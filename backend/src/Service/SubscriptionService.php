<?php

namespace App\Service;

use App\Entity\Employer;
use App\Entity\Enum\EmployerSubscriptionTier;
use App\Entity\Enum\UserSubscriptionTier;
use App\Entity\User;
use App\Repository\EmployerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionService
{
    private const CANDIDATE_FREE_DAILY_SWIPES = 10;
    private const EMPLOYER_FREE_ACTIVE_OFFERS = 1;
    private const EMPLOYER_STANDARD_ACTIVE_OFFERS = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly EmployerRepository $employerRepository,
        private readonly string $subscriptionMode = 'mock',
    ) {
    }

    public function canSwipe(User $user): bool
    {
        if ($this->isMockMode() || $this->isCandidatePremium($user)) {
            return true;
        }

        $this->resetUserDailyCounterIfNeeded($user);

        return $user->getSwipesUsedToday() < self::CANDIDATE_FREE_DAILY_SWIPES;
    }

    public function canSuperSwipe(User $user): bool
    {
        return $this->isMockMode() || $this->isCandidatePremium($user);
    }

    public function canUploadDocument(User $user, int $currentDocumentCount): bool
    {
        if ($this->isMockMode()) {
            return true;
        }

        $limit = $this->isCandidatePremium($user) ? 50 : 10;

        return $currentDocumentCount < $limit;
    }

    public function canCreateOffer(Employer $employer): bool
    {
        if ($this->isMockMode()) {
            return true;
        }

        $limit = $this->getOfferLimit($employer);

        return -1 === $limit || $employer->getOffersUsedThisMonth() < $limit;
    }

    public function getRemainingSwipes(User $user): int
    {
        if ($this->isMockMode() || $this->isCandidatePremium($user)) {
            return -1;
        }

        $this->resetUserDailyCounterIfNeeded($user);

        return max(0, self::CANDIDATE_FREE_DAILY_SWIPES - $user->getSwipesUsedToday());
    }

    public function getRemainingOffers(Employer $employer): int
    {
        if ($this->isMockMode()) {
            return -1;
        }

        $limit = $this->getOfferLimit($employer);

        return -1 === $limit ? -1 : max(0, $limit - $employer->getOffersUsedThisMonth());
    }

    public function getCandidateFeatures(User $user): array
    {
        $premium = $this->isMockMode() || $this->isCandidatePremium($user);

        return [
            'tier' => $premium ? 'premium' : 'free',
            'swipesLimit' => $premium ? -1 : self::CANDIDATE_FREE_DAILY_SWIPES,
            'documentsLimit' => $premium ? 50 : 10,
            'cvDesigner' => $premium ? 'pro' : 'standard',
            'superSwipe' => $premium,
            'personalStats' => $premium,
            'adsEnabled' => !$premium,
        ];
    }

    public function getEmployerFeatures(Employer $employer): array
    {
        $tier = $this->isMockMode() ? EmployerSubscriptionTier::Pro : $employer->getSubscriptionTier();

        return [
            'tier' => $tier->value,
            'activeOffersLimit' => $this->isMockMode() ? -1 : $this->getOfferLimit($employer),
            'applicationsPerOfferLimit' => match ($tier) {
                EmployerSubscriptionTier::Free => 10,
                EmployerSubscriptionTier::Standard => 100,
                EmployerSubscriptionTier::Pro => -1,
            },
            'csvExport' => $tier !== EmployerSubscriptionTier::Free,
            'advancedStats' => $tier !== EmployerSubscriptionTier::Free,
            'apiAccess' => $tier === EmployerSubscriptionTier::Pro,
            'prioritySupport' => $tier === EmployerSubscriptionTier::Pro,
            'whiteLabel' => $tier === EmployerSubscriptionTier::Pro,
        ];
    }

    public function resetDailyCounters(): void
    {
        foreach ($this->userRepository->findAll() as $user) {
            $user
                ->setSwipesUsedToday(0)
                ->setLastSwipeReset(new \DateTimeImmutable('today'));
        }

        $this->entityManager->flush();
    }

    public function resetMonthlyCounters(): void
    {
        foreach ($this->employerRepository->findAll() as $employer) {
            $employer
                ->setOffersUsedThisMonth(0)
                ->setApplicationsViewedThisMonth(0)
                ->setLastOfferReset(new \DateTimeImmutable('today'));
        }

        $this->entityManager->flush();
    }

    public function isMockMode(): bool
    {
        return 'mock' === mb_strtolower($this->subscriptionMode);
    }

    private function isCandidatePremium(User $user): bool
    {
        return $user->getSubscriptionTier() === UserSubscriptionTier::Premium
            && (null === $user->getSubscriptionExpiry() || $user->getSubscriptionExpiry() >= new \DateTimeImmutable('today'));
    }

    private function getOfferLimit(Employer $employer): int
    {
        return match ($employer->getSubscriptionTier()) {
            EmployerSubscriptionTier::Free => self::EMPLOYER_FREE_ACTIVE_OFFERS,
            EmployerSubscriptionTier::Standard => self::EMPLOYER_STANDARD_ACTIVE_OFFERS,
            EmployerSubscriptionTier::Pro => -1,
        };
    }

    private function resetUserDailyCounterIfNeeded(User $user): void
    {
        if ($user->getLastSwipeReset()->format('Y-m-d') === (new \DateTimeImmutable('today'))->format('Y-m-d')) {
            return;
        }

        $user
            ->setSwipesUsedToday(0)
            ->setLastSwipeReset(new \DateTimeImmutable('today'));
        $this->entityManager->flush();
    }
}
