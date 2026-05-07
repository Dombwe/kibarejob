<?php

namespace App\Controller\Admin;

use App\Entity\Enum\JobOfferStatus;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;

class JobOfferCrudController extends AbstractCrudController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

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
        yield TextField::new('title', 'Titre');
        yield TextareaField::new('description')->hideOnIndex();
        yield ArrayField::new('requiredSkills', 'Competences');
        yield TextField::new('requiredEducation', 'Niveau');
        yield IntegerField::new('requiredExperienceYears', 'Experience');
        yield ChoiceField::new('contractType');
        yield TextField::new('location', 'Lieu');
        yield IntegerField::new('salaryMin')->hideOnIndex();
        yield IntegerField::new('salaryMax')->hideOnIndex();
        yield BooleanField::new('isRemoteAllowed', 'Remote');
        yield BooleanField::new('isBoosted', 'Boostee');
        yield DateField::new('deadline', 'Deadline');
        yield ChoiceField::new('status');
        yield BooleanField::new('isDeleted', 'Supprimee');
        yield IntegerField::new('viewsCount', 'Vues')->hideOnForm();
        yield IntegerField::new('applicationsCount', 'Candidatures')->hideOnForm();
        yield DateTimeField::new('createdAt')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $approve = Action::new('approveOffer', 'Publier')->linkToCrudAction('approveOffer');
        $close = Action::new('closeOffer', 'Fermer')->linkToCrudAction('closeOffer');
        $hide = Action::new('hideOffer', 'Masquer')->linkToCrudAction('hideOffer')->setCssClass('btn btn-danger');

        return $actions
            ->add(Crud::PAGE_INDEX, $approve)
            ->add(Crud::PAGE_INDEX, $close)
            ->add(Crud::PAGE_INDEX, $hide);
    }

    public function approveOffer(AdminContext $context): Response
    {
        $offer = $context->getEntity()->getInstance();
        if ($offer instanceof JobOffer) {
            $offer->setStatus(JobOfferStatus::Active)->setIsDeleted(false);
            $this->entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? '/admin');
    }

    public function closeOffer(AdminContext $context): Response
    {
        $offer = $context->getEntity()->getInstance();
        if ($offer instanceof JobOffer) {
            $offer->setStatus(JobOfferStatus::Closed);
            $this->entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? '/admin');
    }

    public function hideOffer(AdminContext $context): Response
    {
        $offer = $context->getEntity()->getInstance();
        if ($offer instanceof JobOffer) {
            $offer->setIsDeleted(true)->setStatus(JobOfferStatus::Closed);
            $this->entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? '/admin');
    }
}
