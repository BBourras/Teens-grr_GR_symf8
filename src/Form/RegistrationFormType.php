<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire d'inscription.
 *
 * Sécurité mot de passe :
 * - min: 8 caractères
 * - max: 4096 (protection contre attaques DoS)
 * - Assert\PasswordStrength niveau MEDIUM
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'adresse email ne peut pas être vide.'),
                    new Assert\Email(message: 'L\'adresse email {{ value }} n\'est pas valide.'),
                    new Assert\Length(max: 180, maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.'),
                ],
                'attr' => [
                    'placeholder'  => 'adresse.mail@exemple.fr',
                    'autocomplete' => 'email',
                ],
            ])

            ->add('username', TextType::class, [
                'label' => 'Pseudo',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le pseudo ne peut pas être vide.'),
                    new Assert\Length(
                        min: 3,
                        max: 50,
                        minMessage: 'Le pseudo doit faire au moins {{ limit }} caractères.',
                        maxMessage: 'Le pseudo ne peut pas dépasser {{ limit }} caractères.',
                    ),
                    new Assert\Regex(
                        pattern: '/^[a-zA-Z0-9_\-]+$/',
                        message: 'Le pseudo ne doit contenir que des lettres, chiffres, tirets et underscores.',
                    ),
                ],
                'attr' => [
                    'placeholder'  => 'MonPseudo-007',
                    'autocomplete' => 'username',
                ],
            ])

            ->add('plainPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'mapped'          => false,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'first_options'   => [
                    'label' => 'Mot de passe',
                    'attr'  => ['autocomplete' => 'new-password'],
                ],
                'second_options'  => [
                    'label' => 'Confirmer le mot de passe',
                    'attr'  => ['autocomplete' => 'new-password'],
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le mot de passe ne peut pas être vide.'),
                    new Assert\Length(
                        min: 8,
                        max: 4096,
                        minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.',
                    ),
                    new Assert\PasswordStrength(
                        minScore: Assert\PasswordStrength::STRENGTH_MEDIUM,
                        message: 'Le mot de passe est trop faible. Utilisez au moins 8 caractères avec lettres, chiffres ou symboles.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}