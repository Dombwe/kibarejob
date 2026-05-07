<?php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\User;
use App\Repository\SubscriptionPlanRepository;
use Doctrine\ORM\EntityManagerInterface;

class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
        private readonly string $subscriptionMode = 'mock',
        private readonly string $paymentMethod = 'none',
    ) {
    }

    public function initiatePayment(User $user, string $planId, string $method): array
    {
        $plan = $this->subscriptionPlanRepository->find($planId);
        $transactionId = 'mock_' . bin2hex(random_bytes(12));
        $amount = null !== $plan && method_exists($plan, 'getPrice') ? $plan->getPrice() : 0;

        $payment = (new Payment())
            ->setUser($user)
            ->setAmount('mock' === $this->subscriptionMode ? 0 : $amount)
            ->setCurrency('XOF')
            ->setMethod($this->normalizeMethod($method))
            ->setSubscriptionType($plan?->getName() ?? $planId)
            ->setTransactionId($transactionId)
            ->setStatus(\App\Entity\Enum\PaymentStatus::Success);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return [
            'transactionId' => $transactionId,
            'status' => 'success',
            'mode' => $this->subscriptionMode,
            'paymentMethod' => $this->paymentMethod,
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'message' => 'Paiement mock valide. Phase 1 gratuite active.',
        ];
    }

    public function verifyPayment(string $transactionId): bool
    {
        if ('mock' === $this->subscriptionMode) {
            return true;
        }

        return str_starts_with($transactionId, 'mock_');
    }

    private function normalizeMethod(string $method): \App\Entity\Enum\PaymentMethod
    {
        return match ($method) {
            'moov_money' => \App\Entity\Enum\PaymentMethod::MoovMoney,
            'card' => \App\Entity\Enum\PaymentMethod::Card,
            default => \App\Entity\Enum\PaymentMethod::OrangeMoney,
        };
    }
}
