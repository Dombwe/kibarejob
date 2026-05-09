<?php

namespace App\Command;

use App\Repository\JobImportSourceRepository;
use App\Service\ExternalJobImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jobs:import-external', description: 'Importe automatiquement des offres depuis les sources API configurées.')]
class ImportExternalJobsCommand extends Command
{
    public function __construct(
        private readonly ExternalJobImportService $importService,
        private readonly JobImportSourceRepository $sourceRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Nom exact de la source à importer')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximum d’offres à lire par source');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = null !== $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $sourceName = $input->getOption('source');

        if ($sourceName) {
            $source = $this->sourceRepository->findOneBy(['name' => $sourceName]);
            if (!$source) {
                $io->error(sprintf('La source "%s" est introuvable.', $sourceName));

                return Command::FAILURE;
            }

            $result = $this->importService->importSource($source, $limit);
            $io->success(sprintf('%d offres importées, %d ignorées.', $result['imported'], $result['skipped']));

            return Command::SUCCESS;
        }

        $summary = $this->importService->importAllEnabled($limit);
        foreach ($summary['errors'] as $error) {
            $io->warning($error);
        }

        $io->success(sprintf(
            '%d source(s) traitée(s), %d offres importées, %d ignorées.',
            $summary['sources'],
            $summary['imported'],
            $summary['skipped']
        ));

        return Command::SUCCESS;
    }
}
