<?php

namespace App\Controller\Admin;

use App\Entity\Employer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Symfony\Component\HttpFoundation\Response;

class EmployerCrudController extends AbstractCrudController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Employer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Employeur')->setEntityLabelInPlural('Employeurs');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('companyName', 'Entreprise');
        yield TextField::new('nif')->hideOnIndex();
        yield TextField::new('sector', 'Secteur');
        yield TextField::new('companySize', 'Taille')->hideOnIndex();
        yield ArrayField::new('cities', 'Villes');
        yield UrlField::new('logoUrl', 'Logo')->hideOnIndex();
        yield TextareaField::new('description')->hideOnIndex();
        yield UrlField::new('website', 'Site web')->hideOnIndex();
        yield ChoiceField::new('subscriptionTier')->hideOnForm();
        yield BooleanField::new('isValidated', 'Valide');
        yield BooleanField::new('isDeleted', 'Supprime');
        yield IntegerField::new('offersUsedThisMonth', 'Offres/mois');
        yield IntegerField::new('applicationsViewedThisMonth', 'Vues/mois')->hideOnIndex();
    }

    public function configureActions(Actions $actions): Actions
    {
        $validate = Action::new('validateEmployer', 'Valider')->linkToCrudAction('validateEmployer');
        $hide = Action::new('hideEmployer', 'Masquer')->linkToCrudAction('hideEmployer')->setCssClass('btn btn-danger');

        return $actions->add(Crud::PAGE_INDEX, $validate)->add(Crud::PAGE_INDEX, $hide);
    }

    public function validateEmployer(AdminContext $context): Response
    {
        $employer = $context->getEntity()->getInstance();
        if ($employer instanceof Employer) {
            $employer->setIsValidated(true)->setIsDeleted(false);
            $this->entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? '/admin');
    }

    public function hideEmployer(AdminContext $context): Response
    {
        $employer = $context->getEntity()->getInstance();
        if ($employer instanceof Employer) {
            $employer->setIsDeleted(true);
            $this->entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? '/admin');
    }
}
