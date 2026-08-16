<?php

namespace App\Form;

use App\Entity\Shift;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class VacationRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startTime', DateType::class, [
                'label'       => 'Start Date',
                'widget'      => 'single_text',
                'input'       => 'datetime_immutable',
                'constraints' => [new NotBlank()],
            ])
            ->add('endTime', DateType::class, [
                'label'       => 'End Date',
                'widget'      => 'single_text',
                'input'       => 'datetime_immutable',
                'constraints' => [
                    new NotBlank(),
                    new GreaterThanOrEqual(
                        propertyPath: 'parent.all[startTime].data',
                        message: 'End date cannot be before the start date.',
                    ),
                ],
            ])
            ->add('note', TextareaType::class, [
                'label'    => 'Note / Reason',
                'required' => false,
                'attr'     => ['rows' => 3],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Submit Request']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Shift::class]);
    }
}
