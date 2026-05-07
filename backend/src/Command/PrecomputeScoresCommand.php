<?php

namespace App\Command;

use App\Entity\CandidateProfile;
use App\Entity\Enum\JobOfferStatus;
use App\Entity\JobOffer;
use App\Repository\CandidateProfileRepository;
use App\Repository\JobOfferRepository;
use App\Service\ScoreCacheService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'matching:precompute-scores',
    description: 'Pre-calcule les scores de matching candidats/offres pour le feed.',
)]
class PrecomputeScoresCommand extends Command
{
    public function __construct(
        private readonly CandidateProfileRepository $candidateProfileRepository,
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly ScoreCacheService $scoreCacheService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit-candidates', null, InputOption::VALUE_REQUIRED, 'Nombre max de candidats a traiter', 500)
            ->addOption('limit-offers', null, InputOption::VALUE_REQUIRED, 'Nombre max d offres actives a traiter', 500);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $candidateLimit = max(1, (int) $input->getOption('limit-candidates'));
        $offerLimit = max(1, (int) $input->getOption('limit-offers'));

        $candidates = $this->candidateProfileRepository->createQueryBuilder('candidate')
            ->andWhere('candidate.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->setMaxResults($candidateLimit)
            ->getQuery()
            ->getResult();

        $offers = $this->jobOfferRepository->createQueryBuilder('offer')
            ->andWhere('offer.status = :status')
            ->andWhere('offer.isDeleted = :deleted')
            ->andWhere('offer.deadline >= :today')
            ->setParameter('status', JobOfferStatus::Active)
            ->setParameter('deleted', false)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->setMaxResults($offerLimit)
            ->getQuery()
            ->getResult();

        $computed = 0;
        foreach ($candidates as $candidate) {
            if (!$candidate instanceof CandidateProfile) {
                continue;
            }

            foreach ($offers as $offer) {
                if (!$offer instanceof JobOffer) {
                    continue;
                }

                $this->scoreCacheService->getScore($candidate, $offer);
                ++$computed;
            }
        }

        $io->success(sprintf('%d score(s) pre-calcules pour %d candidat(s) et %d offre(s).', $computed, count($candidates), count($offers)));

        return Command::SUCCESS;
    }
}
