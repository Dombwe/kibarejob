<?php

namespace App\Form;

use App\Entity\Employer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmployerProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('companyName', TextType::class)
            ->add('nif', TextType::class, ['required' => false])
            ->add('sector', TextType::class)
            ->add('companySize', TextType::class, ['required' => false])
            ->add('countryCode', TextType::class)
            ->add('countryName', TextType::class)
            ->add('cities')
            ->add('description')
            ->add('website', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Employer::class,
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }
}
