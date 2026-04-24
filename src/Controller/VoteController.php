<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Post;
use App\Enum\VoteType;
use App\Service\VoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/posts/{id}/vote')]
class VoteController extends AbstractController
{
    private const GUEST_COOKIE_NAME = 'guest_vote_key';
    private const GUEST_COOKIE_TTL  = 365 * 24 * 3600; // 1 an

    public function __construct(
        private readonly VoteService $voteService,
    ) {}

    #[Route('', name: 'vote_post', methods: ['POST'])]
    public function vote(Post $post, Request $request): Response
    {
        $voteType = VoteType::tryFrom($request->request->get('type'));

        if ($voteType === null) {
            $this->addFlash('error', 'Type de vote invalide.');
            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }

        $user = $this->getUser();
        $guestKey = null;

        if (!$user) {
            $guestKey = $request->cookies->get(self::GUEST_COOKIE_NAME) ?? Uuid::v4()->toRfc4122();
        }

        try {
            $this->voteService->vote(
                post:       $post,
                user:       $user,
                type:       $voteType,
                guestKey:   $guestKey,
                guestIpRaw: $request->getClientIp(),
            );

            $this->addFlash('success', 'Votre vote a été enregistré !');
        } catch (TooManyRequestsHttpException $e) {
            $this->addFlash('error', 'Vous avez voté trop vite. Attendez un peu avant de réessayer.');
        } catch (\LogicException $e) {
            $this->addFlash('error', 'Erreur lors du vote : ' . $e->getMessage());
        }

        $response = $this->redirectToRoute('post_show', ['id' => $post->getId()]);

        // Pose/renouvelle le cookie pour les invités
        if (!$user && $guestKey) {
            $response->headers->setCookie(
                Cookie::create(self::GUEST_COOKIE_NAME)
                    ->withValue($guestKey)
                    ->withExpires(time() + self::GUEST_COOKIE_TTL)
                    ->withHttpOnly(true)
                    ->withSameSite(Cookie::SAMESITE_LAX)
            );
        }

        return $response;
    }
}