<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Post;
use App\Enum\ContentStatus;
use App\Enum\VoteType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository principal des Posts.
 *
 * Optimisations systématiques :
 * - Tous les classements sont calculés en SQL (pas de tri PHP)
 * - L'auteur est joint en EAGER sur toutes les listes pour éviter le N+1 en affichage Twig
 *
 * Compatibilité base de données :
 * - Les fonctions TIMESTAMPDIFF() et POWER() sont natives MySQL / MariaDB. 
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    // ======================================================
    // LISTES PUBLIQUES
    // ======================================================

    public function findLatestPosts(int $limit = 10): array
    {
        return $this->createLatestPostsQueryBuilder()
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function createLatestPostsQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->where('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('status', ContentStatus::PUBLISHED)
            ->orderBy('p.createdAt', 'DESC');
    }

    // ======================================================
    // CLASSEMENTS ÉDITORIAUX
    // ======================================================

    /**
     * 🔥 TOP DU MOMENT — classement pondéré avec déclin temporel.
     */
    public function findTrendingPosts(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->leftJoin('p.votes', 'v')
            ->where('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('status', ContentStatus::PUBLISHED)
            ->addSelect('
                (
                    SUM(CASE WHEN v.type IN (:humourTypes) THEN 1 ELSE 0 END) * 3
                    - SUM(CASE WHEN v.type = :angry THEN 1 ELSE 0 END) * 1.5
                    + COUNT(v.id) * 0.5
                )
                / POWER(
                    TIMESTAMPDIFF(HOUR, p.createdAt, CURRENT_TIMESTAMP()) + 6,
                    1.2
                ) AS HIDDEN rankingScore
            ')
            ->groupBy('p.id, a.id')
            ->orderBy('rankingScore', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('humourTypes', [VoteType::LAUGH->value, VoteType::DISILLUSIONED->value])
            ->setParameter('angry', VoteType::ANGRY->value)
            ->getQuery()
            ->getResult();
    }

    /**
     * 🏛 LÉGENDES — classement durable sans déclin temporel.
     *
     * Calcul : (Laugh + Disillusioned) × 2
     * Pas de facteur temporel → les vieux posts drôles ou justes restent légendaires.
     */
    public function findLegendPosts(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->leftJoin('p.votes', 'v')
            ->where('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('status', ContentStatus::PUBLISHED)
            ->addSelect('
                SUM(CASE WHEN v.type IN (:humourTypes) THEN 1 ELSE 0 END) * 2 AS HIDDEN rankingScore
            ')
            ->groupBy('p.id, a.id')
            ->orderBy('rankingScore', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('humourTypes', [VoteType::LAUGH->value, VoteType::DISILLUSIONED->value])
            ->getQuery()
            ->getResult();
    }

    // ======================================================
    // MODÉRATION
    // ======================================================

    public function findAutoHiddenPendingPosts(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->where('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('status', ContentStatus::AUTO_HIDDEN)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}