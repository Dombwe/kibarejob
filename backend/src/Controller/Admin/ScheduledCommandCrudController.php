<?php

namespace App\Controller\Admin;

use App\Entity\ScheduledCommand;
use App\Service\ScheduledCommandRunner;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class ScheduledCommandCrudController extends AbstractCrudController
{
    public function __construct(private readonly ScheduledCommandRunner $runner)
    {
    }

    public static function getEntityFqcn(): string
    {
        return ScheduledCommand::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Commande planifiee')
            ->setEntityLabelInPlural('Commandes / Cron')
            ->showEntityActionsInlined()
            ->setDefaultSort(['enabled' => 'DESC', 'createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnIndex()->hideOnForm();
        yield TextField::new('name', 'Nom');
        yield TextField::new('commandName', 'Commande Symfony')
            ->setHelp('Exemples : jobs:import-external, matching:precompute-scores, document:cleanup, subscription:reset-daily, subscription:reset-monthly.');
        yield TextareaField::new('argumentsJson', 'Arguments JSON')->hideOnIndex();
        yield TextareaField::new('optionsJson', 'Options JSON')->hideOnIndex();
        yield ChoiceField::new('frequency', 'Frequence')->setChoices([
            'Toutes les 15 minutes' => ScheduledCommand::FREQUENCY_EVERY_15_MINUTES,
            'Toutes les heures' => ScheduledCommand::FREQUENCY_HOURLY,
            'Toutes les 6 heures' => ScheduledCommand::FREQUENCY_EVERY_6_HOURS,
            'Chaque jour' => ScheduledCommand::FREQUENCY_DAILY,
            'Chaque semaine' => ScheduledCommand::FREQUENCY_WEEKLY,
            'Chaque mois' => ScheduledCommand::FREQUENCY_MONTHLY,
            'Cron personnalise' => ScheduledCommand::FREQUENCY_CUSTOM_CRON,
        ]);
        yield TextField::new('frequencyLabel', 'Rythme')->onlyOnIndex();
        yield TextField::new('customCronExpression', 'Expression cron')->hideOnIndex();
        yield TimeField::new('preferredTime', 'Heure preferee')->hideOnIndex();
        yield BooleanField::new('enabled', 'Active');
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield TextField::new('statusLabel', 'Statut')->onlyOnIndex();
        yield TextField::new('runLink', 'Executer')
            ->onlyOnIndex()
            ->renderAsHtml()
            ->formatValue(static function ($value, ScheduledCommand $command): string {
                return sprintf(
                    '<a class="btn btn-primary kj-cron-action" href="/admin/scheduled-command/%s/run-now">Executer</a>',
                    (string) $command->getId()
                );
            });
        yield TextField::new('planLink', 'Planifier')
            ->onlyOnIndex()
            ->renderAsHtml()
            ->formatValue(static function ($value, ScheduledCommand $command): string {
                return sprintf(
                    '<a class="btn btn-secondary kj-cron-action" href="/admin/scheduled-command/%s">Planifier</a>',
                    (string) $command->getId()
                );
            });
        yield TextField::new('planningGuide', 'Planification Windows / cron')->onlyOnDetail()->renderAsHtml();
        yield IntegerField::new('lastExitCode', 'Code')->hideOnForm();
        yield IntegerField::new('lastDurationMs', 'Duree ms')->hideOnIndex()->hideOnForm();
        yield TextareaField::new('lastOutput', 'Derniere sortie')->hideOnIndex()->hideOnForm();
        yield TextareaField::new('lastError', 'Derniere erreur')->hideOnIndex()->hideOnForm();
        yield DateTimeField::new('lastRunAt', 'Dernier passage')->hideOnForm();
        yield DateTimeField::new('lastSuccessAt', 'Dernier succes')->hideOnIndex()->hideOnForm();
        yield DateTimeField::new('createdAt', 'Creee le')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $runNow = Action::new('runNow', 'Executer', 'fa fa-play')
            ->linkToRoute('admin_scheduled_command_run_now', static fn (ScheduledCommand $command): array => [
                'id' => (string) $command->getId(),
            ])
            ->displayAsButton()
            ->addCssClass('btn btn-primary');

        $runDue = Action::new('runDue', 'Executer les commandes dues', 'fa fa-rotate')
            ->linkToRoute('admin_scheduled_command_run_due')
            ->createAsGlobalAction()
            ->displayAsButton()
            ->addCssClass('btn btn-primary');

        return $actions
            ->add(Crud::PAGE_INDEX, $runDue)
            ->add(Crud::PAGE_DETAIL, $runNow)
            ->add(Crud::PAGE_EDIT, $runNow);
    }

    #[Route('/admin/scheduled-command/{id}/run-now', name: 'admin_scheduled_command_run_now', methods: ['GET', 'POST'])]
    public function runNow(ScheduledCommand $scheduledCommand, EntityManagerInterface $entityManager): RedirectResponse
    {
        try {
            $result = $this->runner->run($scheduledCommand);
            $type = 0 === $result['exitCode'] ? 'success' : 'warning';
            $this->addFlash($type, sprintf('Commande executee avec le code %d.', $result['exitCode']));
        } catch (\Throwable $exception) {
            $scheduledCommand
                ->setLastRunAt(new \DateTimeImmutable())
                ->setLastError($exception->getMessage())
                ->setLastExitCode(1);
            $entityManager->flush();
            $this->addFlash('danger', 'Execution impossible : ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_scheduled_command_index');
    }

    #[Route('/admin/scheduled-commands/run-due', name: 'admin_scheduled_command_run_due', methods: ['GET', 'POST'])]
    public function runDue(): RedirectResponse
    {
        $summary = $this->runner->runDue();
        $this->addFlash('success', sprintf(
            '%d commande(s) verifiee(s), %d executee(s), %d erreur(s).',
            $summary['checked'],
            $summary['executed'],
            $summary['errors']
        ));

        return $this->redirectToRoute('admin_scheduled_command_index');
    }
}
