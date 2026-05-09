<?php

namespace App\Controller\Admin;

use App\Entity\Enum\JobOfferStatus;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
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
            ->setEntityLabelInPlural('Offres internes')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnIndex()->hideOnForm();
        yield TextField::new('title', 'Titre');
        yield TextareaField::new('description')->hideOnIndex();
        yield ArrayField::new('requiredSkills', 'Compétences')->hideOnIndex();
        yield TextField::new('requiredEducation', 'Niveau')->hideOnIndex();
        yield IntegerField::new('requiredExperienceYears', 'Expérience')->hideOnIndex();
        yield TextField::new('location', 'Lieu');
        yield IntegerField::new('salaryMin', 'Salaire min')->hideOnIndex();
        yield IntegerField::new('salaryMax', 'Salaire max')->hideOnIndex();
        yield BooleanField::new('isRemoteAllowed', 'Télétravail');
        yield DateField::new('deadline', 'Date limite');
        yield ChoiceField::new('status', 'Statut')->setChoices([
            'Active' => JobOfferStatus::Active,
            'Clôturée' => JobOfferStatus::Closed,
            'Brouillon' => JobOfferStatus::Draft,
        ]);
        yield BooleanField::new('isBoosted', 'Boost');
        yield BooleanField::new('isDeleted', 'Masquée');
        yield TextField::new('externalSourceName', 'Source externe')->hideOnIndex()->hideOnForm();
        yield IntegerField::new('reliabilityScore', 'Fiabilité')->hideOnIndex()->hideOnForm();
        yield TextField::new('externalUrl', 'Lien source')->hideOnIndex()->hideOnForm();
        yield EmailField::new('applicationEmail', 'Email candidature')->hideOnIndex()->hideOnForm();
        yield DateTimeField::new('createdAt', 'Créée le')->hideOnForm();
        yield DateTimeField::new('importedAt', 'Importée le')->hideOnIndex()->hideOnForm();
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $alias = $queryBuilder->getRootAliases()[0];

        return $queryBuilder
            ->andWhere(sprintf('%s.sourceType IS NULL OR %s.sourceType != :externalSourceType', $alias, $alias))
            ->setParameter('externalSourceType', 'external_api');
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

