<?php

namespace App\Service;

use App\Entity\Employer;
use App\Entity\User;

class QuotaService
{
    public function __construct(private readonly SubscriptionService $subscriptionService)
    {
    }

    public function assertCanSwipe(User $user): void
    {
        if (!$this->subscriptionService->canSwipe($user)) {
            throw new \RuntimeException('Quota de swipes quotidien atteint.');
        }
    }

    public function assertCanCreateOffer(Employer $employer): void
    {
        if (!$this->subscriptionService->canCreateOffer($employer)) {
            throw new \RuntimeException('Quota mensuel de publication d offres atteint.');
        }
    }

    public function shouldEnforceQuotas(): bool
    {
        return !$this->subscriptionService->isMockMode();
    }
}
