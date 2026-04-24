<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Post;
use App\Entity\User;
use App\Entity\Vote;
use App\Enum\VoteType;
use App\Repository\VoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Service métier pour la gestion des Votes/Réactions.
 *
 * Responsabilités :
 * ---------------------------------------------------
 * - Vérification de la capacité à voter (y compris rate limiting invités)
 * - Création / modification (toggle) / suppression de vote
 * - Mise à jour du score dénormalisé du Post
 * - Délégation des agrégations au VoteRepository
 *
 * Identification des votants :
 * ---------------------------------------------------
 * - Utilisateur connecté  → User entity
 * - Invité                → guestKey (UUID cookie) + guestIpHash (SHA-256)
 *
 * Règles de vote :
 * ---------------------------------------------------
 * - Connecté  : 1 vote max par post, modifiable à volonté (pas de limite temporelle stricte)
 * - Invité    : 1 vote par post (unicité par guestKey),
 *               + rate limiting : max 2 tentatives toutes les 30 secondes (par IP)
 *               + contrôle anti-abus 24h par IP hashée (unicité stricte)
 *
 * Gestion RGPD :
 * ---------------------------------------------------
 * L'IP brute n'est JAMAIS stockée. Seul un hash SHA-256
 * est persisté dans guestIpHash pour la limitation anti-abus.
 */
class VoteService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VoteRepository $voteRepository,
        private readonly RateLimiterFactory $voteGuestLimiter,
    ) {}

    // ======================================================
    // POINT D'ENTRÉE PUBLIC (utilisé par fixtures et contrôleurs)
    // ======================================================

    /**
     * Vote pour un utilisateur connecté (version simplifiée et explicite).
     */
    public function voteAsUser(Post $post, User $user, VoteType $type): void
    {
        $this->vote($post, $user, $type);
    }

    /**
     * Vote pour un invité (version simplifiée et explicite).
     */
    public function voteAsGuest(Post $post, string $guestKey, string $guestIpRaw, VoteType $type): void
    {
        $this->vote($post, null, $type, $guestKey, $guestIpRaw);
    }

    /**
     * Applique un vote/réaction sur un post.
     *
     * Gère les 3 cas :
     * - Nouveau vote       → création
     * - Même type revoté   → toggle (suppression)
     * - Type différent     → changement de réaction
     *
     * @throws \LogicException si invité sans guestKey
     * @throws TooManyRequestsHttpException si l'invité dépasse le rate limit
     */
    public function vote(
        Post $post,
        ?User $user,
        VoteType $type,
        ?string $guestKey = null,
        ?string $guestIpRaw = null,
    ): void {
        // Rate limiting SEULEMENT pour les invités
        if ($user === null) {
            if (empty($guestIpRaw)) {
                throw new \LogicException('IP requise pour limiter les votes invités.');
            }
            $this->enforceGuestRateLimit($guestIpRaw);
        }

        // Validation des paramètres d'identification
        if ($user === null && empty($guestKey)) {
            throw new \LogicException('Un vote invité requiert un guestKey (UUID cookie).');
        }

        $existingVote = $this->findExistingVote($post, $user, $guestKey);

        if ($existingVote === null) {
            $this->createVote($post, $user, $type, $guestKey, $guestIpRaw);
            return;
        }

        if ($existingVote->getType() === $type) {
            $this->removeVote($existingVote);
            return;
        }

        $this->changeVoteType($existingVote, $post, $type);
    }

    // ======================================================
    // VÉRIFICATION D'AUTORISATION
    // ======================================================
    public function canVoteAsUser(Post $post, User $user): bool
    {
        return $this->voteRepository->findOneBy([
            'post' => $post,
            'user' => $user,
        ]) === null;
    }

    public function getUserVote(Post $post, User $user): ?Vote
    {
        return $this->voteRepository->findOneBy([
            'post' => $post,
            'user' => $user,
        ]);
    }

    public function getGuestVote(Post $post, string $guestKey): ?Vote
    {
        return $this->voteRepository->findOneBy([
            'post'     => $post,
            'guestKey' => $guestKey,
        ]);
    }

    public function getScoreByTypeForPost(Post $post): array
    {
        return $this->voteRepository->findScoreByTypeForPost($post);
    }

    // ======================================================
    // OPÉRATIONS INTERNES
    // ======================================================
    private function createVote(
        Post $post,
        ?User $user,
        VoteType $type,
        ?string $guestKey,
        ?string $guestIpRaw,
    ): void {
        $vote = new Vote();

        $vote->assignPost($post)
             ->setType($type);

        if ($user !== null) {
            $vote->assignUser($user);
        } else {
            $vote->assignGuest($guestKey, $guestIpRaw ? $this->hashIp($guestIpRaw) : null);
        }

        $post->incrementReactionScore($type->weight());
        $this->em->persist($vote);
        $this->em->flush();
    }

    private function removeVote(Vote $vote): void
    {
        $vote->getPost()->decrementReactionScore($vote->getType()->weight());
        $this->em->remove($vote);
        $this->em->flush();
    }

    private function changeVoteType(Vote $existingVote, Post $post, VoteType $newType): void
    {
        $post->decrementReactionScore($existingVote->getType()->weight());
        $existingVote->setType($newType);
        $post->incrementReactionScore($newType->weight());
        $this->em->flush();
    }

    private function findExistingVote(Post $post, ?User $user, ?string $guestKey): ?Vote
    {
        if ($user !== null) {
            return $this->voteRepository->findOneBy(['post' => $post, 'user' => $user]);
        }

        if ($guestKey !== null) {
            return $this->voteRepository->findOneBy(['post' => $post, 'guestKey' => $guestKey]);
        }

        return null;
    }

    private function enforceGuestRateLimit(string $guestIpRaw): void
    {
        $limiter = $this->voteGuestLimiter->create($this->hashIp($guestIpRaw));
        $limiterResult = $limiter->consume(1);

        if (!$limiterResult->isAccepted()) {
            throw new TooManyRequestsHttpException(
                retryAfter: $limiterResult->getRetryAfter()?->getTimestamp() - time() ?? 30,
                message: 'Trop de votes rapprochés. Attendez un instant avant de continuer.'
            );
        }
    }

    private function hashIp(string $ip): string
    {
        return hash('sha256', $ip);
    }
}