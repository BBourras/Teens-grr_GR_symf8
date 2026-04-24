<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Types de votes/réactions possibles sur un Post.
 *
 * Poids pour le reactionScore dénormalisé :
 * ---------------------------------------------------
 * Le reactionScore sur Post est une somme pondérée de votes.
 * Les poids sont SIGNÉS :
 * - Positif → augmente le score (réaction positive/neutre)
 * - Négatif → diminue le score (réaction négative)
 *
 * Cohérence avec l'algorithme de classement (PostRepository) :
 * ---------------------------------------------------
 * findTrendingPosts() favorise LAUGH + DISILLUSIONED et pénalise ANGRY.
 * weight() reflète cette même logique pour le score dénormalisé.
 *
 * ⚠️ Si weight() est modifié, recalculer le reactionScore en base via une commande de maintenance.
 */
enum VoteType: string
{
    case LAUGH         = 'laugh';
    case ANGRY         = 'angry';
    case DISILLUSIONED = 'disillusioned';

    // ======================================================
    // PONDÉRATION
    // ======================================================
    /**
     * Poids signé pour le score dénormalisé du post.
     *
     * LAUGH         → +3 (drôle, réaction positive)
     * DISILLUSIONED → +2 (ironie comprise, engagement positif)
     * ANGRY         → -1 (réaction négative, pénalise légèrement)
     */
    public function weight(): int
    {
        return match ($this) {
            self::LAUGH         => 3,
            self::DISILLUSIONED => 2,
            self::ANGRY         => -1,
        };
    }

    // ======================================================
    // AFFICHAGE
    // ======================================================

    /**
     * Emoji associé pour l'affichage UI.
     */
    public function emoji(): string
    {
        return match ($this) {
            self::LAUGH         => '😂',
            self::ANGRY         => '😡',
            self::DISILLUSIONED => '😏',
        };
    }

    /**
     * Label français pour l'affichage UI.
     * Remplace ucfirst($this->value) qui retournait de l'anglais.
     */
    public function label(): string
    {
        return match ($this) {
            self::LAUGH         => 'Trop drôle',
            self::ANGRY         => 'Énervant',
            self::DISILLUSIONED => 'Tellement vrai…',
        };
    }

    /**
     * Clé de traduction i18n (si le composant Translation est activé).
     */
    public function labelKey(): string
    {
        return match ($this) {
            self::LAUGH         => 'vote.laugh',
            self::ANGRY         => 'vote.angry',
            self::DISILLUSIONED => 'vote.disillusioned',
        };
    }

    /**
     * Label complet pour l'affichage UI : emoji + libellé français.
     */
    public function displayLabel(): string
    {
        return $this->emoji() . ' ' . $this->label();
    }

    // ======================================================
    // UTILITAIRES
    // ======================================================

    /**
     * Retourne toutes les valeurs string (validation, requêtes).
     */
    public static function values(): array
    {
        return array_map(static fn(self $t) => $t->value, self::cases());
    }

    /**
     * ⚠️ IMPORTANT :
     * On utilise value => value pour éviter tout problème si les labels changent dans le futur.
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $case) {
            $choices[$case->value] = $case->value;
        }

        return $choices;
    }
}
