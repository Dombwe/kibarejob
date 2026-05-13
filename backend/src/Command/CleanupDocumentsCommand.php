<?php

namespace App\Command;

use App\Repository\CandidateDocumentRepository;
use App\Repository\SwipeRepository;
use App\Service\ChunkedUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'document:cleanup',
    description: 'Supprime les documents candidats non utilisés de plus de 90 jours et nettoie les chunks expirés.',
)]
class CleanupDocumentsCommand extends Command
{
    public function __construct(
        private readonly CandidateDocumentRepository $documentRepository,
        private readonly SwipeRepository $swipeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChunkedUploadService $chunkedUploadService,
        private readonly string $projectDir,
        private readonly string $storagePath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Age minimum des documents a supprimer', 90)
            ->addOption('include-active', null, InputOption::VALUE_NONE, 'Inclut aussi les documents actifs non utilisés')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait supprimé sans modifier la base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int) $input->getOption('days'));
        $dryRun = (bool) $input->getOption('dry-run');
        $includeActive = (bool) $input->getOption('include-active');
        $threshold = new \DateTimeImmutable(sprintf('-%d days', $days));
        $queryBuilder = $this->documentRepository->createQueryBuilder('document')
            ->andWhere('document.uploadedAt < :threshold')
            ->setParameter('threshold', $threshold);

        if (!$includeActive) {
            $queryBuilder
                ->andWhere('document.isDeleted = :deleted')
                ->setParameter('deleted', true);
        }

        $documents = $queryBuilder->getQuery()->getResult();

        $deletedDocuments = 0;
        $deletedFiles = 0;

        foreach ($documents as $document) {
            $isUsedInSwipe = null !== $this->swipeRepository->createQueryBuilder('swipe')
                ->select('swipe.id')
                ->andWhere('swipe.documentsSent LIKE :documentId')
                ->setParameter('documentId', '%' . $document->getId() . '%')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($isUsedInSwipe || (!$document->isDeleted() && $document->isPinned())) {
                continue;
            }

            if (!$dryRun) {
                $filePath = $this->absolutePathFromUrl($document->getFileUrl());
                if (is_file($filePath)) {
                    unlink($filePath);
                    ++$deletedFiles;
                }

                $this->entityManager->remove($document);
            }

            ++$deletedDocuments;
        }

        $deletedChunkDirectories = $dryRun ? 0 : $this->chunkedUploadService->cleanupExpiredChunks();

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%d document(s) %s, %d fichier(s) supprimé(s), %d dossier(s) de chunks nettoye(s).',
            $deletedDocuments,
            $dryRun ? 'detecte(s)' : 'supprime(s)',
            $deletedFiles,
            $deletedChunkDirectories,
        ));

        return Command::SUCCESS;
    }

    private function absolutePathFromUrl(string $url): string
    {
        if (str_starts_with($url, '/storage/')) {
            return $this->resolveStorageRoot() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim(substr($url, strlen('/storage/')), '/\\'));
        }

        return $this->projectDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($url, '/\\'));
    }

    private function resolveStorageRoot(): string
    {
        if (str_starts_with($this->storagePath, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $this->storagePath)) {
            return rtrim($this->storagePath, '/\\');
        }

        return $this->projectDir . DIRECTORY_SEPARATOR . trim($this->storagePath, '/\\');
    }
}
