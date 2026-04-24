<?php

declare(strict_types=1);

namespace App\Enum;

use App\Contract\ContentStatusInterface;

/**
 * Enum centralisé unique pour les statuts de contenu (Post ET Comment).
 *
 * Cycle de vie :
 * ---------------------------------------------------
 * PUBLISHED → AUTO_HIDDEN (seuil signalements atteint)
 * AUTO_HIDDEN → PUBLISHED (modérateur infirme)
 * AUTO_HIDDEN → HIDDEN_BY_MODERATOR (modérateur confirme)
 * PUBLISHED → HIDDEN_BY_MODERATOR (masquage manuel direct)
 * * → DELETED (suppression auteur ou modérateur)
 */
enum ContentStatus: string implements ContentStatusInterface
{
    case PUBLISHED           = 'published';
    case AUTO_HIDDEN         = 'auto_hidden';
    case HIDDEN_BY_MODERATOR = 'hidden_by_moderator';
    case DELETED             = 'deleted';

    /**
     * Clé de traduction i18n (préfixe commun).
     */
    public function labelKey(): string
    {
        return match ($this) {
            self::PUBLISHED           => 'content.status.published',
            self::AUTO_HIDDEN         => 'content.status.auto_hidden',
            self::HIDDEN_BY_MODERATOR => 'content.status.hidden_by_moderator',
            self::DELETED             => 'content.status.deleted',
        };
    }

    /**
     * Label français direct pour l'affichage UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::PUBLISHED           => 'Publié',
            self::AUTO_HIDDEN         => 'Masqué automatiquement',
            self::HIDDEN_BY_MODERATOR => 'Masqué par un modérateur',
            self::DELETED             => 'Supprimé',
        };
    }

    // ====================== Implémentation de ContentStatusInterface ======================

    public function isVisible(): bool
    {
        return $this === self::PUBLISHED;
    }

    public function isHidden(): bool
    {
        return $this === self::AUTO_HIDDEN || $this === self::HIDDEN_BY_MODERATOR;
    }

    public function isDeleted(): bool
    {
        return $this === self::DELETED;
    }

    public function isModerated(): bool
    {
        return $this !== self::PUBLISHED;
    }

    public function isAutoModerated(): bool
    {
        return $this === self::AUTO_HIDDEN;
    }

    public function isManuallyModerated(): bool
    {
        return $this === self::HIDDEN_BY_MODERATOR || $this === self::DELETED;
    }
}