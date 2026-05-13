<?php

namespace App\Controller\Admin;

use App\Entity\Employer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class EmployerCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Employer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Employeur')
            ->setEntityLabelInPlural('Employeurs')
            ->setPaginatorUseOutputWalkers(true)
            ->setDefaultSort(['isValidated' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('user.email', 'Compte')->hideOnForm();
        yield TextField::new('companyName', 'Entreprise');
        yield TextField::new('nif')->hideOnIndex();
        yield TextField::new('sector', 'Secteur');
        yield TextField::new('companySize', 'Taille')->hideOnIndex();
        yield TextField::new('countryCode', 'Code pays')->hideOnIndex();
        yield TextField::new('countryName', 'Pays');
        yield ArrayField::new('cities', 'Villes');
        yield UrlField::new('logoUrl', 'Logo')->hideOnIndex();
        yield TextareaField::new('description')->hideOnIndex();
        yield UrlField::new('website', 'Site')->hideOnIndex();
        yield BooleanField::new('isValidated', 'Validé');
        yield BooleanField::new('isDeleted', 'Masqué');
        yield IntegerField::new('offersUsedThisMonth', 'Offres/mois')->hideOnForm();
        yield IntegerField::new('applicationsViewedThisMonth', 'Candidatures vues')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $validate = Action::new('validateEmployer', 'Valider')->linkToCrudAction('validateEmployer');
        $reject = Action::new('rejectEmployer', 'Masquer')->linkToCrudAction('rejectEmployer')->addCssClass('btn btn-danger');
        $batchReject = Action::new('batchRejectEmployers', 'Supprimer la sélection')
            ->linkToCrudAction('batchRejectEmployers')
            ->createAsBatchAction()
            ->addCssClass('btn btn-danger');

        return $actions
            ->disable(Action::DELETE)
            ->add(Crud::PAGE_INDEX, $batchReject)
            ->add(Crud::PAGE_INDEX, $validate)
            ->add(Crud::PAGE_INDEX, $reject)
            ->add(Crud::PAGE_DETAIL, $validate)
            ->add(Crud::PAGE_DETAIL, $reject);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $alias = $queryBuilder->getRootAliases()[0] ?? 'entity';

        return $queryBuilder
            ->andWhere(sprintf('%s.isDeleted = :deleted', $alias))
            ->setParameter('deleted', false);
    }

    public function validateEmployer(AdminContext $context, EntityManagerInterface $entityManager)
    {
        $employer = $context->getEntity()->getInstance();
        if ($employer instanceof Employer) {
            $employer->setIsValidated(true)->setIsDeleted(false);
            $entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
    }

    public function rejectEmployer(AdminContext $context, EntityManagerInterface $entityManager)
    {
        $employer = $context->getEntity()->getInstance();
        if ($employer instanceof Employer) {
            $employer->setIsValidated(false)->setIsDeleted(true);
            $employer->getUser()->setIsActive(false)->setIsDeleted(true);
            $entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
    }

    public function batchRejectEmployers(BatchActionDto $batchActionDto, EntityManagerInterface $entityManager)
    {
        $count = 0;

        foreach ($batchActionDto->getEntityIds() as $id) {
            $employer = $entityManager->find(Employer::class, $id);
            if (!$employer instanceof Employer) {
                continue;
            }

            $employer->setIsValidated(false)->setIsDeleted(true);
            $employer->getUser()->setIsActive(false)->setIsDeleted(true);
            ++$count;
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('%d employeur(s) supprimé(s) logiquement.', $count));

        return $this->redirect($batchActionDto->getReferrerUrl());
    }
}
