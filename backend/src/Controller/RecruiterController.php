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

    #[Route('/recruteur/offres', name: 'recruiter_offers', methods: ['GET'])]
    public function offers(): Response
    {
        return $this->render('recruiter/offers.html.twig');
    }

    #[Route('/recruteur/offres/nouvelle', name: 'recruiter_offer_new', methods: ['GET'])]
    public function newOffer(): Response
    {
        return $this->render('recruiter/offer_new.html.twig');
    }

    #[Route('/recruteur/candidatures', name: 'recruiter_applications', methods: ['GET'])]
    public function applications(): Response
    {
        return $this->render('recruiter/applications.html.twig');
    }

    #[Route('/recruteur/statistiques', name: 'recruiter_stats', methods: ['GET'])]
    public function stats(): Response
    {
        return $this->render('recruiter/stats.html.twig');
    }

    #[Route('/recruteur/parametres', name: 'recruiter_settings', methods: ['GET'])]
    public function settings(): Response
    {
        return $this->render('recruiter/settings.html.twig');
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
