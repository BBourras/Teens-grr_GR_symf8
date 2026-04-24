<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité représentant un utilisateur inscrit sur la plateforme.
 * ---------------------------------------------------
 * - Email unique, normalisé en minuscules
 * - Username unique (affiché publiquement)
 * - ROLE_USER garanti à chaque appel à getRoles()
 * - Suppression en cascade sur posts, commentaires,
 *   votes et signalements liés
 *
 * Validation Symfony :
 * ---------------------------------------------------
 * - #[UniqueEntity] déclenche la vérification d'unicité AVANT le flush(), côté validation de formulaire.
 *   Le catch UniqueConstraintViolationException dans RegistrationController est le filet de sécurité
 *   contre les race conditions concurrentes.
 *
 * Optimisations :
 * ---------------------------------------------------
 * - EXTRA_LAZY sur toutes les collections (évite le chargement massif pour les utilisateurs actifs)
 * - Index sur created_at pour les stats admin
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\Table(
    name: 'user',
    indexes: [
        new ORM\Index(name: 'idx_user_created_at', columns: ['created_at']),
    ]
)]
#[UniqueEntity(fields: ['email'],    message: 'Cet email est déjà utilisé.')]
#[UniqueEntity(fields: ['username'], message: 'Ce pseudo est déjà pris.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ======================================================
    // DONNÉES COMPTE
    // ======================================================

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email;

    /**
     * Le pseudo est affiché publiquement.
     * Limité aux caractères alphanumériques, tirets et underscores (évite inject° HTML et usurpat° visuelles)
     */
    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_\-]+$/',
        message: 'Le pseudo ne peut contenir que des lettres, chiffres, tirets et underscores.',
    )]
    private string $username;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    // ======================================================
    // DATES
    // ======================================================

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    // ======================================================
    // RELATIONS
    // ======================================================

    /**
     * Posts publiés par cet utilisateur.
     *
     * EXTRA_LAZY : count() et contains() sans charger toute la collection (crucial pour les gros comptes).
     */
    #[ORM\OneToMany(
        mappedBy: 'author',
        targetEntity: Post::class,
        fetch: 'EXTRA_LAZY'
    )]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $posts;

    /**
     * Commentaires rédigés par cet utilisateur.
     */
    #[ORM\OneToMany(
        mappedBy: 'author',
        targetEntity: Comment::class,
        fetch: 'EXTRA_LAZY'
    )]
    private Collection $comments;

    /**
     * Votes/réactions posés par cet utilisateur.
     */
    #[ORM\OneToMany(
        mappedBy: 'user',
        targetEntity: Vote::class,
        fetch: 'EXTRA_LAZY'
    )]
    private Collection $votes;

    /**
     * Signalements émis par cet utilisateur.
     */
    #[ORM\OneToMany(
        mappedBy: 'user',
        targetEntity: Report::class,
        fetch: 'EXTRA_LAZY'
    )]
    private Collection $reports;

    // ======================================================
    // CONSTRUCTEUR
    // ======================================================

    public function __construct()
    {
        $this->posts    = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->votes    = new ArrayCollection();
        $this->reports  = new ArrayCollection();
    }

    // ======================================================
    // LIFECYCLE
    // ======================================================

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    // ======================================================
    // INTERFACE UserInterface
    // ======================================================

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles   = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique($roles));

        return $this;
    }

    public function eraseCredentials(): void
    {
        // Pas de données sensibles temporaires à effacer pour l'instant.
    }

    // ======================================================
    // GETTERS & SETTERS
    // ======================================================

    public function getId(): ?int { return $this->id; }

    public function getEmail(): string { return $this->email; }

    /**
     * Normalise l'email en minuscules à la saisie.
     */
    public function setEmail(string $email): static
    {
        $this->email = strtolower(trim($email));
        return $this;
    }

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): static { $this->username = $username; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, Post> */
    public function getPosts(): Collection { return $this->posts; }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection { return $this->comments; }

    /** @return Collection<int, Vote> */
    public function getVotes(): Collection { return $this->votes; }

    /** @return Collection<int, Report> */
    public function getReports(): Collection { return $this->reports; }

    /**
     * Helper pour vérifier les droits de modération (préparation future).
     */
    public function isModerator(): bool
    {
        return in_array('ROLE_MODERATOR', $this->roles, true) || in_array('ROLE_ADMIN', $this->roles, true);
    }
}