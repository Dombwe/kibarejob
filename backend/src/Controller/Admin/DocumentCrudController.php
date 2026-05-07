<?php

namespace App\Controller\Admin;

use App\Entity\CandidateDocument;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class DocumentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CandidateDocument::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Document')
            ->setEntityLabelInPlural('Documents')
            ->setDefaultSort(['isVerified' => 'ASC', 'uploadedAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('type');
        yield TextField::new('title', 'Titre');
        yield TextareaField::new('description')->hideOnIndex();
        yield TextField::new('issuingOrganization', 'Organisme')->hideOnIndex();
        yield DateField::new('issueDate', 'Date obtention')->hideOnIndex();
        yield DateField::new('expiryDate', 'Expiration')->hideOnIndex();
        yield TextField::new('documentNumber', 'Reference')->hideOnIndex();
        yield UrlField::new('fileUrl', 'Fichier');
        yield BooleanField::new('isVerified', 'Verifie');
        yield IntegerField::new('confidenceScore', 'Score');
        yield BooleanField::new('isPublic', 'Public');
        yield BooleanField::new('isPinned', 'Epingle');
        yield BooleanField::new('isDeleted', 'Masque');
        yield ArrayField::new('tags')->hideOnIndex();
        yield DateTimeField::new('uploadedAt')->hideOnForm();
        yield DateTimeField::new('lastVerifiedAt')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $validate = Action::new('validateDocument', 'Valider')->linkToCrudAction('validateDocument');
        $reject = Action::new('rejectDocument', 'Refuser')->linkToCrudAction('rejectDocument')->addCssClass('btn btn-danger');

        return $actions
            ->add(Crud::PAGE_INDEX, $validate)
            ->add(Crud::PAGE_INDEX, $reject)
            ->add(Crud::PAGE_DETAIL, $validate)
            ->add(Crud::PAGE_DETAIL, $reject);
    }

    public function validateDocument(AdminContext $context, EntityManagerInterface $entityManager)
    {
        $document = $context->getEntity()->getInstance();
        if ($document instanceof CandidateDocument) {
            $document
                ->setIsVerified(true)
                ->setConfidenceScore(100)
                ->setLastVerifiedAt(new \DateTimeImmutable())
                ->setIsDeleted(false);
            $entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
    }

    public function rejectDocument(AdminContext $context, EntityManagerInterface $entityManager)
    {
        $document = $context->getEntity()->getInstance();
        if ($document instanceof CandidateDocument) {
            $document
                ->setIsVerified(false)
                ->setConfidenceScore(0)
                ->setLastVerifiedAt(new \DateTimeImmutable())
                ->setIsDeleted(true);
            $entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
    }
}
