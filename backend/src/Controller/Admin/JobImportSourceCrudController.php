<?php

namespace App\Controller\Admin;

use App\Entity\JobImportSource;
use App\Service\ExternalJobImportService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class JobImportSourceCrudController extends AbstractCrudController
{
    public function __construct(private readonly ExternalJobImportService $importService)
    {
    }

    public static function getEntityFqcn(): string
    {
        return JobImportSource::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Source d’offres')
            ->setEntityLabelInPlural('Sources d’offres')
            ->showEntityActionsInlined()
            ->setDefaultSort(['enabled' => 'DESC', 'createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnIndex()->hideOnForm();
        yield TextField::new('name', 'Nom');
        yield ChoiceField::new('provider', 'Fournisseur')->setChoices([
            'JSON générique' => 'generic_json',
            'RapidAPI / JSearch' => 'rapidapi_jsearch',
        ]);
        yield UrlField::new('apiUrl', 'URL API')->hideOnIndex();
        yield TextField::new('rapidApiHost', 'RapidAPI Host')->hideOnIndex();
        yield TextField::new('apiKeyEnvName', 'Variable clé API')->hideOnIndex();
        yield ChoiceField::new('httpMethod', 'Méthode')->setChoices(['GET' => 'GET'])->hideOnIndex();
        yield TextareaField::new('headersJson', 'Headers JSON')->hideOnIndex();
        yield TextareaField::new('queryParamsJson', 'Paramètres JSON')->hideOnIndex();
        yield TextField::new('itemsPath', 'Chemin des offres')->hideOnIndex();
        yield TextareaField::new('fieldMappingJson', 'Mapping des champs JSON')->hideOnIndex();
        yield ArrayField::new('targetCountries', 'Pays ciblés')->hideOnIndex();
        yield ArrayField::new('targetLocations', 'Localités ciblées')->hideOnIndex();
        yield ArrayField::new('keywords', 'Mots-clés')->hideOnIndex();
        yield BooleanField::new('enabled', 'Active');
        yield BooleanField::new('autoPublish', 'Auto');
        yield TextField::new('importLink', 'Import')
            ->onlyOnIndex()
            ->renderAsHtml()
            ->formatValue(static function ($value, JobImportSource $source): string {
                return sprintf(
                    '<a class="btn btn-primary kj-import-direct" href="/admin/job-import-source/%s/import-now">Importer</a>',
                    (string) $source->getId()
                );
            });
        yield TextField::new('importStatus', 'Statut import')->onlyOnIndex();
        yield IntegerField::new('lastImportedCount', 'Importées')->hideOnForm();
        yield IntegerField::new('lastSkippedCount', 'Ignorées')->hideOnForm();
        yield IntegerField::new('maxItemsPerRun', 'Limite')->hideOnIndex();
        yield IntegerField::new('minReliabilityScore', 'Score min.')->hideOnIndex();
        yield TextareaField::new('lastError', 'Dernière erreur')->hideOnIndex()->hideOnForm();
        yield TextareaField::new('lastStatusMessage', 'Résumé du dernier import')->hideOnIndex()->hideOnForm();
        yield DateTimeField::new('lastRunAt', 'Dernier passage')->hideOnForm();
        yield DateTimeField::new('lastSuccessAt', 'Dernier succès')->hideOnForm();
        yield DateTimeField::new('createdAt', 'Créée le')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $importNow = Action::new('importNow', 'Importer', 'fa fa-cloud-arrow-down')
            ->linkToRoute('admin_job_import_source_import_now', static fn (JobImportSource $source): array => [
                'id' => (string) $source->getId(),
            ])
            ->displayAsButton()
            ->addCssClass('btn btn-primary kj-import-action');

        $importAll = Action::new('importAll', 'Importer les sources actives', 'fa fa-rotate')
            ->linkToRoute('admin_job_import_sources_import_all')
            ->createAsGlobalAction()
            ->displayAsButton()
            ->addCssClass('btn btn-primary');

        return $actions
            ->add(Crud::PAGE_INDEX, $importAll)
            ->add(Crud::PAGE_INDEX, $importNow)
            ->add(Crud::PAGE_DETAIL, $importNow)
            ->add(Crud::PAGE_EDIT, $importNow);
    }

    #[Route('/admin/job-import-source/{id}/import-now', name: 'admin_job_import_source_import_now', methods: ['GET', 'POST'])]
    public function importNow(JobImportSource $source, EntityManagerInterface $entityManager): RedirectResponse
    {
        try {
            $result = $this->importService->importSource($source);
            $this->addFlash('success', sprintf('%d offres importées depuis "%s", %d ignorées.', $result['imported'], $source->getName(), $result['skipped']));
        } catch (\Throwable $exception) {
            $source
                ->setLastRunAt(new \DateTimeImmutable())
                ->setLastError($exception->getMessage())
                ->setLastImportedCount(0)
                ->setLastSkippedCount(0)
                ->setLastStatusMessage('Import echoue : ' . $exception->getMessage());
            $entityManager->flush();
            $this->addFlash('danger', 'Import impossible : ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_job_import_source_index');
    }

    #[Route('/admin/job-import-sources/import-all', name: 'admin_job_import_sources_import_all', methods: ['GET', 'POST'])]
    public function importAll(): RedirectResponse
    {
        try {
            $summary = $this->importService->importAllEnabled();
            if (0 === $summary['sources']) {
                $this->addFlash('warning', 'Aucune source active à importer. Activez au moins une source ou utilisez le bouton "Importer cette source" sur une ligne.');

                return $this->redirectToRoute('admin_job_import_source_index');
            }

            foreach ($summary['errors'] as $error) {
                $this->addFlash('warning', $error);
            }

            $this->addFlash('success', sprintf(
                '%d source(s) active(s) traitée(s), %d offres importées, %d ignorées.',
                $summary['sources'],
                $summary['imported'],
                $summary['skipped']
            ));
        } catch (\Throwable $exception) {
            $this->addFlash('danger', 'Import impossible : ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_job_import_source_index');
    }
}
