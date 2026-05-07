<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CandidateController extends AbstractController
{
    #[Route('/candidats', name: 'candidate_landing', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('candidate/index.html.twig');
    }
}
