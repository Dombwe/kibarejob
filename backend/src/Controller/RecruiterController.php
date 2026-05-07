<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RecruiterController extends AbstractController
{
    #[Route('/recruteur', name: 'recruiter_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('recruiter/dashboard.html.twig');
    }

    #[Route('/go/recruteur', name: 'recruiter_entry', methods: ['GET'])]
    public function entry(): RedirectResponse
    {
        if (null === $this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        return $this->redirectToRoute('recruiter_dashboard');
    }
}
