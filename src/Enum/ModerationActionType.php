<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Types d'actions enregistrées dans ModerationActionLog.
 *
 * Chaque action modifiant le statut d'un Post ou Commentaire doit être tracée avec l'un de ces types.
 *
 * Actions automatiques (système, moderator = null) :
 * ---------------------------------------------------
 * - AUTO_HIDE : masquage automatique après N signalements
 *
 * Actions manuelles (modérateur ou admin) :
 * ---------------------------------------------------
 * - MODERATOR_HIDE     : masquage manuel
 * - RESTORE            : restauration / republication
 * - MODERATOR_DELETE   : suppression par modérateur
 * - REPORTS_CONFIRMED  : signalements confirmés (contenu reste caché)
 * - REPORTS_REJECTED  : signalements rejetés (contenu restauré)
 *
 * Actions auteur :
 * ---------------------------------------------------
 * - AUTHOR_DELETE : suppression par l'auteur lui-même
 *   → Tracée par PostService::delete() et CommentService::delete()
 *     via ModerationService::logAuthorDelete() pour conserver
 *     l'historique complet en cas de litige.
 *
 * Actions informationnelles (audit fin) :
 * ---------------------------------------------------
 * - REPORT_CREATED : création d'un signalement
 *   → Tracée par ReportService si l'audit détaillé est activé.
 *   → Optionnel : peut rester inutilisé si les Reports
 *     suffisent comme source d'audit.
 */
enum ModerationActionType: string
{
    // ── Automatique ───────────────────────────────────────
    case AUTO_HIDE          = 'auto_hide';

    // ── Manuel (modérateur / admin) ───────────────────────
    case MODERATOR_HIDE     = 'moderator_hide';
    case RESTORE            = 'restore';
    case MODERATOR_DELETE   = 'moderator_delete';
    case REPORTS_CONFIRMED  = 'reports_confirmed';
    case REPORTS_REJECTED  = 'reports_rejected';

    // ── Auteur ────────────────────────────────────────────
    case AUTHOR_DELETE      = 'author_delete';

    // ── Informationnel (audit fin, optionnel) ─────────────
    case REPORT_CREATED     = 'report_created';

    // ======================================================
    // CLASSIFICATION
    // ======================================================

    /**
     * Action déclenchée par le système (sans intervention humaine).
     */
    public function isAutomatic(): bool
    {
        return $this === self::AUTO_HIDE;
    }

    /**
     * Action déclenchée par un modérateur ou administrateur.
     */
    public function isModerationAction(): bool
    {
        return \in_array($this, [
            self::MODERATOR_HIDE,
            self::RESTORE,
            self::MODERATOR_DELETE,
            self::REPORTS_CONFIRMED,
            self::REPORTS_REJECTED,
        ], true);
    }

    /**
     * Action déclenchée par l'auteur du contenu.
     */
    public function isAuthorAction(): bool
    {
        return $this === self::AUTHOR_DELETE;
    }

    /**
     * L'action aboutit à une suppression (logique ou physique).
     */
    public function isDelete(): bool
    {
        return \in_array($this, [
            self::AUTHOR_DELETE,
            self::MODERATOR_DELETE,
        ], true);
    }

    /**
     * L'action restaure le contenu à l'état publié.
     */
    public function isRestore(): bool
    {
        return \in_array($this, [
            self::RESTORE,
            self::REPORTS_REJECTED,
        ], true);
    }

    // ======================================================
    // AFFICHAGE
    // ======================================================

    /**
     * Clé de traduction i18n.
     */
    public function labelKey(): string
    {
        return match ($this) {
            self::AUTO_HIDE         => 'moderation.action.auto_hide',
            self::MODERATOR_HIDE    => 'moderation.action.moderator_hide',
            self::RESTORE           => 'moderation.action.restore',
            self::MODERATOR_DELETE  => 'moderation.action.moderator_delete',
            self::REPORTS_CONFIRMED => 'moderation.action.reports_confirmed',
            self::REPORTS_REJECTED => 'moderation.action.reports_rejected',
            self::AUTHOR_DELETE     => 'moderation.action.author_delete',
            self::REPORT_CREATED    => 'moderation.action.report_created',
        };
    }

    /**
     * Label français direct pour l'affichage UI (dashboard modération).
     */
    public function label(): string
    {
        return match ($this) {
            self::AUTO_HIDE         => 'Masquage automatique',
            self::MODERATOR_HIDE    => 'Masquage manuel',
            self::RESTORE           => 'Restauration',
            self::MODERATOR_DELETE  => 'Suppression par modérateur',
            self::REPORTS_CONFIRMED => 'Signalements confirmés',
            self::REPORTS_REJECTED => 'Signalements rejetés',
            self::AUTHOR_DELETE     => 'Suppression par l\'auteur',
            self::REPORT_CREATED    => 'Signalement créé',
        };
    }

    /**
     * Retourne toutes les valeurs string de l'enum.
     */
    public static function values(): array
    {
        return array_map(static fn(self $a) => $a->value, self::cases());
    }
}
