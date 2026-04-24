<?php

declare(strict_types=1);

namespace App\Contract;

/**
 * Interface commune aux statuts de contenu (Post, Comment).
 * ContentStatus expose des méthodes pour le code métier (ModerationService, Voters, templates, etc.) 
 */
interface ContentStatusInterface
{
    /**
     * Le contenu est publié et visible publiquement.
     */
    public function isVisible(): bool;

    /**
     * Le contenu est masqué (auto ou manuel), mais pas supprimé.
     */
    public function isHidden(): bool;

    /**
     * Le contenu est supprimé (soft delete).
     */
    public function isDeleted(): bool;

    /**
     * Le contenu a subi une modération (tout sauf PUBLISHED).
     */
    public function isModerated(): bool;

    /**
     * Le contenu a été masqué automatiquement par le système.
     */
    public function isAutoModerated(): bool;

    /**
     * Le contenu a été masqué ou supprimé par un humain.
     */
    public function isManuallyModerated(): bool;

    /**
     * Clé de traduction pour l'affichage i18n.
     */
    public function labelKey(): string;

    /**
     * Label français direct (sans passer par le composant Translation).
     */
    public function label(): string;
}