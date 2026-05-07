<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use Symfony\Component\HttpFoundation\Response;

class UserCrudController extends AbstractCrudController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

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
        yield EmailField::new('email');
        yield TelephoneField::new('phone')->hideOnIndex();
        yield ArrayField::new('roles');
        yield BooleanField::new('isActive', 'Actif');
        yield BooleanField::new('isDeleted', 'Banni/Supprime');
        yield IntegerField::new('profileCompletedPercent', 'Profil %');
        yield ChoiceField::new('subscriptionTier')->hideOnForm();
        yield IntegerField::new('swipesUsedToday', 'Swipes aujourd hui');
        yield DateTimeField::new('lastLogin')->hideOnForm();
        yield DateTimeField::new('createdAt')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $activate = Action::new('activate', 'Activer')->linkToCrudAction('activate');
        $deactivate = Action::new('deactivate', 'Desactiver')->linkToCrudAction('deactivate');
        $ban = Action::new('ban', 'Bannir')->linkToCrudAction('ban')->setCssClass('btn btn-danger');

        return $actions
            ->add(Crud::PAGE_INDEX, $activate)
            ->add(Crud::PAGE_INDEX, $deactivate)
            ->add(Crud::PAGE_INDEX, $ban)
            ->add(Crud::PAGE_DETAIL, $activate)
            ->add(Crud::PAGE_DETAIL, $deactivate)
            ->add(Crud::PAGE_DETAIL, $ban);
    }

    public function activate(AdminContext $context): Response
    {
        $user = $context->getEntity()->getInstance();
        if ($user instanceof User) {
            $user->setIsActive(true)->setIsDeleted(false);
            $this->entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? '/admin');
    }

    public function deactivate(AdminContext $context): Response
    {
        $user = $context->getEntity()->getInstance();
        if ($user instanceof User) {
            $user->setIsActive(false);
            $this->entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? '/admin');
    }

    public function ban(AdminContext $context): Response
    {
        $user = $context->getEntity()->getInstance();
        if ($user instanceof User) {
            $user->setIsActive(false)->setIsDeleted(true);
            $this->entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? '/admin');
    }
}
