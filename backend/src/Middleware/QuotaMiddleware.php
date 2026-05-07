<?php

namespace App\Middleware;

use App\Entity\User;
use App\Service\QuotaService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class QuotaMiddleware
{
    public function __construct(
        private readonly Security $security,
        private readonly QuotaService $quotaService,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->quotaService->shouldEnforceQuotas()) {
            return;
        }

        $request = $event->getRequest();
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        try {
            if ('POST' === $request->getMethod() && str_starts_with($request->getPathInfo(), '/api/swipes')) {
                $this->quotaService->assertCanSwipe($user);
            }

            if ('POST' === $request->getMethod() && str_starts_with($request->getPathInfo(), '/api/offers') && null !== $user->getEmployer()) {
                $this->quotaService->assertCanCreateOffer($user->getEmployer());
            }
        } catch (\RuntimeException $exception) {
            $event->setResponse(new JsonResponse([
                'message' => $exception->getMessage(),
                'code' => 'quota_exceeded',
            ], JsonResponse::HTTP_TOO_MANY_REQUESTS));
        }
    }
}
