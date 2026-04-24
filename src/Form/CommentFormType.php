<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Comment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire de création / édition d'un commentaire.
 */
class CommentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Votre commentaire',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le commentaire ne peut pas être vide.'),
                    new Assert\Length(
                        min: 3,
                        max: 2000,
                        minMessage: 'Le commentaire doit faire au moins {{ limit }} caractères.',
                        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
                'attr' => [
                    'rows'        => 4,
                    'placeholder' => 'Votre réaction au message posté…',
                    'maxlength'   => 2000,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Comment::class,
        ]);
    }
}