<?php

namespace App\Controller\Api;

use App\Repository\ApplicationSettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/app-config', name: 'api_app_config_')]
class AppConfigController extends AbstractController
{
    #[Route('', name: 'show', methods: ['GET'])]
    public function show(ApplicationSettingRepository $settingsRepository): JsonResponse
    {
        $settings = $settingsRepository->getActiveSettings();

        return $this->json([
            'apiBaseUrl' => $settings->getActiveBaseUrl(),
            'environment' => $settings->getActiveEnvironment(),
            'localBaseUrl' => $settings->getLocalBaseUrl(),
            'onlineBaseUrl' => $settings->getOnlineBaseUrl(),
        ]);
    }
}
