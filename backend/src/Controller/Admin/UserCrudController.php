<?php

namespace App\Controller\Admin;

use App\Entity\User;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnIndex()->hideOnForm();
        yield TextField::new('firstName', 'Prénom');
        yield TextField::new('lastName', 'Nom');
        yield EmailField::new('email', 'Adresse mail');
        yield TelephoneField::new('phone', 'Téléphone')->hideOnIndex();
        yield ArrayField::new('roles', 'Rôles')->hideOnIndex();
        yield BooleanField::new('isEmailVerified', 'Email confirmé');
        yield BooleanField::new('isActive', 'Actif');
        yield BooleanField::new('isDeleted', 'Banni/Supprimé');
        yield IntegerField::new('profileCompletedPercent', 'Profil %')->hideOnIndex();
        yield DateTimeField::new('lastLogin', 'Dernière connexion')->hideOnIndex()->hideOnForm();
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $activate = Action::new('activate', 'Activer')->linkToCrudAction('activate');
        $deactivate = Action::new('deactivate', 'Désactiver')->linkToCrudAction('deactivate');
        $ban = Action::new('ban', 'Bannir')->linkToCrudAction('ban')->addCssClass('btn btn-danger');
        $batchBan = Action::new('batchBan', 'Supprimer la sélection')
            ->linkToCrudAction('batchBan')
            ->createAsBatchAction()
            ->addCssClass('btn btn-danger');

        return $actions
            ->disable(Action::DELETE)
            ->add(Crud::PAGE_INDEX, $batchBan)
            ->add(Crud::PAGE_INDEX, $activate)
            ->add(Crud::PAGE_INDEX, $deactivate)
            ->add(Crud::PAGE_INDEX, $ban)
            ->add(Crud::PAGE_DETAIL, $activate)
            ->add(Crud::PAGE_DETAIL, $deactivate)
            ->add(Crud::PAGE_DETAIL, $ban);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $alias = $queryBuilder->getRootAliases()[0] ?? 'entity';

        return $queryBuilder
            ->andWhere(sprintf('%s.isDeleted = :deleted', $alias))
            ->setParameter('deleted', false);
    }

    public function activate(AdminContext $context, EntityManagerInterface $entityManager)
    {
        $user = $context->getEntity()->getInstance();
        if ($user instanceof User) {
            $user->setIsActive(true)->setIsDeleted(false);
            $entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
    }

    public function deactivate(AdminContext $context, EntityManagerInterface $entityManager)
    {
        $user = $context->getEntity()->getInstance();
        if ($user instanceof User) {
            if ($this->isProtectedAdmin($user)) {
                $this->addFlash('warning', 'Le compte admin principal ne peut pas etre desactive.');

                return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
            }

            $user->setIsActive(false);
            $entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
    }

    public function ban(AdminContext $context, EntityManagerInterface $entityManager)
    {
        $user = $context->getEntity()->getInstance();
        if ($user instanceof User) {
            if ($this->isProtectedAdmin($user)) {
                $this->addFlash('warning', 'Le compte admin principal ne peut pas etre supprime.');

                return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
            }

            $user->setIsActive(false)->setIsDeleted(true);
            $entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
    }

    public function batchBan(BatchActionDto $batchActionDto, EntityManagerInterface $entityManager)
    {
        $count = 0;

        foreach ($batchActionDto->getEntityIds() as $id) {
            $user = $entityManager->find(User::class, $id);
            if (!$user instanceof User) {
                continue;
            }

            if ($this->isProtectedAdmin($user)) {
                continue;
            }

            $user->setIsActive(false)->setIsDeleted(true);
            ++$count;
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('%d utilisateur(s) supprimé(s) logiquement.', $count));

        return $this->redirect($batchActionDto->getReferrerUrl());
    }

    private function isProtectedAdmin(User $user): bool
    {
        return 'admin@kibarejob.test' === mb_strtolower($user->getEmail());
    }
}

