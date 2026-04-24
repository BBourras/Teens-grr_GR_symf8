<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Post;
use App\Entity\Vote;
use App\Enum\VoteType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des votes/réactions.
 *
 * Identification des votants :
 * - Utilisateur connecté → via la relation User
 * - Invité               → via guestKey (UUID cookie)
 *
 * Anti-abus invités :
 * - La limitation 24h est basée sur guestIpHash (SHA-256 de l'IP), pas sur le guestKey (cookie). 
 *   Un invité qui efface son cookie ne peut pas contourner la limite via une nouvelle IP hashée.
 */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    // ======================================================
    // COMPTAGES ANTI-ABUS
    // ======================================================

    /**
     * Compte les votes récents d'un invité pour un post, identifié par son IP hashée.
     * - Utilisé par VoteService::canGuestVote() pour la règle 24h.
     * - Résiste à l'effacement du cookie (contrairement à guestKey).
     *
     * @param \DateTimeInterface $since Borne inférieure (ex: -24h)
     */
    public function countRecentVotesByIpHash(
        Post $post,
        string $guestIpHash,
        \DateTimeInterface $since,
    ): int {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.post = :post')
            ->andWhere('v.guestIpHash = :ipHash')
            ->andWhere('v.createdAt >= :since')
            ->setParameter('post', $post)
            ->setParameter('ipHash', $guestIpHash)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ======================================================
    // SCORE AGRÉGÉ
    // ======================================================

    /**
     * Retourne le nombre de votes par type pour un post.
     * Format retourné : ['laugh' => 12, 'angry' => 3, 'disillusioned' => 8]
     * 
     *  * Note : Doctrine retourne v.type comme une instance de VoteType (grâce à enumType).
     * On normalise en ->value pour la cohérence avec les clés attendues dans les templates.
     *
     * @return array<string, int>
     */
    public function findScoreByTypeForPost(Post $post): array
    {
        $rows = $this->createQueryBuilder('v')
            ->select('v.type AS type, COUNT(v.id) AS voteCount')
            ->where('v.post = :post')
            ->groupBy('v.type')
            ->setParameter('post', $post)
            ->getQuery()
            ->getResult();

        // Initialise toutes les clés à 0
        $score = [];
        foreach (VoteType::cases() as $case) {
            $score[$case->value] = 0;
        }

        // Remplit avec les résultats
        foreach ($rows as $row) {
            $key = $row['type'] instanceof VoteType 
                ? $row['type']->value 
                : (string) $row['type'];
            $score[$key] = (int) $row['voteCount'];
        }

        return $score;
    }
}