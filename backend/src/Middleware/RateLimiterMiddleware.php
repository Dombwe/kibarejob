<?php

namespace App\Middleware;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class RateLimiterMiddleware
{
    /**
     * @param string[] $criticalPrefixes
     * @param string[] $uploadPrefixes
     */
    public function __construct(
        private readonly RateLimiterFactory $criticalLimiter,
        private readonly RateLimiterFactory $uploadLimiter,
        private readonly Security $security,
        private readonly array $criticalPrefixes = ['/api/auth', '/api/swipes', '/api/offers', '/api/subscription'],
        private readonly array $uploadPrefixes = ['/api/documents/upload', '/api/profile/upload-cv', '/api/employer/profile/upload-logo'],
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $limiter = null;

        if ($this->matches($path, $this->uploadPrefixes)) {
            $limiter = $this->uploadLimiter;
        } elseif ($this->matches($path, $this->criticalPrefixes)) {
            $limiter = $this->criticalLimiter;
        }

        if (null === $limiter) {
            return;
        }

        $limit = $limiter->create($this->clientKey($request->getClientIp() ?? 'unknown'))->consume(1);
        if ($limit->isAccepted()) {
            return;
        }

        $retryAfter = $limit->getRetryAfter();
        $event->setResponse(new JsonResponse([
            'message' => 'Trop de requetes. Reessayez plus tard.',
            'code' => 'rate_limited',
            'retryAfter' => $retryAfter?->format(DATE_ATOM),
        ], JsonResponse::HTTP_TOO_MANY_REQUESTS, [
            'Retry-After' => $retryAfter ? (string) max(1, $retryAfter->getTimestamp() - time()) : '60',
        ]));
    }

    /**
     * @param string[] $prefixes
     */
    private function matches(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function clientKey(string $ip): string
    {
        $user = $this->security->getUser();
        $userId = method_exists($user, 'getId') ? (string) $user->getId() : 'guest';

        return sha1($ip . ':' . $userId);
    }
}
