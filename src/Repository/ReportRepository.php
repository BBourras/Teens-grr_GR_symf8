<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\Report;
use App\Entity\User;
use App\Enum\ReportReason;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des signalements.
 *
 * Note sur les compteurs dénormalisés :
 * Post::getReportCount() et Comment::getReportCount() sont maintenus par ReportService.
 * Ce repository ne duplique pas ces compteurs.
 */
class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    /**
     * Vérifie si un utilisateur a déjà signalé un post.
     */
    public function hasAlreadyReportedPost(Post $post, User $user): bool
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.post = :post')
            ->andWhere('r.user = :user')
            ->setParameter('post', $post)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Vérifie si un utilisateur a déjà signalé un commentaire.
     */
    public function hasAlreadyReportedComment(Comment $comment, User $user): bool
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.comment = :comment')
            ->andWhere('r.user = :user')
            ->setParameter('comment', $comment)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Retourne les signalements en attente de modération (pour le dashboard).
     * Triés par gravité puis par date.
     */
    public function findPendingReports(int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->leftJoin('r.post', 'p')
            ->leftJoin('r.comment', 'c')
            ->addSelect('u', 'p', 'c')
            ->where('r.reason IN (:seriousReasons)')
            ->orWhere('r.createdAt >= :recent')
            ->setParameter('seriousReasons', [
                ReportReason::HARASSMENT->value,
                ReportReason::HATE_SPEECH->value,
                ReportReason::INAPPROPRIATE->value
            ])
            ->setParameter('recent', new \DateTimeImmutable('-7 days'))
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}