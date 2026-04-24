<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use App\Enum\ContentStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait partagé pour gérer le statut et les dates de modération/soft-delete
 * des entités Post et Comment.
 *
 * Ce trait contient :
 * - Le mapping Doctrine (status, deleted_at, updated_at)
 * - Les lifecycle callbacks
 * - Toutes les méthodes métier de visibilité et de statut
 *
 * Utilise désormais l'enum central ContentStatus (plus de duplication).
 */

trait ContentStatusBehavior
{
    // ======================================================
    // MAPPING DOCTRINE
    // ======================================================

    #[ORM\Column(type: 'string', enumType: ContentStatus::class, length: 50)]
    protected ContentStatus $status = ContentStatus::PUBLISHED;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // ======================================================
    // LIFECYCLE CALLBACKS
    // ======================================================

    #[ORM\PrePersist]
    public function onPrePersistStatus(): void
    {
        // Statut par défaut déjà initialisé dans la propriété
    }

    #[ORM\PreUpdate]
    public function onPreUpdateStatus(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ======================================================
    // MÉTHODES MÉTIER (déléguées à l'enum central)
    // ======================================================

    public function isVisible(): bool
    {
        return $this->status->isVisible() && $this->deletedAt === null;
    }

    public function isHidden(): bool
    {
        return $this->status->isHidden();
    }

    public function isDeleted(): bool
    {
        return $this->status->isDeleted();
    }

    public function isModerated(): bool
    {
        return $this->status->isModerated();
    }

    public function isAutoModerated(): bool
    {
        return $this->status->isAutoModerated();
    }

    public function isManuallyModerated(): bool
    {
        return $this->status->isManuallyModerated();
    }

    /**
     * Marque le contenu comme supprimé (soft delete + statut).
     */
    public function markAsDeleted(): static
    {
        $this->status = ContentStatus::DELETED;
        $this->deletedAt = new \DateTimeImmutable();
        return $this;
    }

    // ======================================================
    // MÉTHODES REQUISES PAR ContentStatusInterface
    // ======================================================

    /**
     * Clé de traduction pour l'affichage i18n.
     * Déléguée à l'enum central.
     */
    public function labelKey(): string
    {
        return $this->status->labelKey();
    }

    /**
     * Label français direct (sans passer par le composant Translation).
     * Déléguée à l'enum central.
     */
    public function label(): string
    {
        return $this->status->label();
    }

    // ======================================================
    // GETTERS & SETTERS
    // ======================================================

    /**
     * Retourne l'enum correspondant au statut.
     */
    public function getStatusEnum(): ContentStatus
    {
        return $this->status;
    }

    /**
     * Pour Twig : retourne le statut sous forme de string
     */
    public function getStatusValue(): string
    {
        return $this->status->value;
    }

    /**
     * Définit le statut à partir de l'enum central.
     */
    public function setStatus(ContentStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
