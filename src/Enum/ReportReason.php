<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Raisons possibles de signalement d'un Post ou d'un Commentaire.
 *
 * Ces raisons sont utilisées dans le formulaire de signalement
 * et stockées dans l'entité Report.
 */
enum ReportReason: string
{
    case SPAM           = 'spam';
    case HARASSMENT     = 'harassment';
    case OFF_TOPIC      = 'off_topic';
    case HATE_SPEECH    = 'hate_speech';
    case MISINFORMATION = 'misinformation';
    case INAPPROPRIATE  = 'inappropriate';
    case OTHER          = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SPAM           => 'Spam',
            self::HARASSMENT     => 'Harcèlement',
            self::OFF_TOPIC      => 'Hors-sujet',
            self::HATE_SPEECH    => 'Discours haineux',
            self::MISINFORMATION => 'Désinformation',
            self::INAPPROPRIATE  => 'Contenu inapproprié',
            self::OTHER          => 'Autre',
        };
    }

    /**
     * Clé de traduction i18n (pour cohérence avec les autres enums).
     */
    public function labelKey(): string
    {
        return match ($this) {
            self::SPAM           => 'report.reason.spam',
            self::HARASSMENT     => 'report.reason.harassment',
            self::OFF_TOPIC      => 'report.reason.off_topic',
            self::HATE_SPEECH    => 'report.reason.hate_speech',
            self::MISINFORMATION => 'report.reason.misinformation',
            self::INAPPROPRIATE  => 'report.reason.inappropriate',
            self::OTHER          => 'report.reason.other',
        };
    }

    /**
     * Indique si cette raison de signalement nécessite une revue manuelle par un modérateur.
     *
     * Les raisons graves (harcèlement, discours haineux, contenu inapproprié) 
     * sont automatiquement priorisées dans le dashboard de modération et 
     * peuvent déclencher des notifications ou un traitement plus rapide.
     *
     * Utile pour filtrer/prioriser les signalements dans ModerationService 
     * et le ModerationController.
     */
    public function requiresModeratorReview(): bool
    {
        return match ($this) {
            self::HARASSMENT,
            self::HATE_SPEECH,
            self::INAPPROPRIATE => true,
            default             => false,
        };
    }

    /**
     * Retourne toutes les valeurs string de l'enum.
     * Utile pour la validation, les queries et les fixtures.
     */
    public static function values(): array
    {
        return array_map(static fn(self $r) => $r->value, self::cases());
    }

    /**
     * value => value pour éviter les bugs liés aux labels.
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
