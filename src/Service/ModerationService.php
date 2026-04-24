<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\ModeratableContentInterface;
use App\Entity\ModerationActionLog;
use App\Entity\Post;
use App\Entity\User;
use App\Enum\ContentStatus;
use App\Enum\ModerationActionType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * 🔥 SERVICE CENTRAL DE MODÉRATION
 *
 * Toute modification de statut (publication, masquage, suppression, restauration)
 * DOIT passer par ce service pour garantir la traçabilité complète.
 *
 * Responsabilités :
 * - Changement de statut avec atomicité
 * - Enregistrement systématique dans ModerationActionLog
 * - Distinction claire entre actions auteur / modérateur / automatique
 */
class ModerationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Masquage manuel par un modérateur.
     */
    public function hideByModerator(ModeratableContentInterface $entity, User $moderator, ?string $reason = null): void
    {
        $this->changeStatus(
            $entity,
            ContentStatus::HIDDEN_BY_MODERATOR,
            ModerationActionType::MODERATOR_HIDE,
            $moderator,
            $reason
        );
    }

    /**
     * Masquage automatique après seuil de signalements.
     */
    public function autoHide(ModeratableContentInterface $entity): void
    {
        $this->changeStatus(
            $entity,
            ContentStatus::AUTO_HIDDEN,
            ModerationActionType::AUTO_HIDE,
            null, // action système
            'Masquage automatique après seuil de signalements'
        );
    }

    /**
     * Suppression par un modérateur.
     */
    public function deleteByModerator(ModeratableContentInterface $entity, User $moderator, ?string $reason = null): void
    {
        $this->changeStatus(
            $entity,
            ContentStatus::DELETED,
            ModerationActionType::MODERATOR_DELETE,
            $moderator,
            $reason
        );
    }

    /**
     * Suppression par l’auteur du contenu.
     * Utilisé par PostService::deleteByAuthor() et CommentService::deleteByAuthor().
     */
    public function deleteByAuthor(ModeratableContentInterface $entity, User $author): void
    {
        $this->changeStatus(
            $entity,
            ContentStatus::DELETED,
            ModerationActionType::AUTHOR_DELETE,
            $author,
            'Suppression demandée par l’auteur'
        );
    }

    /**
     * Restauration d’un contenu masqué ou supprimé.
     */
    public function restore(ModeratableContentInterface $entity, ?User $moderator = null, ?string $reason = null): void
    {
        $this->changeStatus(
            $entity,
            ContentStatus::PUBLISHED,
            ModerationActionType::RESTORE,
            $moderator,
            $reason
        );
    }

    /**
     * Méthode privée centrale qui gère le changement de statut + logging.
     */
    private function changeStatus(
        ModeratableContentInterface $entity,
        ContentStatus $newStatus,
        ModerationActionType $actionType,
        ?User $moderator,
        ?string $reason = null
    ): void {
        $this->em->wrapInTransaction(function () use ($entity, $newStatus, $actionType, $moderator, $reason) {
            $previous = $entity->getStatusEnum();

            $entity->setStatus($newStatus);

            // Gestion du soft delete
            if ($newStatus === ContentStatus::DELETED) {
                $entity->setDeletedAt(new \DateTimeImmutable());
            } elseif ($newStatus === ContentStatus::PUBLISHED) {
                $entity->setDeletedAt(null);
            }

            $this->logAction($entity, $actionType, $previous, $newStatus, $moderator, $reason);
        });
    }

    /**
     * Enregistre l’action dans ModerationActionLog.
     */
    private function logAction(
        ModeratableContentInterface $entity,
        ModerationActionType $actionType,
        ContentStatus $previous,
        ContentStatus $newStatus,
        ?User $moderator,
        ?string $reason
    ): void {
        $log = (new ModerationActionLog())
            ->setActionType($actionType)
            ->setModerator($moderator)
            ->setReason($reason)
            ->setPreviousStatus($previous)
            ->setNewStatus($newStatus);

        // Assignation du target (post ou comment)
        if ($entity instanceof Post) {
            $log->setPost($entity);
        } else {
            $log->setComment($entity);
        }

        $this->em->persist($log);
    }
}