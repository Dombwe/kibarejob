<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:storage:check',
    description: 'Vérifie que le stockage fichiers KIBARE-JOB est accessible et inscriptible.',
)]
class CheckStorageCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $storagePath,
        private readonly string $storageBaseUrl,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $root = $this->resolveStorageRoot();

        if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
            $io->error('Impossible de créer le dossier de stockage : ' . $root);

            return Command::FAILURE;
        }

        if (!is_writable($root)) {
            $io->error('Le dossier de stockage n’est pas inscriptible : ' . $root);

            return Command::FAILURE;
        }

        $probeDirectory = $root . DIRECTORY_SEPARATOR . 'healthcheck';
        if (!is_dir($probeDirectory) && !mkdir($probeDirectory, 0775, true) && !is_dir($probeDirectory)) {
            $io->error('Impossible de créer le dossier de test : ' . $probeDirectory);

            return Command::FAILURE;
        }

        $probeFile = $probeDirectory . DIRECTORY_SEPARATOR . 'storage-check.txt';
        file_put_contents($probeFile, 'KIBARE-JOB storage check ' . date(DATE_ATOM));

        if (!is_file($probeFile) || !is_readable($probeFile)) {
            $io->error('Le fichier de test n’est pas lisible : ' . $probeFile);

            return Command::FAILURE;
        }

        $io->success('Stockage fichiers opérationnel.');
        $io->listing([
            'Chemin : ' . $root,
            'URL publique : ' . rtrim($this->storageBaseUrl, '/') . '/healthcheck/storage-check.txt',
        ]);

        return Command::SUCCESS;
    }

    private function resolveStorageRoot(): string
    {
        if (str_starts_with($this->storagePath, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $this->storagePath)) {
            return rtrim($this->storagePath, '/\\');
        }

        return $this->projectDir . DIRECTORY_SEPARATOR . trim($this->storagePath, '/\\');
    }
}
