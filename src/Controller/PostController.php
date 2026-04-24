<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Enum\ReportReason;
use App\Form\CommentFormType;
use App\Form\PostFormType;
use App\Service\CommentService;
use App\Service\PostService;
use App\Service\VoteService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/posts')]
class PostController extends AbstractController
{
    public function __construct(
        private readonly PostService $postService,
        private readonly CommentService $commentService,
        private readonly VoteService $voteService,
        private readonly PaginatorInterface $paginator,
    ) {}

    // ======================================================
    // LISTES PUBLIQUES
    // ======================================================

    #[Route('/recent', name: 'post_recent', methods: ['GET'])]
    public function recent(Request $request): Response
    {
        $queryBuilder = $this->postService->getLatestPostsQueryBuilder();

        $pagination = $this->paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            15
        );

        // Calcul des scores détaillés pour les posts de la page courante
        $postScores = [];
        foreach ($pagination->getItems() as $post) {
            $postScores[$post->getId()] = $this->voteService->getScoreByTypeForPost($post);
        }

        return $this->render('post/recents.html.twig', [
            'pagination' => $pagination,
            'postScores' => $postScores,
        ]);
    }

    #[Route('/top', name: 'post_trending', methods: ['GET'])]
    public function top(Request $request): Response
    {
        $posts = $this->postService->getTrendingPosts(15);

        $userVotes = [];
        $postScores = [];

        foreach ($posts as $post) {
            $postScores[$post->getId()] = $this->voteService->getScoreByTypeForPost($post);

            if ($user = $this->getUser()) {
                $vote = $this->voteService->getUserVote($post, $user);
                $userVotes[$post->getId()] = $vote?->getType()->value;
            } elseif ($guestKey = $request->cookies->get('guest_vote_key')) {
                $vote = $this->voteService->getGuestVote($post, $guestKey);
                $userVotes[$post->getId()] = $vote?->getType()->value;
            }
        }

        return $this->render('post/trending.html.twig', [
            'posts'      => $posts,
            'userVotes'  => $userVotes,
            'postScores' => $postScores,
        ]);
    }

    #[Route('/legends', name: 'post_legend', methods: ['GET'])]
    public function legends(Request $request): Response
    {
        $posts = $this->postService->getLegendPosts(15);

        $userVotes = [];
        $postScores = [];

        foreach ($posts as $post) {
            $postScores[$post->getId()] = $this->voteService->getScoreByTypeForPost($post);

            if ($user = $this->getUser()) {
                $vote = $this->voteService->getUserVote($post, $user);
                $userVotes[$post->getId()] = $vote?->getType()->value;
            } elseif ($guestKey = $request->cookies->get('guest_vote_key')) {
                $vote = $this->voteService->getGuestVote($post, $guestKey);
                $userVotes[$post->getId()] = $vote?->getType()->value;
            }
        }

        return $this->render('post/legends.html.twig', [
            'posts'      => $posts,
            'userVotes'  => $userVotes,
            'postScores' => $postScores,
        ]);
    }

    // ======================================================
    // AFFICHAGE D'UN POST
    // ======================================================

    #[Route('/{id}', name: 'post_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Post $post, Request $request): Response
    {
        if (!$this->isGranted('POST_VIEW', $post)) {
            if ($post->isAutoModerated()) {
                $this->addFlash('warning', 'Ce post a été masqué automatiquement suite à plusieurs signalements.');
            } elseif ($post->isManuallyModerated()) {
                $this->addFlash('warning', 'Ce post a été masqué par un modérateur.');
            } elseif ($post->isDeleted()) {
                $this->addFlash('danger', 'Ce post a été supprimé.');
            } else {
                $this->addFlash('danger', 'Vous n\'avez pas les droits nécessaires pour consulter ce post.');
            }

            return $this->redirectToRoute('user_dashboard');
        }

        $user = $this->getUser();
        $comments = $this->commentService->getVisibleCommentsByPost($post);

        $commentForm = $user
            ? $this->createForm(CommentFormType::class, new Comment())->createView()
            : null;

        $userVote = $this->resolveUserVote($post, $request);

        return $this->render('post/show.html.twig', [
            'post'          => $post,
            'comments'      => $comments,
            'commentForm'   => $commentForm,
            'postScores'    => $this->voteService->getScoreByTypeForPost($post),
            'userVote'      => $userVote,
            'reportReasons' => ReportReason::cases(),
        ]);
    }

    // ======================================================
    // CRUD
    // ======================================================

    #[Route('/new', name: 'post_create', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ✅ On récupère le vrai post persisté
            $newPost = $this->postService->createPost(
                $post->getTitle(),
                $post->getContent(),
                $this->getUser()
            );

            $this->addFlash('success', 'Post créé avec succès.');

            return $this->redirectToRoute('post_show', [
                'id' => $newPost->getId()
            ]);
        }

        return $this->render('post/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'post_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Post $post, Request $request): Response
    {
        $this->denyAccessUnlessGranted('POST_EDIT', $post);

        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->postService->update($post);

            $this->addFlash('success', 'Post mis à jour.');

            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }

        return $this->render('post/edit.html.twig', [
            'form' => $form->createView(),
            'post' => $post,
        ]);
    }

    #[Route('/{id}/delete', name: 'post_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Post $post, Request $request): Response
    {
        $this->denyAccessUnlessGranted('POST_DELETE', $post);

        if (!$this->isCsrfTokenValid('delete_post_' . $post->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');

            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }

        $this->postService->deleteByAuthor($post, $this->getUser());

        $this->addFlash('success', 'Post supprimé.');

        return $this->redirectToRoute('post_recent');
    }

    // ======================================================
    // MÉTHODES PRIVÉES
    // ======================================================

    private function resolveUserVote(Post $post, Request $request): ?string
    {
        if ($user = $this->getUser()) {
            $vote = $this->voteService->getUserVote($post, $user);
            return $vote?->getType()->value;
        }

        if ($guestKey = $request->cookies->get('guest_vote_key')) {
            $vote = $this->voteService->getGuestVote($post, $guestKey);
            return $vote?->getType()->value;
        }

        return null;
    }
}