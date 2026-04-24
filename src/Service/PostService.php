<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Post;
use App\Entity\User;
use App\Enum\ContentStatus;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Service métier central pour la gestion des Posts.
 *
 * Responsabilités :
 * - Création / mise à jour / suppression physique
 * - Lecture (listes publiques / éditoriales)
 *
 * ⚠️ IMPORTANT :
 * - AUCUNE logique de modération (logs, hide, soft delete…) ici
 * - Toute modification de statut métier DOIT passer par ModerationService
 */
class PostService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PostRepository $postRepository,
        private readonly ModerationService $moderationService,
    ) {}

    /**
     * Crée un nouveau post publié.
     * Utilisé par les fixtures, le PostController et les tests.
     */
    public function createPost(string $title, string $content, User $author): Post
    {
        $post = new Post();
        $post->setTitle($title)
             ->setContent($content);

        $this->em->wrapInTransaction(function () use ($post, $author) {
            $post->assignAuthor($author);
            $post->setStatus(ContentStatus::PUBLISHED);

            $this->em->persist($post);
        });

        // ✅ IMPORTANT : garantit que l'ID est généré
        $this->em->flush();

        return $post;
    }

    public function update(Post $post): void
    {
        // Ici on part du principe que l'entité est déjà gérée
        $this->em->flush();
    }

    /**
     * Suppression du post par son auteur.
     * Déclenche une trace AUTHOR_DELETE via ModerationService.
     */
    public function deleteByAuthor(Post $post, User $author): void
    {
        $this->moderationService->deleteByAuthor($post, $author);
    }

    /**
     * Suppression physique définitive (rarement utilisée).
     */
    public function hardDelete(Post $post): void
    {
        $this->em->wrapInTransaction(function () use ($post) {
            $this->em->remove($post);
        });

        // (optionnel mais plus sûr)
        $this->em->flush();
    }

    // ======================================================
    // LECTURE
    // ======================================================

    /**
     * Retourne les derniers posts publiés, avec une limite fixe.
     * Utilisé principalement pour la page d'accueil.
     */
    public function getLatestPosts(int $limit = 10): array
    {
        return $this->postRepository->findLatestPosts($limit);
    }

    /**
     * Retourne un QueryBuilder pour les posts les plus récents.
     * Permet la pagination tout en gardant la logique dans le service.
     */
    public function getLatestPostsQueryBuilder(): QueryBuilder
    {
        return $this->postRepository->createLatestPostsQueryBuilder();
    }

    /**
     * Retourne les « Trending Posts » (Top du moment).
     *
     * Calcul approximatif :
     * (Laugh × 3) + (Disillusioned × 2) - (Angry × 1.5) + (Volume × 0.5)
     * avec déclin temporel (les posts récents sont favorisés).
     */
    public function getTrendingPosts(int $limit = 10): array
    {
        return $this->postRepository->findTrendingPosts($limit);
    }

    /**
     * Retourne les « Legend Posts » (les posts iconiques / intemporels).
     *
     * Calcul :
     * (Laugh + Disillusioned) × 2
     * → Sans aucun déclin temporel.
     * Un vieux post très drôle ou très juste reste légendaire même des mois après.
     */
    public function getLegendPosts(int $limit = 10): array
    {
        return $this->postRepository->findLegendPosts($limit);
    }

    /**
     * Retourne les posts masqués automatiquement (en attente de modération).
     */
    public function getAutoHiddenPosts(): array
    {
        return $this->postRepository->findAutoHiddenPendingPosts();
    }
}