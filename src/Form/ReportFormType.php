<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\ReportReason;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ReportFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reason', EnumType::class, [
             'class' => ReportReason::class,
    'label' => 'Raison du signalement',
    'choice_label' => fn(ReportReason $r) => $r->label(),
    'placeholder' => 'Choisissez une raison...',
    'required' => true,
    'attr' => [
        'class' => 'form-select',
    ],
            ])

            ->add('reason_detail', TextareaType::class, [
                'label'       => 'Précisions supplémentaires (facultatif)',
                'required'    => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows'  => 6,
                    'placeholder' => 'Décrivez brièvement pourquoi vous signalez ce contenu (ex: insultes, harcèlement, contenu choquant...)',
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'Les précisions ne peuvent pas dépasser {{ limit }} caractères.',
                    ]),
                    new Assert\NotNull(message: 'Vous devez choisir une raison.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Pas de data_class car on n'utilise pas directement une entité Report dans le formulaire
            'data_class' => null,
        ]);
    }
}
