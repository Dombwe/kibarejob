<?php

namespace App\Controller\Admin;

use App\Entity\Report;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bundle\SecurityBundle\Security;

class ReportCrudController extends AbstractCrudController
{
    public function __construct(private readonly Security $security)
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
            ->setDefaultSort(['status' => 'ASC', 'createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('reporter')->hideOnIndex();
        yield TextField::new('targetType', 'Type');
        yield TextField::new('targetId', 'Cible');
        yield TextField::new('reason', 'Raison');
        yield TextareaField::new('description')->hideOnIndex();
        yield ChoiceField::new('status')->setChoices([
            'Pending' => 'pending',
            'Reviewing' => 'reviewing',
            'Resolved' => 'resolved',
            'Rejected' => 'rejected',
        ]);
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield DateTimeField::new('resolvedAt')->hideOnForm();
        yield AssociationField::new('resolvedBy')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $review = Action::new('markReviewing', 'En revue')->linkToCrudAction('markReviewing');
        $resolve = Action::new('resolveReport', 'Resoudre')->linkToCrudAction('resolveReport');
        $reject = Action::new('rejectReport', 'Rejeter')->linkToCrudAction('rejectReport');

        return $actions
            ->add(Crud::PAGE_INDEX, $review)
            ->add(Crud::PAGE_INDEX, $resolve)
            ->add(Crud::PAGE_INDEX, $reject)
            ->add(Crud::PAGE_DETAIL, $review)
            ->add(Crud::PAGE_DETAIL, $resolve)
            ->add(Crud::PAGE_DETAIL, $reject);
    }

    public function markReviewing(AdminContext $context, EntityManagerInterface $entityManager)
    {
        return $this->setStatus($context, $entityManager, 'reviewing');
    }

    public function resolveReport(AdminContext $context, EntityManagerInterface $entityManager)
    {
        return $this->setStatus($context, $entityManager, 'resolved');
    }

    public function rejectReport(AdminContext $context, EntityManagerInterface $entityManager)
    {
        return $this->setStatus($context, $entityManager, 'rejected');
    }

    private function setStatus(AdminContext $context, EntityManagerInterface $entityManager, string $status)
    {
        $report = $context->getEntity()->getInstance();
        if ($report instanceof Report) {
            $report->setStatus($status);
            if (in_array($status, ['resolved', 'rejected'], true)) {
                $report->setResolvedAt(new \DateTimeImmutable());
                $user = $this->security->getUser();
                $report->setResolvedBy($user instanceof User ? $user : null);
            }
            $entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
    }
}
