<?php

namespace App\Controller\Admin;

use App\Entity\Enum\JobOfferStatus;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ExternalJobOfferCrudController extends JobOfferCrudController
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
            ->setEntityLabelInSingular('Offre externe')
            ->setEntityLabelInPlural('Offres externes')
            ->setDefaultSort(['importedAt' => 'DESC', 'createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnIndex()->hideOnForm();
        yield TextField::new('title', 'Titre');
        yield TextField::new('location', 'Lieu');
        yield TextField::new('externalSourceName', 'Source')->hideOnForm();
        yield IntegerField::new('reliabilityScore', 'Fiabilite')->hideOnForm();
        yield TextField::new('externalUrl', 'Lien candidature')->hideOnIndex()->hideOnForm();
        yield EmailField::new('applicationEmail', 'Email candidature')->hideOnForm();
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield BooleanField::new('isRemoteAllowed', 'Teletravail')->hideOnIndex();
        yield ChoiceField::new('status', 'Statut')->setChoices([
            'Active' => JobOfferStatus::Active,
            'Cloturee' => JobOfferStatus::Closed,
            'Brouillon' => JobOfferStatus::Draft,
        ]);
        yield BooleanField::new('isDeleted', 'Masquee');
        yield DateTimeField::new('importedAt', 'Importee le')->hideOnForm();
        yield DateTimeField::new('createdAt', 'Creee le')->hideOnIndex()->hideOnForm();
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return $this->entityManager
            ->getRepository(JobOffer::class)
            ->createQueryBuilder('entity')
            ->andWhere('entity.sourceType = :externalSourceType')
            ->setParameter('externalSourceType', 'external_api')
            ->orderBy('entity.importedAt', 'DESC')
            ->addOrderBy('entity.createdAt', 'DESC');
    }
}
