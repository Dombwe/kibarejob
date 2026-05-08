<?php

namespace App\Controller;

use App\Service\DashboardStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(DashboardStatsService $dashboardStats): Response
    {
        return $this->render('home/index.html.twig', [
            'homeStats' => $dashboardStats->getHomeStats(),
        ]);
    }
}
