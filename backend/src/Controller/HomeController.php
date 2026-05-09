<?php

namespace App\Controller;

use App\Service\ContactEmailService;
use App\Service\DashboardStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/faq', name: 'faq', methods: ['GET'])]
    public function faq(): Response
    {
        return $this->render('home/faq.html.twig');
    }

    #[Route('/contact', name: 'contact', methods: ['GET', 'POST'])]
    public function contact(Request $request, ContactEmailService $contactEmail): Response
    {
        $formData = [
            'full_name' => '',
            'email' => '',
            'profile' => 'candidate',
            'phone' => '',
            'subject' => '',
            'message' => '',
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            $formData = [
                'full_name' => trim((string) $request->request->get('full_name')),
                'email' => mb_strtolower(trim((string) $request->request->get('email'))),
                'profile' => trim((string) $request->request->get('profile', 'candidate')),
                'phone' => trim((string) $request->request->get('phone')),
                'subject' => trim((string) $request->request->get('subject')),
                'message' => trim((string) $request->request->get('message')),
            ];

            if (!$this->isCsrfTokenValid('contact_form', (string) $request->request->get('_csrf_token'))) {
                $errors[] = 'Le formulaire a expiré. Merci de réessayer.';
            }

            if ('' !== trim((string) $request->request->get('website'))) {
                $errors[] = "Votre message n'a pas pu être envoyé.";
            }

            if (strlen($formData['full_name']) < 2) {
                $errors[] = 'Merci de renseigner votre nom complet.';
            }

            if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Merci de renseigner une adresse email valide.';
            }

            if (!in_array($formData['profile'], ['candidate', 'employer', 'partner', 'other'], true)) {
                $errors[] = 'Merci de selectionner un profil valide.';
            }

            if (strlen($formData['subject']) < 3) {
                $errors[] = 'Merci de renseigner un objet plus precis.';
            }

            if (strlen($formData['message']) < 15) {
                $errors[] = 'Votre message doit contenir au moins 15 caractères.';
            }

            if ([] === $errors) {
                try {
                    $contactEmail->send($formData);
                    $this->addFlash('success', 'Votre message a bien été envoyé. Notre équipe vous recontactera rapidement.');

                    return $this->redirectToRoute('contact');
                } catch (\Throwable) {
                    $errors[] = "Impossible d'envoyer le message pour le moment. Vérifiez la configuration mail puis réessayez.";
                }
            }
        }

        return $this->render('home/contact.html.twig', [
            'formData' => $formData,
            'errors' => $errors,
        ]);
    }
}
