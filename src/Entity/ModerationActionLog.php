<?php

declare(strict_types=1);

namespace App\Entity;

use App\Contract\XorTargetInterface;
use App\Entity\Trait\XorTargetTrait;
use App\Enum\ContentStatus;
use App\Enum\ModerationActionType;
use App\Repository\ModerationActionLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal immuable des actions de modération.
 * - Tracer chaque action (masquage, restauration, suppression…)
 * - Conserver l'état avant / après (previousStatus / newStatus)
 * - Identifier l'auteur (modérateur humain ou action auto système)
 * - Stocker un contexte JSON libre pour les données métier variables
 *
 * La contrainte XOR est gérée par ModerationXorListener + assertExactlyOneTarget().
 */
#[ORM\Entity(repositoryClass: ModerationActionLogRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(
    name: 'moderation_action_log',
    indexes: [
        new ORM\Index(name: 'idx_modlog_action_created', columns: ['action_type', 'created_at']),
        new ORM\Index(name: 'idx_modlog_post',           columns: ['post_id']),
        new ORM\Index(name: 'idx_modlog_comment',        columns: ['comment_id']),
        new ORM\Index(name: 'idx_modlog_moderator',      columns: ['moderator_id']),
    ]
)]
class ModerationActionLog implements XorTargetInterface
{
    use XorTargetTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ======================================================
    // DONNÉES D'AUDIT
    // ======================================================

    /**
     * Type d'action effectuée (HIDE, RESTORE, DELETE…).
     */
    #[ORM\Column(name: 'action_type', enumType: ModerationActionType::class)]
    private ModerationActionType $actionType;

    /**
     * Statut de l'entité avant l'action.
     */
    #[ORM\Column(name: 'previous_status', length: 30, nullable: true)]
    private ?string $previousStatus = null;

    /**
     * Statut de l'entité après l'action.
     */
    #[ORM\Column(name: 'new_status', length: 30, nullable: true)]
    private ?string $newStatus = null;

    /**
     * Raison saisie par le modérateur (optionnelle).
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    /**
     * Contexte métier libre (JSON).
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = null;

    // ======================================================
    // DATE
    // ======================================================
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    // ======================================================
    // RELATIONS
    // ======================================================

    /**
     * Modérateur ou admin à l'origine de l'action.
     * Null = action automatique du système.
     */
    #[ORM\ManyToOne(fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $moderator = null;

    /**
     * Post concerné (null si c'est un commentaire).
     */
    #[ORM\ManyToOne(inversedBy: 'moderationLogs', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Post $post = null;

    /**
     * Commentaire concerné (null si c'est un post).
     */
    #[ORM\ManyToOne(inversedBy: 'moderationLogs', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Comment $comment = null;

    // ======================================================
    // LIFECYCLE
    // ======================================================
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
        $this->assertExactlyOneTarget();
    }

    // ======================================================
    // HELPERS
    // ======================================================

    public function setPreviousStatus(ContentStatus $status): static 
    { 
        $this->previousStatus = $status->value; 
        return $this; 
    }

    public function setNewStatus(ContentStatus $status): static 
    { 
        $this->newStatus = $status->value; 
        return $this; 
    }

    public function isForPost(): bool { return $this->post !== null; }
    public function isForComment(): bool { return $this->comment !== null; }
    public function isAutomaticAction(): bool { return $this->moderator === null; }

    // ======================================================
    // GETTERS & SETTERS 
    // ======================================================

    public function getId(): ?int { return $this->id; }

    public function getActionType(): ModerationActionType { return $this->actionType; }
    public function setActionType(ModerationActionType $actionType): static { $this->actionType = $actionType; return $this; }

    public function getPreviousStatus(): ?string { return $this->previousStatus; }
    public function getNewStatus(): ?string { return $this->newStatus; }

    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $this->reason = $reason; return $this; }

    public function getContext(): ?array { return $this->context; }
    public function setContext(?array $context): static { $this->context = $context; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getModerator(): ?User { return $this->moderator; }
    public function setModerator(?User $moderator): static { $this->moderator = $moderator; return $this; }

    public function getPost(): ?Post { return $this->post; }
    public function setPost(?Post $post): static { $this->post = $post; return $this; }

    public function getComment(): ?Comment { return $this->comment; }
    public function setComment(?Comment $comment): static { $this->comment = $comment; return $this; }
}