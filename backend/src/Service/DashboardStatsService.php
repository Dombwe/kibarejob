<?php

namespace App\Service;

use App\Entity\Enum\JobOfferStatus;
use App\Entity\Enum\SwipeStatus;
use App\Entity\JobOffer;
use App\Entity\Swipe;
use App\Entity\User;
use App\Repository\CandidateProfileRepository;
use App\Repository\EmployerRepository;
use App\Repository\JobOfferRepository;
use App\Repository\SwipeRepository;
use Doctrine\ORM\EntityManagerInterface;

class DashboardStatsService
{
    public function __construct(
        private readonly CandidateProfileRepository $candidateProfileRepository,
        private readonly EmployerRepository $employerRepository,
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly SwipeRepository $swipeRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getHomeStats(): array
    {
        $candidates = $this->candidateProfileRepository->count(['isDeleted' => false]);
        $employers = $this->employerRepository->count(['isDeleted' => false]);
        $hired = $this->swipeRepository->count(['status' => SwipeStatus::Hired, 'isDeleted' => false]);
        $activeOffers = $this->jobOfferRepository->count(['status' => JobOfferStatus::Active, 'isDeleted' => false]);

        return [
            ['value' => $this->compactNumber($candidates), 'label' => 'Candidats'],
            ['value' => $this->compactNumber($employers), 'label' => 'Entreprises'],
            ['value' => $this->compactNumber($hired), 'label' => 'Recrutements'],
            ['value' => $this->compactNumber($activeOffers), 'label' => 'Offres actives'],
        ];
    }

    /**
     * @return array{
     *     stats: array<int, array<string, mixed>>,
     *     weekData: array<int, array{day: string, value: int, height: int}>,
     *     statusData: array<int, array{name: string, value: int, percent: int, color: string}>,
     *     recentApplications: array<int, array<string, mixed>>,
     *     topJobs: array<int, array<string, mixed>>,
     *     periodFilter: array<string, string>
     * }
     */
    public function getRecruiterDashboard(User $employerUser, string $period = 'week', ?string $customStart = null, ?string $customEnd = null): array
    {
        $periodFilter = $this->resolvePeriodFilter($period, $customStart, $customEnd);
        $allOffers = $this->getEmployerOffers($employerUser);
        $swipes = $this->filterSwipesByPeriod($this->getEmployerSwipes($employerUser), $periodFilter['startDate'], $periodFilter['endDate']);
        $periodOfferIds = [];
        foreach ($swipes as $swipe) {
            $periodOfferIds[(string) $swipe->getOffer()->getId()] = true;
        }
        $offers = array_values(array_filter($allOffers, static fn (JobOffer $offer): bool => ($offer->getCreatedAt() >= $periodFilter['startDate'] && $offer->getCreatedAt() <= $periodFilter['endDate']) || isset($periodOfferIds[(string) $offer->getId()])));

        $applications = count($swipes);
        $views = array_sum(array_map(static fn (JobOffer $offer): int => $offer->getViewsCount(), $offers));
        $interviews = count(array_filter($swipes, static fn (Swipe $swipe): bool => SwipeStatus::Interview === $swipe->getStatus()));
        $hires = count(array_filter($swipes, static fn (Swipe $swipe): bool => SwipeStatus::Hired === $swipe->getStatus()));

        return [
            'stats' => [
                ['label' => 'Candidatures', 'value' => (string) $applications, 'change' => $this->trendText($applications), 'trend' => 'up', 'color' => 'primary', 'icon' => 'users'],
                ['label' => 'Vues totales', 'value' => $this->compactNumber($views), 'change' => $this->trendText($views), 'trend' => 'up', 'color' => 'accent', 'icon' => 'eye'],
                ['label' => 'Entretiens', 'value' => (string) $interviews, 'change' => $this->trendText($interviews), 'trend' => 'up', 'color' => 'secondary', 'icon' => 'calendar'],
                ['label' => 'Recrutements', 'value' => (string) $hires, 'change' => $this->trendText($hires), 'trend' => 0 === $hires ? 'down' : 'up', 'color' => 'neutral', 'icon' => 'check'],
            ],
            'weekData' => $this->buildPeriodData($swipes, $periodFilter['startDate'], $periodFilter['endDate']),
            'statusData' => $this->buildStatusData($swipes),
            'recentApplications' => $this->buildRecentApplications($swipes),
            'topJobs' => $this->buildTopJobs($allOffers, $swipes),
            'periodFilter' => [
                'period' => $periodFilter['period'],
                'label' => $periodFilter['label'],
                'start' => $periodFilter['startDate']->format('Y-m-d'),
                'end' => $periodFilter['endDate']->format('Y-m-d'),
            ],
        ];
    }

    /**
     * @return array{offers: array<int, array<string, mixed>>, activeOffersCount: int}
     */
    public function getRecruiterOffersPage(User $employerUser): array
    {
        $offers = $this->getEmployerOffers($employerUser);

        return [
            'offers' => array_map(fn (JobOffer $offer): array => [
                'title' => $offer->getTitle(),
                'location' => $offer->getLocation(),
                'salary' => $this->salaryRange($offer),
                'contract' => $offer->getContractType()->value,
                'applications' => $offer->getApplicationsCount(),
                'views' => $offer->getViewsCount(),
                'status' => $offer->getStatus()->value,
                'postedAt' => $this->relativeTime($offer->getCreatedAt()),
                'deadline' => $offer->getDeadline()->format('d/m/Y'),
                'boosted' => $offer->isBoosted(),
            ], $offers),
            'activeOffersCount' => count(array_filter($offers, static fn (JobOffer $offer): bool => JobOfferStatus::Active === $offer->getStatus())),
        ];
    }

    /**
     * @return array{applications: array<int, array<string, mixed>>, statusCounts: array<string, int>}
     */
    public function getRecruiterApplicationsPage(User $employerUser): array
    {
        $swipes = $this->getEmployerSwipes($employerUser);

        $applications = array_map(function (Swipe $swipe): array {
            $profile = $swipe->getCandidate()->getCandidateProfile();
            $candidate = null === $profile
                ? $swipe->getCandidate()->getEmail()
                : trim($profile->getFirstName() . ' ' . $profile->getLastName());

            return [
                'candidate' => $candidate,
                'avatar' => $this->initials($candidate),
                'age' => $this->age($profile?->getBirthDate()),
                'location' => $profile?->getCity() ?: 'Non renseigné',
                'job' => $swipe->getOffer()->getTitle(),
                'status' => $swipe->getStatus()->value,
                'label' => $this->statusLabel($swipe->getStatus()),
                'score' => $swipe->getMatchScore() ?? 0,
                'skills' => $profile?->getSkills() ?: [],
                'education' => $profile?->getEducationLevel() ?: 'Non renseigné',
                'experience' => $this->experienceLabel($swipe->getOffer()->getRequiredExperienceYears()),
                'appliedAt' => 'Il y a ' . $this->relativeTime($swipe->getSentAt()),
            ];
        }, $swipes);

        return [
            'applications' => $applications,
            'statusCounts' => [
                'all' => count($applications),
                'new' => count(array_filter($swipes, static fn (Swipe $swipe): bool => SwipeStatus::Sent === $swipe->getStatus())),
                'viewed' => count(array_filter($swipes, static fn (Swipe $swipe): bool => SwipeStatus::Viewed === $swipe->getStatus())),
                'interview' => count(array_filter($swipes, static fn (Swipe $swipe): bool => SwipeStatus::Interview === $swipe->getStatus())),
                'hired' => count(array_filter($swipes, static fn (Swipe $swipe): bool => SwipeStatus::Hired === $swipe->getStatus())),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRecruiterStatsPage(User $employerUser): array
    {
        $offers = $this->getEmployerOffers($employerUser);
        $swipes = $this->getEmployerSwipes($employerUser);
        $applications = count($swipes);
        $views = array_sum(array_map(static fn (JobOffer $offer): int => $offer->getViewsCount(), $offers));
        $hires = count(array_filter($swipes, static fn (Swipe $swipe): bool => SwipeStatus::Hired === $swipe->getStatus()));
        $interviews = count(array_filter($swipes, static fn (Swipe $swipe): bool => SwipeStatus::Interview === $swipe->getStatus()));
        $conversion = 0 === $applications ? 0 : round(($hires / $applications) * 100, 1);

        return [
            'stats' => [
                ['label' => 'Candidatures totales', 'value' => (string) $applications, 'change' => $this->trendText($applications), 'color' => 'primary'],
                ['label' => 'Taux de conversion', 'value' => $conversion . '%', 'change' => $conversion . '%', 'color' => 'accent'],
                ['label' => 'Entretiens', 'value' => (string) $interviews, 'change' => $this->trendText($interviews), 'color' => 'secondary'],
                ['label' => 'Vues totales', 'value' => $this->compactNumber($views), 'change' => $this->trendText($views), 'color' => 'neutral'],
            ],
            'weekly' => $this->buildWeeklyStats($swipes, $offers),
            'funnel' => $this->buildFunnel($applications, $swipes),
            'skills' => $this->buildTopSkills($swipes),
            'locations' => $this->buildLocations($swipes),
            'conversionRate' => $conversion,
        ];
    }

    /**
     * @return array{company: array<string, string>, plans: array<int, array<string, mixed>>, team: array<int, array<string, string>>}
     */
    public function getRecruiterSettingsPage(User $employerUser): array
    {
        $employer = $employerUser->getEmployer();
        $companyName = $employer?->getCompanyName() ?: 'Entreprise';

        return [
            'company' => [
                'name' => $companyName,
                'nif' => $employer?->getNif() ?: '',
                'sector' => $employer?->getSector() ?: '',
                'size' => $employer?->getCompanySize() ?: '',
                'email' => $employerUser->getEmail(),
                'phone' => $employerUser->getPhone() ?: '',
                'city' => implode(', ', $employer?->getCities() ?: []),
                'country' => $employer?->getCountryName() ?: 'Burkina Faso',
            ],
            'plans' => $this->employerPlans($employer?->getSubscriptionTier()->value ?? 'free'),
            'team' => [[
                'name' => $companyName,
                'email' => $employerUser->getEmail(),
                'role' => 'Admin',
                'avatar' => $this->initials($companyName),
            ]],
        ];
    }

    /**
     * @return JobOffer[]
     */
    private function getEmployerOffers(User $employerUser): array
    {
        return $this->jobOfferRepository
            ->createQueryBuilder('offer')
            ->andWhere('offer.employer = :employer')
            ->andWhere('offer.isDeleted = false')
            ->setParameter('employer', $employerUser)
            ->orderBy('offer.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Swipe[]
     */
    private function getEmployerSwipes(User $employerUser): array
    {
        return $this->swipeRepository
            ->createQueryBuilder('swipe')
            ->join('swipe.offer', 'offer')
            ->addSelect('offer')
            ->join('swipe.candidate', 'candidate')
            ->addSelect('candidate')
            ->leftJoin('candidate.candidateProfile', 'profile')
            ->addSelect('profile')
            ->andWhere('offer.employer = :employer')
            ->andWhere('swipe.isDeleted = false')
            ->andWhere('offer.isDeleted = false')
            ->setParameter('employer', $employerUser)
            ->orderBy('swipe.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{period: string, label: string, startDate: \DateTimeImmutable, endDate: \DateTimeImmutable}
     */
    private function resolvePeriodFilter(string $period, ?string $customStart, ?string $customEnd): array
    {
        $today = new \DateTimeImmutable('today');
        $end = $today->setTime(23, 59, 59);

        if ('custom' === $period && null !== $customStart && null !== $customEnd) {
            try {
                $start = (new \DateTimeImmutable($customStart))->setTime(0, 0);
                $customEndDate = (new \DateTimeImmutable($customEnd))->setTime(23, 59, 59);
                if ($customEndDate >= $start) {
                    return [
                        'period' => 'custom',
                        'label' => 'Du ' . $start->format('d/m/Y') . ' au ' . $customEndDate->format('d/m/Y'),
                        'startDate' => $start,
                        'endDate' => $customEndDate,
                    ];
                }
            } catch (\Exception) {
                // Invalid custom dates fall back to the default weekly view.
            }
        }

        return match ($period) {
            'month' => [
                'period' => 'month',
                'label' => 'Ce mois',
                'startDate' => $today->modify('first day of this month')->setTime(0, 0),
                'endDate' => $end,
            ],
            'quarter' => [
                'period' => 'quarter',
                'label' => 'Ce trimestre',
                'startDate' => $today->modify('-2 months')->modify('first day of this month')->setTime(0, 0),
                'endDate' => $end,
            ],
            'semester' => [
                'period' => 'semester',
                'label' => 'Ce semestre',
                'startDate' => $today->modify('-5 months')->modify('first day of this month')->setTime(0, 0),
                'endDate' => $end,
            ],
            'year' => [
                'period' => 'year',
                'label' => 'Cette annee',
                'startDate' => $today->modify('first day of january this year')->setTime(0, 0),
                'endDate' => $end,
            ],
            default => [
                'period' => 'week',
                'label' => 'Cette semaine',
                'startDate' => $today->modify('-6 days')->setTime(0, 0),
                'endDate' => $end,
            ],
        };
    }

    /**
     * @param Swipe[] $swipes
     * @return Swipe[]
     */
    private function filterSwipesByPeriod(array $swipes, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return array_values(array_filter($swipes, static fn (Swipe $swipe): bool => $swipe->getSentAt() >= $start && $swipe->getSentAt() <= $end));
    }

    /**
     * @param Swipe[] $swipes
     * @return array<int, array{day: string, value: int, height: int}>
     */
    private function buildPeriodData(array $swipes, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $days = max(1, (int) $start->diff($end)->format('%a') + 1);
        if ($days <= 31) {
            return $this->buildDailyPeriodData($swipes, $start, $end, $days <= 7);
        }

        if ($days <= 180) {
            return $this->buildWeeklyPeriodData($swipes, $start, $end);
        }

        return $this->buildMonthlyPeriodData($swipes, $start, $end);
    }

    /**
     * @param Swipe[] $swipes
     * @return array<int, array{day: string, value: int, height: int}>
     */
    private function buildDailyPeriodData(array $swipes, \DateTimeImmutable $start, \DateTimeImmutable $end, bool $shortLabels): array
    {
        $buckets = [];
        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            $buckets[$date->format('Y-m-d')] = [
                'day' => $shortLabels ? $this->frenchDay($date) : $date->format('d/m'),
                'value' => 0,
                'height' => 0,
            ];
        }

        foreach ($swipes as $swipe) {
            $key = $swipe->getSentAt()->format('Y-m-d');
            if (isset($buckets[$key])) {
                ++$buckets[$key]['value'];
            }
        }

        return $this->normalizeChartHeights(array_values($buckets));
    }

    /**
     * @param Swipe[] $swipes
     * @return array<int, array{day: string, value: int, height: int}>
     */
    private function buildWeeklyPeriodData(array $swipes, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $buckets = [];
        $index = 1;
        for ($date = $start; $date <= $end; $date = $date->modify('+7 days')) {
            $candidateEnd = $date->modify('+6 days');
            $bucketEnd = $candidateEnd > $end ? $end : $candidateEnd;
            $key = $date->format('Y-m-d');
            $buckets[$key] = [
                'start' => $date,
                'end' => $bucketEnd,
                'day' => 'S' . $index,
                'value' => 0,
                'height' => 0,
            ];
            ++$index;
        }

        foreach ($swipes as $swipe) {
            foreach ($buckets as &$bucket) {
                if ($swipe->getSentAt() >= $bucket['start'] && $swipe->getSentAt() <= $bucket['end']) {
                    ++$bucket['value'];
                    break;
                }
            }
            unset($bucket);
        }

        return $this->normalizeChartHeights(array_map(static fn (array $bucket): array => [
            'day' => $bucket['day'],
            'value' => $bucket['value'],
            'height' => $bucket['height'],
        ], array_values($buckets)));
    }

    /**
     * @param Swipe[] $swipes
     * @return array<int, array{day: string, value: int, height: int}>
     */
    private function buildMonthlyPeriodData(array $swipes, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $buckets = [];
        for ($date = $start->modify('first day of this month'); $date <= $end; $date = $date->modify('first day of next month')) {
            $buckets[$date->format('Y-m')] = [
                'day' => $this->frenchMonth($date),
                'value' => 0,
                'height' => 0,
            ];
        }

        foreach ($swipes as $swipe) {
            $key = $swipe->getSentAt()->format('Y-m');
            if (isset($buckets[$key])) {
                ++$buckets[$key]['value'];
            }
        }

        return $this->normalizeChartHeights(array_values($buckets));
    }

    /**
     * @param array<int, array{day: string, value: int, height: int}> $items
     * @return array<int, array{day: string, value: int, height: int}>
     */
    private function normalizeChartHeights(array $items): array
    {
        $max = max(1, ...array_column($items, 'value'));
        foreach ($items as &$item) {
            $item['height'] = max(8, (int) round(($item['value'] / $max) * 100));
        }
        unset($item);

        return $items;
    }

    /**
     * @param Swipe[] $swipes
     * @return array<int, array{day: string, value: int, height: int}>
     */
    private function buildWeekData(array $swipes): array
    {
        $days = [];
        for ($i = 6; $i >= 0; --$i) {
            $date = new \DateTimeImmutable("-{$i} days");
            $days[$date->format('Y-m-d')] = [
                'day' => $this->frenchDay($date),
                'value' => 0,
                'height' => 0,
            ];
        }

        foreach ($swipes as $swipe) {
            $key = $swipe->getSentAt()->format('Y-m-d');
            if (isset($days[$key])) {
                ++$days[$key]['value'];
            }
        }

        $max = max(1, ...array_column($days, 'value'));
        foreach ($days as &$day) {
            $day['height'] = max(8, (int) round(($day['value'] / $max) * 100));
        }

        return array_values($days);
    }

    /**
     * @param Swipe[] $swipes
     * @return array<int, array{name: string, value: int, percent: int, color: string}>
     */
    private function buildStatusData(array $swipes): array
    {
        $counts = [
            SwipeStatus::Sent->value => 0,
            SwipeStatus::Viewed->value => 0,
            SwipeStatus::Interview->value => 0,
            SwipeStatus::Hired->value => 0,
            SwipeStatus::Rejected->value => 0,
        ];

        foreach ($swipes as $swipe) {
            ++$counts[$swipe->getStatus()->value];
        }

        $total = array_sum($counts);
        $percent = static fn (int $value): int => 0 === $total ? 0 : (int) round(($value / $total) * 100);

        return [
            ['name' => 'Nouvelles', 'value' => $counts[SwipeStatus::Sent->value], 'percent' => $percent($counts[SwipeStatus::Sent->value]), 'color' => 'bg-secondary'],
            ['name' => 'Consultees', 'value' => $counts[SwipeStatus::Viewed->value], 'percent' => $percent($counts[SwipeStatus::Viewed->value]), 'color' => 'bg-accent'],
            ['name' => 'Entretiens', 'value' => $counts[SwipeStatus::Interview->value], 'percent' => $percent($counts[SwipeStatus::Interview->value]), 'color' => 'bg-secondary/70'],
            ['name' => 'Recrutees', 'value' => $counts[SwipeStatus::Hired->value], 'percent' => $percent($counts[SwipeStatus::Hired->value]), 'color' => 'bg-primary'],
            ['name' => 'Rejetees', 'value' => $counts[SwipeStatus::Rejected->value], 'percent' => $percent($counts[SwipeStatus::Rejected->value]), 'color' => 'bg-slate-500'],
        ];
    }

    /**
     * @param Swipe[] $swipes
     * @return array<int, array<string, mixed>>
     */
    private function buildRecentApplications(array $swipes): array
    {
        return array_map(function (Swipe $swipe): array {
            $profile = $swipe->getCandidate()->getCandidateProfile();
            $candidate = null === $profile
                ? $swipe->getCandidate()->getEmail()
                : trim($profile->getFirstName() . ' ' . $profile->getLastName());

            return [
                'candidate' => $candidate,
                'avatar' => $this->initials($candidate),
                'job' => $swipe->getOffer()->getTitle(),
                'status' => $this->statusLabel($swipe->getStatus()),
                'tone' => $this->statusTone($swipe->getStatus()),
                'matchScore' => $swipe->getMatchScore() ?? 0,
                'appliedAt' => $this->relativeTime($swipe->getSentAt()),
            ];
        }, array_slice($swipes, 0, 4));
    }

    /**
     * @param JobOffer[] $offers
     * @param Swipe[] $periodSwipes
     * @return array<int, array<string, mixed>>
     */
    private function buildTopJobs(array $offers, array $periodSwipes = []): array
    {
        $periodCounts = [];
        foreach ($periodSwipes as $swipe) {
            $key = (string) $swipe->getOffer()->getId();
            $periodCounts[$key] = ($periodCounts[$key] ?? 0) + 1;
        }

        usort($offers, static function (JobOffer $a, JobOffer $b) use ($periodCounts): int {
            $aApplications = $periodCounts[(string) $a->getId()] ?? 0;
            $bApplications = $periodCounts[(string) $b->getId()] ?? 0;

            return [$bApplications, $b->getViewsCount()] <=> [$aApplications, $a->getViewsCount()];
        });

        $rankedOffers = array_filter($offers, static fn (JobOffer $offer): bool => ($periodCounts[(string) $offer->getId()] ?? 0) > 0);

        return array_map(function (JobOffer $offer) use ($periodSwipes, $periodCounts): array {
            $avgScore = $this->averageScoreForOffer($offer, $periodSwipes);

            return [
                'title' => $offer->getTitle(),
                'applications' => $periodCounts[(string) $offer->getId()] ?? 0,
                'views' => $offer->getViewsCount(),
                'avgScore' => $avgScore,
            ];
        }, array_slice($rankedOffers, 0, 3));
    }

    /**
     * @param Swipe[] $periodSwipes
     */
    private function averageScoreForOffer(JobOffer $offer, array $periodSwipes = []): int
    {
        if ([] !== $periodSwipes) {
            $scores = array_values(array_filter(array_map(
                static fn (Swipe $swipe): ?int => $swipe->getOffer() === $offer ? $swipe->getMatchScore() : null,
                $periodSwipes
            ), static fn (?int $score): bool => null !== $score));

            if ([] !== $scores) {
                return (int) round(array_sum($scores) / count($scores));
            }

            return 0;
        }

        $result = $this->entityManager->createQueryBuilder()
            ->select('AVG(swipe.matchScore)')
            ->from(Swipe::class, 'swipe')
            ->andWhere('swipe.offer = :offer')
            ->andWhere('swipe.matchScore IS NOT NULL')
            ->andWhere('swipe.isDeleted = false')
            ->setParameter('offer', $offer)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $result ? 0 : (int) round((float) $result);
    }

    private function salaryRange(JobOffer $offer): string
    {
        if (null === $offer->getSalaryMin() && null === $offer->getSalaryMax()) {
            return 'Non precise';
        }

        if (null === $offer->getSalaryMax()) {
            return number_format((int) $offer->getSalaryMin(), 0, ',', ' ');
        }

        if (null === $offer->getSalaryMin()) {
            return number_format((int) $offer->getSalaryMax(), 0, ',', ' ');
        }

        return number_format($offer->getSalaryMin(), 0, ',', ' ') . ' - ' . number_format($offer->getSalaryMax(), 0, ',', ' ');
    }

    /**
     * @param Swipe[] $swipes
     * @param JobOffer[] $offers
     * @return array<int, array{day: string, apps: int, views: int, appHeight: int, viewHeight: int}>
     */
    private function buildWeeklyStats(array $swipes, array $offers): array
    {
        $days = [];
        for ($i = 6; $i >= 0; --$i) {
            $date = new \DateTimeImmutable("-{$i} days");
            $days[$date->format('Y-m-d')] = ['day' => $this->frenchDay($date), 'apps' => 0, 'views' => 0];
        }

        foreach ($swipes as $swipe) {
            $key = $swipe->getSentAt()->format('Y-m-d');
            if (isset($days[$key])) {
                ++$days[$key]['apps'];
            }
        }

        $totalViews = array_sum(array_map(static fn (JobOffer $offer): int => $offer->getViewsCount(), $offers));
        $dailyViews = 0 === $totalViews ? 0 : (int) ceil($totalViews / 7);
        foreach ($days as &$day) {
            $day['views'] = $dailyViews;
        }
        unset($day);

        $maxApps = max(1, ...array_column($days, 'apps'));
        $maxViews = max(1, ...array_column($days, 'views'));
        foreach ($days as &$day) {
            $day['appHeight'] = max(8, (int) round(($day['apps'] / $maxApps) * 100));
            $day['viewHeight'] = max(8, (int) round(($day['views'] / $maxViews) * 100));
        }

        return array_values($days);
    }

    /**
     * @param Swipe[] $swipes
     * @return array<int, array{name: string, value: int, width: int, color: string}>
     */
    private function buildFunnel(int $applications, array $swipes): array
    {
        $viewed = count(array_filter($swipes, static fn (Swipe $swipe): bool => in_array($swipe->getStatus(), [SwipeStatus::Viewed, SwipeStatus::Interview, SwipeStatus::Hired], true)));
        $interviews = count(array_filter($swipes, static fn (Swipe $swipe): bool => SwipeStatus::Interview === $swipe->getStatus()));
        $hires = count(array_filter($swipes, static fn (Swipe $swipe): bool => SwipeStatus::Hired === $swipe->getStatus()));

        return [
            ['name' => 'Candidatures', 'value' => $applications, 'width' => $this->percentOf($applications, $applications), 'color' => 'bg-secondary'],
            ['name' => 'Consultees', 'value' => $viewed, 'width' => $this->percentOf($viewed, $applications), 'color' => 'bg-accent'],
            ['name' => 'Entretiens', 'value' => $interviews, 'width' => $this->percentOf($interviews, $applications), 'color' => 'bg-secondary'],
            ['name' => 'Recrutements', 'value' => $hires, 'width' => $this->percentOf($hires, $applications), 'color' => 'bg-accent'],
        ];
    }

    /**
     * @param Swipe[] $swipes
     * @return array<int, array{name: string, count: int, width: int}>
     */
    private function buildTopSkills(array $swipes): array
    {
        $counts = [];
        foreach ($swipes as $swipe) {
            $skills = $swipe->getCandidate()->getCandidateProfile()?->getSkills() ?: $swipe->getOffer()->getRequiredSkills();
            foreach ($skills as $skill) {
                $skill = trim((string) $skill);
                if ('' !== $skill) {
                    $counts[$skill] = ($counts[$skill] ?? 0) + 1;
                }
            }
        }

        arsort($counts);

        $top = array_slice($counts, 0, 6, true);
        $max = max(1, ...array_values($top ?: [1]));

        return array_map(
            fn (string $name, int $count): array => ['name' => $name, 'count' => $count, 'width' => $this->percentOf($count, $max)],
            array_keys($top),
            array_values($top)
        );
    }

    /**
     * @param Swipe[] $swipes
     * @return array<int, array{city: string, count: int, percent: int, color: string}>
     */
    private function buildLocations(array $swipes): array
    {
        $counts = [];
        foreach ($swipes as $swipe) {
            $city = $swipe->getCandidate()->getCandidateProfile()?->getCity() ?: 'Non renseigné';
            $counts[$city] = ($counts[$city] ?? 0) + 1;
        }
        arsort($counts);
        $total = max(1, array_sum($counts));
        $colors = ['bg-secondary', 'bg-secondary', 'bg-accent', 'bg-accent'];
        $items = [];
        foreach (array_slice($counts, 0, 4, true) as $city => $count) {
            $items[] = [
                'city' => $city,
                'count' => $count,
                'percent' => (int) round(($count / $total) * 100),
                'color' => $colors[count($items)] ?? 'bg-slate-500',
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function employerPlans(string $current): array
    {
        $plans = [
            ['id' => 'free', 'name' => 'Gratuit', 'price' => '0', 'features' => ['1 offre active', '10 candidatures par offre', 'Matching basique', 'Support email']],
            ['id' => 'standard', 'name' => 'Standard', 'price' => '25 000', 'features' => ['5 offres actives', '100 candidatures par offre', 'Matching avance', 'Statistiques RH']],
            ['id' => 'pro', 'name' => 'Pro', 'price' => '75 000', 'features' => ['Offres illimitées', 'Candidatures illimitées', 'Boost inclus', 'Support prioritaire']],
        ];

        return array_map(static function (array $plan) use ($current): array {
            $plan['current'] = $plan['id'] === $current;

            return $plan;
        }, $plans);
    }

    private function percentOf(int $value, int $total): int
    {
        if (0 === $total) {
            return 0;
        }

        return max(4, (int) round(($value / $total) * 100));
    }

    private function age(?\DateTimeImmutable $birthDate): string
    {
        if (null === $birthDate) {
            return '-';
        }

        return (string) $birthDate->diff(new \DateTimeImmutable())->y;
    }

    private function experienceLabel(int $years): string
    {
        return 0 === $years ? 'Débutant' : $years . ' an' . ($years > 1 ? 's' : '');
    }

    private function compactNumber(int $value): string
    {
        if ($value >= 1000) {
            $rounded = $value >= 10000 ? round($value / 1000) : round($value / 1000, 1);

            return str_replace('.0', '', (string) $rounded) . 'K+';
        }

        return (string) $value;
    }

    private function trendText(int $value): string
    {
        if (0 === $value) {
            return '0%';
        }

        return '+' . min(99, max(1, (int) round($value / 10))) . '%';
    }

    private function frenchDay(\DateTimeImmutable $date): string
    {
        return [
            'Mon' => 'Lun',
            'Tue' => 'Mar',
            'Wed' => 'Mer',
            'Thu' => 'Jeu',
            'Fri' => 'Ven',
            'Sat' => 'Sam',
            'Sun' => 'Dim',
        ][$date->format('D')] ?? $date->format('D');
    }

    private function frenchMonth(\DateTimeImmutable $date): string
    {
        return [
            'Jan' => 'Jan',
            'Feb' => 'Fev',
            'Mar' => 'Mar',
            'Apr' => 'Avr',
            'May' => 'Mai',
            'Jun' => 'Juin',
            'Jul' => 'Juil',
            'Aug' => 'Aout',
            'Sep' => 'Sep',
            'Oct' => 'Oct',
            'Nov' => 'Nov',
            'Dec' => 'Dec',
        ][$date->format('M')] ?? $date->format('M');
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(static fn (string $part): string => mb_substr($part, 0, 1), array_slice($parts, 0, 2));

        return mb_strtoupper(implode('', $letters) ?: 'KJ');
    }

    private function statusLabel(SwipeStatus $status): string
    {
        return match ($status) {
            SwipeStatus::Sent => 'Nouvelle',
            SwipeStatus::Viewed => 'Vue',
            SwipeStatus::Interview => 'Entretien',
            SwipeStatus::Rejected => 'Refusee',
            SwipeStatus::Hired => 'Recrutee',
        };
    }

    private function statusTone(SwipeStatus $status): string
    {
        return match ($status) {
            SwipeStatus::Sent => 'primary',
            SwipeStatus::Interview, SwipeStatus::Hired => 'secondary',
            default => 'accent',
        };
    }

    private function relativeTime(\DateTimeImmutable $date): string
    {
        $seconds = max(0, time() - $date->getTimestamp());
        if ($seconds < 3600) {
            return max(1, (int) floor($seconds / 60)) . 'min';
        }
        if ($seconds < 86400) {
            return (int) floor($seconds / 3600) . 'h';
        }

        return (int) floor($seconds / 86400) . 'j';
    }
}
