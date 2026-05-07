<?php

namespace App\Controller\Admin;

use App\Entity\Enum\JobOfferStatus;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class JobOfferCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return JobOffer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Offre')
            ->setEntityLabelInPlural('Offres')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('title', 'Titre');
        yield TextareaField::new('description')->hideOnIndex();
        yield ArrayField::new('requiredSkills', 'Competences');
        yield TextField::new('requiredEducation', 'Niveau');
        yield IntegerField::new('requiredExperienceYears', 'Experience');
        yield TextField::new('location', 'Lieu');
        yield IntegerField::new('salaryMin', 'Salaire min')->hideOnIndex();
        yield IntegerField::new('salaryMax', 'Salaire max')->hideOnIndex();
        yield BooleanField::new('isRemoteAllowed', 'Remote');
        yield DateField::new('deadline', 'Deadline');
        yield ChoiceField::new('status')->setChoices([
            'Active' => JobOfferStatus::Active,
            'Closed' => JobOfferStatus::Closed,
            'Draft' => JobOfferStatus::Draft,
        ]);
        yield BooleanField::new('isBoosted', 'Boost');
        yield BooleanField::new('isDeleted', 'Masquee');
        yield DateTimeField::new('createdAt')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $approve = Action::new('approveOffer', 'Activer')->linkToCrudAction('approveOffer');
        $close = Action::new('closeOffer', 'Fermer')->linkToCrudAction('closeOffer');
        $hide = Action::new('hideOffer', 'Masquer')->linkToCrudAction('hideOffer')->addCssClass('btn btn-danger');

        return $actions
            ->add(Crud::PAGE_INDEX, $approve)
            ->add(Crud::PAGE_INDEX, $close)
            ->add(Crud::PAGE_INDEX, $hide)
            ->add(Crud::PAGE_DETAIL, $approve)
            ->add(Crud::PAGE_DETAIL, $close)
            ->add(Crud::PAGE_DETAIL, $hide);
    }

    public function approveOffer(AdminContext $context, EntityManagerInterface $entityManager)
    {
        $offer = $context->getEntity()->getInstance();
        if ($offer instanceof JobOffer) {
            $offer->setStatus(JobOfferStatus::Active)->setIsDeleted(false);
            $entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
    }

    public function closeOffer(AdminContext $context, EntityManagerInterface $entityManager)
    {
        $offer = $context->getEntity()->getInstance();
        if ($offer instanceof JobOffer) {
            $offer->setStatus(JobOfferStatus::Closed);
            $entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
    }

    public function hideOffer(AdminContext $context, EntityManagerInterface $entityManager)
    {
        $offer = $context->getEntity()->getInstance();
        if ($offer instanceof JobOffer) {
            $offer->setIsDeleted(true)->setStatus(JobOfferStatus::Closed);
            $entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
    }
}
