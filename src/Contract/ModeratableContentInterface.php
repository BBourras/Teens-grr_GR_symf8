<?php

declare(strict_types=1);

namespace App\Contract;

use App\Entity\Post;
use App\Entity\User;
use App\Enum\ContentStatus;

/**
 * Interface commune pour tout contenu modérable (Post et Comment).
 *
 * Permet un traitement polymorphe dans ModerationService, ReportService,
 * Voters et templates sans instanceof ni duplication.
 *
 * Garantit la cohérence du cycle de vie (statut, visibilité, compteurs).
 */
interface ModeratableContentInterface extends ContentStatusInterface
{
    /**
     * Retourne le statut sous forme d'enum centralisé.
     */
    public function getStatusEnum(): ContentStatus;

    /**
     * Définit le statut (utilisé uniquement via ModerationService).
     */
    public function setStatus(ContentStatus $status): self;

    /**
     * Définit la date de suppression (soft delete).
     * Utilisé dans ModerationService lors du passage à DELETED ou PUBLISHED.
     */
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self;

    /**
     * Compteur de signalements (dénormalisé).
     */
    public function getReportCount(): int;

    /**
     * Incrémente le compteur de signalements.
     */
    public function incrementReportCount(int $by = 1): self;

    /**
     * Retourne l'auteur du contenu.
     */
    public function getAuthor(): User;

    /**
     * Identifiant (utile pour les logs et relations).
     */
    public function getId(): ?int;

    /**
     * Retourne le type de cible ('post' ou 'comment') – utile pour les logs.
     */
    public function getTargetType(): string;

    /**
     * Retourne le Post parent.
     * - Pour un Post → retourne $this
     * - Pour un Comment → retourne le post auquel il est attaché
     */
    public function getPost(): Post;
}