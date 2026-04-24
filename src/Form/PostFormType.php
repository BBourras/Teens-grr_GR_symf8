<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Post;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire de création / édition d'un Post.
 */
class PostFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le titre ne peut pas être vide.'),
                    new Assert\Length(
                        min: 5,
                        max: 255,
                        minMessage: 'Le titre doit faire au moins {{ limit }} caractères.',
                        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
                'attr' => [
                    'placeholder' => 'Un titre … ironique, c\'est mieux !',
                    'maxlength'   => 255,
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le contenu ne peut pas être vide.'),
                    new Assert\Length(
                        min: 10,
                        max: 5000,
                        minMessage: 'Le message doit faire au moins {{ limit }} caractères.',
                        maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
                'attr' => [
                    'rows'        => 8,
                    'placeholder' => 'Votre anecdote … drôle, c\'est mieux !',
                    'maxlength'   => 5000,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
        ]);
    }
}