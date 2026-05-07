<?php

namespace App\Controller\Admin;

use App\Entity\CandidateDocument;
use App\Entity\DocumentVerificationLog;
use App\Entity\Enum\VerificationAction;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Symfony\Component\HttpFoundation\Response;

class DocumentCrudController extends AbstractCrudController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public static function getEntityFqcn(): string
    {
        return CandidateDocument::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Document')
            ->setEntityLabelInPlural('Documents')
            ->setDefaultSort(['uploadedAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield ChoiceField::new('type');
        yield TextField::new('title', 'Titre');
        yield TextareaField::new('description')->hideOnIndex();
        yield TextField::new('issuingOrganization', 'Organisme')->hideOnIndex();
        yield DateField::new('issueDate')->hideOnIndex();
        yield DateField::new('expiryDate')->hideOnIndex();
        yield TextField::new('documentNumber')->hideOnIndex();
        yield UrlField::new('fileUrl', 'Fichier');
        yield BooleanField::new('isVerified', 'Verifie');
        yield IntegerField::new('confidenceScore', 'Confiance');
        yield BooleanField::new('isPublic', 'Public');
        yield BooleanField::new('isPinned', 'Epingle');
        yield ArrayField::new('tags')->hideOnIndex();
        yield DateTimeField::new('uploadedAt')->hideOnForm();
        yield DateTimeField::new('lastVerifiedAt')->hideOnForm();
        yield BooleanField::new('isDeleted', 'Supprime');
    }

    public function configureActions(Actions $actions): Actions
    {
        $validate = Action::new('validateDocument', 'Valider')->linkToCrudAction('validateDocument');
        $reject = Action::new('rejectDocument', 'Refuser')->linkToCrudAction('rejectDocument')->setCssClass('btn btn-danger');

        return $actions->add(Crud::PAGE_INDEX, $validate)->add(Crud::PAGE_INDEX, $reject);
    }

    public function validateDocument(AdminContext $context): Response
    {
        $document = $context->getEntity()->getInstance();
        if ($document instanceof CandidateDocument) {
            $document->setIsVerified(true)->setConfidenceScore(100)->setLastVerifiedAt(new \DateTimeImmutable());
            $this->log($document, VerificationAction::AdminValidated, null);
            $this->entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? '/admin');
    }

    public function rejectDocument(AdminContext $context): Response
    {
        $document = $context->getEntity()->getInstance();
        if ($document instanceof CandidateDocument) {
            $document->setIsVerified(false)->setIsPublic(false)->setLastVerifiedAt(new \DateTimeImmutable());
            $this->log($document, VerificationAction::AdminRejected, 'Refus admin depuis le backoffice.');
            $this->entityManager->flush();
        }

        return $this->redirect($context->getReferrer() ?? '/admin');
    }

    private function log(CandidateDocument $document, VerificationAction $action, ?string $reason): void
    {
        $log = (new DocumentVerificationLog())
            ->setDocument($document)
            ->setAiScore($document->getConfidenceScore())
            ->setAdminOverride(true)
            ->setAction($action)
            ->setReason($reason);

        $this->entityManager->persist($log);
    }
}
