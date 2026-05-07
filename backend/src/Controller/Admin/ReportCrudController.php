<?php

namespace App\Controller\Admin;

use App\Entity\Report;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;

class ReportCrudController extends AbstractCrudController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Report::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Signalement')
            ->setEntityLabelInPlural('Signalements')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('reporter')->hideOnIndex();
        yield ChoiceField::new('targetType', 'Cible')->setChoices([
            'Offre' => 'job_offer',
            'Compte' => 'user',
            'Employeur' => 'employer',
            'Document' => 'document',
        ]);
        yield TextField::new('targetId', 'ID cible');
        yield TextareaField::new('reason', 'Raison');
        yield ChoiceField::new('status', 'Statut')->setChoices([
            'En attente' => 'pending',
            'Resolue' => 'resolved',
            'Rejetee' => 'rejected',
        ]);
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield DateTimeField::new('resolvedAt')->hideOnForm();
        yield BooleanField::new('isDeleted', 'Supprime');
    }

    public function configureActions(Actions $actions): Actions
    {
        $resolve = Action::new('resolveReport', 'Resoudre')->linkToCrudAction('resolveReport');
        $reject = Action::new('rejectReport', 'Rejeter')->linkToCrudAction('rejectReport');

        return $actions->add(Crud::PAGE_INDEX, $resolve)->add(Crud::PAGE_INDEX, $reject);
    }

    public function resolveReport(AdminContext $context): Response
    {
        $report = $context->getEntity()->getInstance();
        if ($report instanceof Report) {
            $report->setStatus('resolved')->setResolvedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? '/admin');
    }

    public function rejectReport(AdminContext $context): Response
    {
        $report = $context->getEntity()->getInstance();
        if ($report instanceof Report) {
            $report->setStatus('rejected')->setResolvedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? '/admin');
    }
}
