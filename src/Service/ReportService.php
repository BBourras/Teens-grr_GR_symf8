<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\ModeratableContentInterface;
use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\Report;
use App\Entity\User;
use App\Enum\ReportReason;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion des signalements et de l’auto-masquage.
 */
class ReportService
{
    private const MIN_REPORT_THRESHOLD = 5;
    private const RATIO_THRESHOLD = 0.4;   // ratio signalements / score

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReportRepository $reportRepository,
        private readonly ModerationService $moderationService,
    ) {}

    public function reportPost(
        Post $post,
        User $user,
        ReportReason $reason,
        ?string $reasonDetail = null
    ): void {
        $this->reportContent($post, $user, $reason, $reasonDetail);
    }

    public function reportComment(
        Comment $comment,
        User $user,
        ReportReason $reason,
        ?string $reasonDetail = null
    ): void {
        $this->reportContent($comment, $user, $reason, $reasonDetail);
    }

    /**
     * Méthode privée commune pour signaler un Post ou un Comment.
     */
    private function reportContent(
        ModeratableContentInterface $content,
        User $user,
        ReportReason $reason,
        ?string $reasonDetail
    ): void {
        $this->em->wrapInTransaction(function () use ($content, $user, $reason, $reasonDetail) {

            // Protection contre doublon
            $alreadyReported = match (true) {
                $content instanceof Post =>
                    $this->reportRepository->hasAlreadyReportedPost($content, $user),

                $content instanceof Comment =>
                    $this->reportRepository->hasAlreadyReportedComment($content, $user),
            };

            if ($alreadyReported) {
                return; // déjà signalé par cet utilisateur
            }

            $report = (new Report())
                ->setUser($user)
                ->setReason($reason)
                ->setReasonDetail($reasonDetail);

            if ($content instanceof Post) {
                $report->setPost($content);
            } else {
                $report->setComment($content);
            }

            $content->incrementReportCount();
            $this->em->persist($report);

            $this->handleAutoHide($content);
        });
    }

    private function handleAutoHide(ModeratableContentInterface $entity): void
    {
        if (!$entity->isVisible()) {
            return;
        }

        $count = $entity->getReportCount();

        $score = $entity instanceof Post 
            ? max(1, $entity->getReactionScore() ?? 0) 
            : 1;

        if ($count >= self::MIN_REPORT_THRESHOLD && ($count / $score) >= self::RATIO_THRESHOLD) {
            $this->moderationService->autoHide($entity);
        }
    }
}