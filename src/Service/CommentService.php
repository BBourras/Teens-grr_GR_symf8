<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Enum\ContentStatus;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service métier pour la gestion des Commentaires.
 *
 * ⚠️ IMPORTANT :
 * - AUCUNE logique de modération ici
 * - Toute suppression / masquage passe par ModerationService
 */
class CommentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommentRepository $commentRepository,
        private readonly ModerationService $moderationService,
    ) {}

    /**
     * Crée un nouveau commentaire sur un post.
     * Utilisé par les fixtures et le CommentController.
     */
    public function createComment(string $content, User $author, Post $post): Comment
    {
        $comment = new Comment();
        $comment->setContent($content);

        $this->em->wrapInTransaction(function () use ($comment, $post, $author) {
            $comment->assignAuthor($author);
            $comment->assignPost($post);

            $comment->setStatus(ContentStatus::PUBLISHED);
            $post->incrementCommentCount();

            $this->em->persist($comment);
        });

        return $comment;
    }

    /**
     * Suppression du commentaire par son auteur.
     * Déclenche une trace AUTHOR_DELETE via ModerationService.
     */
    public function deleteByAuthor(Comment $comment, User $author): void
    {
        $this->moderationService->deleteByAuthor($comment, $author);
    }

    /**
     * Suppression physique définitive (rarement utilisée).
     */
    public function hardDelete(Comment $comment): void
    {
        $this->em->wrapInTransaction(fn() => $this->em->remove($comment));
    }

    /**
     * Retourne uniquement les commentaires visibles d'un post.
     */
    public function getVisibleCommentsByPost(Post $post): array
    {
        return $this->commentRepository->findVisibleCommentsByPost($post);
    }

    /**
     * Retourne tous les commentaires d'un post (pour MODERATEURS).
     */
    public function getAllCommentsByPost(Post $post): array
    {
        return $this->commentRepository->findAllCommentsByPost($post);
    }

    /**
     * Retourne les commentaires masqués automatiquement, en attente de décision manuelle.
     * Utilisé par ModerationController::dashboard().
     */
    public function getAutoHiddenPendingComments(): array
    {
        return $this->commentRepository->findAutoHiddenPendingComments();
    }
} 