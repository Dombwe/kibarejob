<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
class ApiController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'name' => 'KIBARE-JOB API',
            'status' => 'ok',
            'version' => '1.0.0',
            'environment' => $this->getParameter('kernel.environment'),
            'endpoints' => [
                'auth' => [
                    'POST /api/auth/register',
                    'POST /api/auth/login',
                ],
                'candidate' => [
                    'GET /api/profile',
                    'GET /api/jobs/feed',
                    'GET /api/documents',
                    'GET /api/matches',
                ],
                'employer' => [
                    'GET /api/employer/profile',
                    'GET /api/employer/offers',
                    'GET /api/employer/applications',
                ],
                'subscription' => [
                    'POST /api/subscription/plans',
                    'GET /api/subscription/status',
                ],
            ],
        ]);
    }
}
