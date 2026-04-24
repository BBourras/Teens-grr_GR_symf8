<?php

declare(strict_types=1);

namespace App\Entity;

use App\Contract\ModeratableContentInterface;
use App\Entity\Trait\ContentStatusBehavior;
use App\Enum\ContentStatus;
use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité représentant un Post publié.
 */
#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(
    name: 'post',
    indexes: [
        new ORM\Index(name: 'idx_post_status',         columns: ['status']),
        new ORM\Index(name: 'idx_post_created_at',     columns: ['created_at']),
        new ORM\Index(name: 'idx_post_status_created', columns: ['status', 'created_at']),
        new ORM\Index(name: 'idx_post_author',         columns: ['author_id']),
        new ORM\Index(name: 'idx_post_reaction_score', columns: ['reaction_score']),
    ]
)]
class Post implements ModeratableContentInterface
{
    use ContentStatusBehavior;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'comment_count', options: ['default' => 0])]
    private int $commentCount = 0;

    #[ORM\Column(name: 'report_count', options: ['default' => 0])]
    private int $reportCount = 0;

    #[ORM\Column(name: 'reaction_score', options: ['default' => 0])]
    private int $reactionScore = 0;

    #[ORM\ManyToOne(inversedBy: 'posts', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: Comment::class, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $comments;

    /** @var Collection<int, Vote> */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: Vote::class, fetch: 'EXTRA_LAZY')]
    private Collection $votes;

    /** @var Collection<int, Report> */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: Report::class, fetch: 'EXTRA_LAZY')]
    private Collection $reports;

    /** @var Collection<int, ModerationActionLog> */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: ModerationActionLog::class, fetch: 'EXTRA_LAZY')]
    private Collection $moderationLogs;

    public function __construct()
    {
        $this->comments       = new ArrayCollection();
        $this->votes          = new ArrayCollection();
        $this->reports        = new ArrayCollection();
        $this->moderationLogs = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    // ======================================================
    // MÉTHODES MÉTIER — compteurs
    // ======================================================

    public function incrementCommentCount(int $by = 1): static
    {
        $this->commentCount += $by;
        return $this;
    }

    public function decrementCommentCount(int $by = 1): static
    {
        $this->commentCount = max(0, $this->commentCount - $by);
        return $this;
    }

    public function incrementReportCount(int $by = 1): static
    {
        $this->reportCount += $by;
        return $this;
    }

    public function decrementReportCount(int $by = 1): static
    {
        $this->reportCount = max(0, $this->reportCount - $by);
        return $this;
    }

    public function incrementReactionScore(int $by = 1): static
    {
        $this->reactionScore += $by;
        return $this;
    }

    public function decrementReactionScore(int $by = 1): static
    {
        $this->reactionScore = max(0, $this->reactionScore - $by);
        return $this;
    }

    // ======================================================
    // HELPERS MÉTIER
    // ======================================================

    public function assignAuthor(User $author): static
    {
        $this->setAuthor($author);
        return $this;
    }

    // ======================================================
    // MÉTHODES DE L'INTERFACE ModeratableContentInterface
    // ======================================================

    public function getTargetType(): string
    {
        return 'post';
    }

    public function getPost(): Post
    {
        return $this;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // Délégation au trait ContentStatusBehavior
    public function getStatusEnum(): ContentStatus
    {
        return $this->status;
    }

    public function setStatus(ContentStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    // ======================================================
    // GETTERS & SETTERS
    // ======================================================

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    protected function setAuthor(User $author): static { $this->author = $author; return $this; }

    public function getCommentCount(): int { return $this->commentCount; }
    public function getReportCount(): int { return $this->reportCount; }
    public function getReactionScore(): int { return $this->reactionScore; }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection { return $this->comments; }

    /** @return Collection<int, Vote> */
    public function getVotes(): Collection { return $this->votes; }

    /** @return Collection<int, Report> */
    public function getReports(): Collection { return $this->reports; }

    /** @return Collection<int, ModerationActionLog> */
    public function getModerationLogs(): Collection { return $this->moderationLogs; }

    public function getExcerpt(int $length = 200): string
    {
        $text = strip_tags($this->content);
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . '…';
    }
}