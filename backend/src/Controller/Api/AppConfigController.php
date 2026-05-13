<?php

namespace App\Controller\Api;

use App\Repository\ApplicationSettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/app-config', name: 'api_app_config_')]
class AppConfigController extends AbstractController
{
    public function __construct(private readonly string $storageBaseUrl)
    {
    }

    #[Route('', name: 'show', methods: ['GET'])]
    public function show(ApplicationSettingRepository $settingsRepository, Request $request): JsonResponse
    {
        $settings = $settingsRepository->getActiveSettings();
        $activeBaseUrl = $settings->getActiveBaseUrl();
        $requestBaseUrl = $request->getSchemeAndHttpHost();

        if ($this->shouldUseRequestBaseUrl($activeBaseUrl, $requestBaseUrl)) {
            $activeBaseUrl = $requestBaseUrl;
        }

        return $this->json([
            'apiBaseUrl' => $activeBaseUrl,
            'environment' => $settings->getActiveEnvironment(),
            'localBaseUrl' => $settings->getLocalBaseUrl(),
            'onlineBaseUrl' => $settings->getOnlineBaseUrl(),
            'storageBaseUrl' => $this->normalizeStorageBaseUrl($activeBaseUrl),
            'requestBaseUrl' => $requestBaseUrl,
        ]);
    }

    private function normalizeStorageBaseUrl(string $apiBaseUrl): string
    {
        if (str_starts_with($this->storageBaseUrl, 'http://') || str_starts_with($this->storageBaseUrl, 'https://')) {
            return rtrim($this->storageBaseUrl, '/');
        }

        return rtrim($apiBaseUrl, '/') . '/' . ltrim($this->storageBaseUrl, '/');
    }

    private function shouldUseRequestBaseUrl(string $configuredBaseUrl, string $requestBaseUrl): bool
    {
        $configuredHost = parse_url($configuredBaseUrl, PHP_URL_HOST);
        $requestHost = parse_url($requestBaseUrl, PHP_URL_HOST);

        if (!is_string($configuredHost) || !is_string($requestHost)) {
            return false;
        }

        if ($configuredHost === $requestHost) {
            return false;
        }

        return $this->isLocalNetworkHost($configuredHost) && $this->isLocalNetworkHost($requestHost);
    }

    private function isLocalNetworkHost(string $host): bool
    {
        return 'localhost' === $host
            || '127.0.0.1' === $host
            || '10.0.2.2' === $host
            || str_starts_with($host, '192.168.')
            || str_starts_with($host, '10.')
            || 1 === preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $host);
    }
}
