<?php

declare(strict_types=1);

namespace App\Entity;

use App\Contract\ModeratableContentInterface;
use App\Entity\Trait\ContentStatusBehavior;
use App\Enum\ContentStatus;
use App\Repository\CommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(
    name: 'comment',
    indexes: [
        new ORM\Index(name: 'idx_comment_post',       columns: ['post_id']),
        new ORM\Index(name: 'idx_comment_author',     columns: ['author_id']),
        new ORM\Index(name: 'idx_comment_status',     columns: ['status']),
        new ORM\Index(name: 'idx_comment_created_at', columns: ['created_at']),
        new ORM\Index(name: 'idx_comment_post_status', columns: ['post_id', 'status']),
    ]
)]
class Comment implements ModeratableContentInterface
{
    use ContentStatusBehavior;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    private string $content;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'report_count', options: ['default' => 0])]
    private int $reportCount = 0;

    #[ORM\ManyToOne(inversedBy: 'comments', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\ManyToOne(inversedBy: 'comments', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Post $post;

    /** @var Collection<int, Report> */
    #[ORM\OneToMany(mappedBy: 'comment', targetEntity: Report::class, fetch: 'EXTRA_LAZY')]
    private Collection $reports;

    /** @var Collection<int, ModerationActionLog> */
    #[ORM\OneToMany(mappedBy: 'comment', targetEntity: ModerationActionLog::class, fetch: 'EXTRA_LAZY')]
    private Collection $moderationLogs;

    public function __construct()
    {
        $this->reports        = new ArrayCollection();
        $this->moderationLogs = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    // ======================================================
    // MÉTHODES MÉTIER
    // ======================================================

    public function incrementReportCount(int $by = 1): static
    {
        $this->reportCount += $by;
        return $this;
    }

    public function assignAuthor(User $author): static
    {
        $this->setAuthor($author);
        return $this;
    }

    public function assignPost(Post $post): static
    {
        $this->setPost($post);
        return $this;
    }

    // ======================================================
    // MÉTHODES DE L'INTERFACE ModeratableContentInterface
    // ======================================================

    public function getTargetType(): string
    {
        return 'comment';
    }

    public function getPost(): Post
    {
        return $this->post;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

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

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    protected function setAuthor(User $author): static { $this->author = $author; return $this; }
    protected function setPost(Post $post): static { $this->post = $post; return $this; }

    public function getReportCount(): int { return $this->reportCount; }

    /** @return Collection<int, Report> */
    public function getReports(): Collection { return $this->reports; }

    /** @return Collection<int, ModerationActionLog> */
    public function getModerationLogs(): Collection { return $this->moderationLogs; }
}