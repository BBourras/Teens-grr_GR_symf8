<?php

declare(strict_types=1);

namespace App\Contract;

/**
 * Interface marquant les entités qui doivent cibler exactement
 * un Post OU un Comment (contrainte XOR).
 *
 * Implémentée par Report et ModerationActionLog.
 */
interface XorTargetInterface
{
    /**
     * Vérifie que l'entité cible exactement un Post ou un Comment.
     *
     * @throws \LogicException si la contrainte XOR n'est pas respectée
     */
    public function assertExactlyOneTarget(): void;
}