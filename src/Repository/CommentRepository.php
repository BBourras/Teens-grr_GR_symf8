<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Post;
use App\Enum\ContentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des commentaires.
 * - Toutes les méthodes qui retournent des listes joignent l'auteur en EAGER pour éviter le N+1 en affichage Twig.
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Retourne les commentaires visibles (PUBLISHED, non soft-deleted) pour un post donné, avec l'auteur pré-chargé.
     * Utilisé par CommentService::getVisibleCommentsByPost().
     */
    public function findVisibleCommentsByPost(Post $post): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')
            ->addSelect('a')
            ->where('c.post = :post')
            ->andWhere('c.status = :status')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('post', $post)
            ->setParameter('status', ContentStatus::PUBLISHED)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne tous les commentaires d'un post (tous statuts), avec l'auteur pré-chargé.
     * Utilisé pour les modérateurs.
     */
    public function findAllCommentsByPost(Post $post): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')
            ->addSelect('a')
            ->where('c.post = :post')
            ->orderBy('c.createdAt', 'DESC')
            ->setParameter('post', $post)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les commentaires masqués automatiquement, en attente de décision manuelle.
     */
    public function findAutoHiddenPendingComments(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')
            ->addSelect('a')
            ->where('c.status = :status')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('status', ContentStatus::AUTO_HIDDEN)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}