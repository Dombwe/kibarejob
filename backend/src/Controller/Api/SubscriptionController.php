<?php

namespace App\Controller\Api;

use App\Entity\Employer;
use App\Entity\Enum\EmployerSubscriptionTier;
use App\Entity\Enum\UserSubscriptionTier;
use App\Entity\SubscriptionPlan;
use App\Entity\User;
use App\Repository\SubscriptionPlanRepository;
use App\Service\PaymentService;
use App\Service\SubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/subscription')]
class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly SubscriptionService $subscriptionService,
        private readonly PaymentService $paymentService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/plans', name: 'api_subscription_plans', methods: ['POST'])]
    public function plans(): JsonResponse
    {
        $plans = $this->planRepository->findBy(['isDeleted' => false]);

        if ([] === $plans) {
            return $this->json(['plans' => $this->defaultPlans()]);
        }

        return $this->json([
            'plans' => array_map(fn (SubscriptionPlan $plan) => $this->serializePlan($plan), $plans),
        ]);
    }

    #[Route('/subscribe', name: 'api_subscription_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $payload = $this->jsonPayload($request);
        $planId = (string) ($payload['planId'] ?? $payload['plan_id'] ?? '');
        $planCode = (string) ($payload['planCode'] ?? $payload['plan_code'] ?? $planId);
        $method = (string) ($payload['method'] ?? 'none');

        $payment = $this->paymentService->initiatePayment($user, $planId ?: $planCode, $method);
        if (!$this->paymentService->verifyPayment($payment['transactionId'])) {
            return $this->json(['message' => 'Paiement non confirmé.'], JsonResponse::HTTP_PAYMENT_REQUIRED);
        }

        $this->applySubscription($user, $planCode);
        $this->entityManager->flush();

        return $this->json([
            'payment' => $payment,
            'status' => $this->subscriptionStatus($user),
        ]);
    }

    #[Route('/status', name: 'api_subscription_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json(['status' => $this->subscriptionStatus($this->authenticatedUser())]);
    }

    #[Route('/cancel', name: 'api_subscription_cancel', methods: ['POST'])]
    public function cancel(): JsonResponse
    {
        $user = $this->authenticatedUser();
        $user
            ->setSubscriptionTier(UserSubscriptionTier::Free)
            ->setSubscriptionExpiry(null)
            ->setSubscriptionStartedAt(null);

        if ($user->getEmployer() instanceof Employer) {
            $user->getEmployer()
                ->setSubscriptionTier(EmployerSubscriptionTier::Free)
                ->setSubscriptionExpiry(null);
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Abonnement résilié.',
            'status' => $this->subscriptionStatus($user),
        ]);
    }

    private function applySubscription(User $user, string $planCode): void
    {
        $today = new \DateTimeImmutable('today');
        $expiry = $today->modify('+1 month');
        $normalized = mb_strtolower($planCode);

        if ($this->subscriptionService->isMockMode()) {
            $user
                ->setSubscriptionTier(UserSubscriptionTier::Premium)
                ->setSubscriptionStartedAt($today)
                ->setSubscriptionExpiry($expiry);

            if ($user->getEmployer() instanceof Employer) {
                $user->getEmployer()
                    ->setSubscriptionTier(EmployerSubscriptionTier::Pro)
                    ->setSubscriptionExpiry($expiry);
            }

            return;
        }

        if (str_contains($normalized, 'standard') && $user->getEmployer() instanceof Employer) {
            $user->getEmployer()->setSubscriptionTier(EmployerSubscriptionTier::Standard)->setSubscriptionExpiry($expiry);
            return;
        }

        if (str_contains($normalized, 'pro') && $user->getEmployer() instanceof Employer) {
            $user->getEmployer()->setSubscriptionTier(EmployerSubscriptionTier::Pro)->setSubscriptionExpiry($expiry);
            return;
        }

        $user
            ->setSubscriptionTier(UserSubscriptionTier::Premium)
            ->setSubscriptionStartedAt($today)
            ->setSubscriptionExpiry($expiry);
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionStatus(User $user): array
    {
        $employer = $user->getEmployer();

        return [
            'mode' => $this->subscriptionService->isMockMode() ? 'mock' : 'production',
            'candidate' => [
                'tier' => $this->subscriptionService->isMockMode() ? 'premium' : $user->getSubscriptionTier()->value,
                'expiry' => $user->getSubscriptionExpiry()?->format('Y-m-d'),
                'remainingSwipes' => $this->subscriptionService->getRemainingSwipes($user),
                'features' => $this->subscriptionService->getCandidateFeatures($user),
            ],
            'employer' => $employer instanceof Employer ? [
                'tier' => $this->subscriptionService->isMockMode() ? 'pro' : $employer->getSubscriptionTier()->value,
                'expiry' => $employer->getSubscriptionExpiry()?->format('Y-m-d'),
                'remainingOffers' => $this->subscriptionService->getRemainingOffers($employer),
                'features' => $this->subscriptionService->getEmployerFeatures($employer),
            ] : null,
        ];
    }

    private function authenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultPlans(): array
    {
        return [
            [
                'id' => 'candidate_premium',
                'name' => 'Candidat Premium',
                'target' => 'candidat',
                'price' => 2000,
                'mockPrice' => 0,
                'features' => ['swipes_illimites', '50_documents', 'cv_designer_pro', 'super_swipe', 'statistiques'],
            ],
            [
                'id' => 'employer_standard',
                'name' => 'Employeur Standard',
                'target' => 'employeur',
                'price' => 20000,
                'mockPrice' => 0,
                'features' => ['5_offres_actives', '100_candidatures_par_offre', 'export_csv', 'statistiques_avancées'],
            ],
            [
                'id' => 'employer_pro',
                'name' => 'Employeur Pro',
                'target' => 'employeur',
                'price' => 75000,
                'mockPrice' => 0,
                'features' => ['offres_illimitees', 'candidatures_illimitees', 'api_accèss', 'support_prioritaire'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePlan(SubscriptionPlan $plan): array
    {
        return [
            'id' => (string) $plan->getId(),
            'name' => $plan->getName(),
            'target' => $plan->getTarget()->value,
            'price' => $plan->getPrice(),
            'mockPrice' => $this->subscriptionService->isMockMode() ? 0 : $plan->getPrice(),
            'price3months' => $plan->getPrice3months(),
            'price6months' => $plan->getPrice6months(),
            'price12months' => $plan->getPrice12months(),
            'features' => $plan->getFeatures(),
        ];
    }
}


