<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\VoteType;
use App\Repository\VoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité représentant un vote/réaction (emoji) sur un Post
 * - 1 vote maximum par utilisateur connecté et par post
 * - 1 vote maximum par invité (guestKey) et par post
 * - Un vote peut être modifié (changement d'emoji) → updatedAt
 * - Pour les invités, guestKey ne doit JAMAIS être null (validation garantie par VoteService avant persistance)
 * - Suppression en cascade si Post ou User supprimé
 *
 * Contrainte XOR user / guest :
 * - Soit user est renseigné (vote connecté), soit guestKey est renseigné (vote invité)
 * - Jamais les deux, jamais aucun
 * → Validé dans onPrePersist()
 *
 * Note SQL sur l'unicité guest :
 * - La contrainte uniq_vote_guest_post ne protège que si guest_key IS NOT NULL. VoteService doit s'assurer
 * qu'un guestKey est toujours fourni pour les invités.
 *
 * Optimisations :
 * - Index composite (post_id, type) pour classements
 * - Index composite invité pour limitation 24h
 */
#[ORM\Entity(repositoryClass: VoteRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(
    name: 'vote',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_vote_user_post', columns: ['user_id', 'post_id']),
        new ORM\UniqueConstraint(name: 'uniq_vote_guest_post', columns: ['guest_key', 'post_id']),
    ],
    indexes: [
        new ORM\Index(name: 'idx_vote_post',              columns: ['post_id']),
        new ORM\Index(name: 'idx_vote_post_type',         columns: ['post_id', 'type']),
        new ORM\Index(name: 'idx_vote_created_at',        columns: ['created_at']),
        new ORM\Index(name: 'idx_vote_guest_key',         columns: ['guest_key']),
        new ORM\Index(name: 'idx_vote_guest_post_created', columns: ['guest_key', 'post_id', 'created_at']),
    ]
)]
class Vote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ======================================================
    // RELATIONS
    // ======================================================
    /**
     * Utilisateur connecté (null pour les invités).
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'votes', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * Post concerné par ce vote.
     */
    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'votes', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Post $post;

    // ======================================================
    // DONNÉES MÉTIER
    // ======================================================
    /**
     * Type de vote/réaction (LAUGH / ANGRY / DISILLUSIONED…).
     */
    #[ORM\Column(enumType: VoteType::class)]
    private VoteType $type;

    /**
     * Identifiant invité (UUID v4 stocké en cookie).
     * ⚠️ Obligatoire pour tout vote invité (user === null).
     *    Jamais renseigné pour un vote connecté (user !== null).
     */
    #[ORM\Column(name: 'guest_key', length: 64, nullable: true)]
    private ?string $guestKey = null;

    /**
     * Hash de l'IP invité (SHA-256, RGPD-compliant).
     * Utilisé pour la limitation anti-abus sur 24h.
     * Optionnel : null si l'IP n'a pas pu être récupérée.
     */
    #[ORM\Column(name: 'guest_ip_hash', length: 64, nullable: true)]
    private ?string $guestIpHash = null;

    // ======================================================
    // DATES
    // ======================================================
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * Date de dernière modification du vote (changement d'emoji).
     * Null si le vote n'a jamais été modifié.
     */
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // ======================================================
    // LIFECYCLE
    // ======================================================
    /**
     * Initialise createdAt et valide la contrainte XOR user/guest.
     *
     * @throws \LogicException si user et guestKey sont tous les deux null ou tous les deux renseignés
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
        $this->assertValidVoter();
    }

    /**
     * Met à jour updatedAt lors d'un changement d'emoji.
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ======================================================
    // VALIDATION MÉTIER
    // ======================================================
    /**
     * Garantit qu'un vote est soit utilisateur, soit invité.
     *
     * Règle XOR :
     * - user renseigné + guestKey null  → vote connecté ✅
     * - user null + guestKey renseigné  → vote invité ✅
     * - user null + guestKey null       → invalide ❌
     * - user renseigné + guestKey rens. → invalide ❌
     *
     * @throws \LogicException
     */
    public function assertValidVoter(): void
    {
        $hasUser  = $this->user !== null;
        $hasGuest = $this->guestKey !== null && $this->guestKey !== '';
        if ($hasUser === $hasGuest) {
            throw new \LogicException(
                'Un vote doit avoir soit un utilisateur connecté, soit une clé invité — pas les deux, pas aucun.'
            );
        }
    }

    // ======================================================
    // HELPERS MÉTIER — Méthodes publiques d'assignation
    // ======================================================

    /**
     * Assigne un utilisateur connecté (setter protégé).
     */
    public function assignUser(User $user): static
    {
        $this->setUser($user);
        return $this;
    }

    /**
     * Assigne les données invité (setter protégé).
     */
    public function assignGuest(string $guestKey, ?string $guestIpHash = null): static
    {
        $this->setGuestKey($guestKey);
        if ($guestIpHash !== null) {
            $this->setGuestIpHash($guestIpHash);
        }
        return $this;
    }

    /**
     * Assigne le post concerné (setter protégé).
     */
    public function assignPost(Post $post): static
    {
        $this->setPost($post);
        return $this;
    }

    // ======================================================
    // HELPERS MÉTIER
    // ======================================================
    public function isUserVote(): bool
    {
        return $this->user !== null;
    }

    public function isGuestVote(): bool
    {
        return $this->user === null;
    }

    // ======================================================
    // GETTERS & SETTERS (protégés pour les assignations critiques)
    // ======================================================
    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    protected function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getPost(): Post { return $this->post; }
    protected function setPost(Post $post): static { $this->post = $post; return $this; }

    public function getType(): VoteType { return $this->type; }
    public function setType(VoteType $type): static { $this->type = $type; return $this; }

    public function getGuestKey(): ?string { return $this->guestKey; }
    protected function setGuestKey(?string $guestKey): static { $this->guestKey = $guestKey; return $this; }

    public function getGuestIpHash(): ?string { return $this->guestIpHash; }
    protected function setGuestIpHash(?string $guestIpHash): static { $this->guestIpHash = $guestIpHash; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}