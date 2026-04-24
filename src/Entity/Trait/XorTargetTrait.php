<?php

declare(strict_types=1);

namespace App\Entity\Trait;

/**
 * Trait qui factorise la validation XOR pour les entités qui doivent cibler
 * exactement un Post OU un Comment (jamais les deux, jamais aucun).
 *
 * Ce trait fournit l'implémentation de la méthode déclarée dans XorTargetInterface.
 */
trait XorTargetTrait
{
    /**
     * @throws \LogicException
     */
    public function assertExactlyOneTarget(): void
    {
        $hasPost    = $this->post !== null;
        $hasComment = $this->comment !== null;

        if ($hasPost === $hasComment) {
            throw new \LogicException(
                'Un signalement ou log de modération doit cibler soit un post, soit un commentaire — pas les deux, pas aucun.'
            );
        }
    }
}