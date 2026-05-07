<?php

namespace App\Form;

use App\Entity\CandidateProfile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CandidateProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class)
            ->add('lastName', TextType::class)
            ->add('birthDate', BirthdayType::class, ['required' => false, 'widget' => 'single_text'])
            ->add('city', TextType::class)
            ->add('educationLevel', TextType::class)
            ->add('educationField', TextType::class, ['required' => false])
            ->add('skills')
            ->add('languages')
            ->add('drivingLicense', CheckboxType::class, ['required' => false])
            ->add('drivingLicenseCategory', TextType::class, ['required' => false])
            ->add('availability', TextType::class)
            ->add('salaryExpectation', IntegerType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CandidateProfile::class,
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }
}
