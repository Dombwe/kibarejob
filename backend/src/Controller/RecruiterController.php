<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Enum\ContractType;
use App\Entity\Enum\JobOfferStatus;
use App\Entity\JobOffer;
use App\Service\DashboardStatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RecruiterController extends AbstractController
{
    #[Route('/recruteur', name: 'recruiter_dashboard', methods: ['GET'])]
    public function dashboard(Request $request, DashboardStatsService $dashboardStats): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('recruiter/dashboard.html.twig', $dashboardStats->getRecruiterDashboard(
            $user,
            (string) $request->query->get('period', 'week'),
            $request->query->get('start') ? (string) $request->query->get('start') : null,
            $request->query->get('end') ? (string) $request->query->get('end') : null,
        ));
    }

    #[Route('/recruteur/offres', name: 'recruiter_offers', methods: ['GET'])]
    public function offers(DashboardStatsService $dashboardStats): Response
    {
        $user = $this->requireRecruiterUser();

        return $this->render('recruiter/offers.html.twig', $dashboardStats->getRecruiterOffersPage($user));
    }

    #[Route('/recruteur/offres/nouvelle', name: 'recruiter_offer_new', methods: ['GET', 'POST'])]
    public function newOffer(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->requireRecruiterUser();
        $errors = [];

        if ($request->isMethod('POST')) {
            $title = trim((string) $request->request->get('title'));
            $description = trim((string) $request->request->get('description'));
            $contractType = (string) $request->request->get('contractType', ContractType::Cdi->value);
            $location = trim((string) $request->request->get('location'));
            $requiredEducation = (string) $request->request->get('requiredEducation', 'Aucun');
            $educationField = trim((string) $request->request->get('educationField'));
            $deadline = (string) $request->request->get('deadline');

            if ('' === $title) {
                $errors['title'][] = 'Le titre du poste est obligatoire.';
            }
            if ('' === $description) {
                $errors['description'][] = 'La description du poste est obligatoire.';
            }
            if ('' === $location) {
                $errors['location'][] = 'Le lieu de travail est obligatoire.';
            }

            $contract = ContractType::tryFrom($contractType) ?? ContractType::Cdi;
            $skills = array_values(array_filter(array_map('trim', explode(',', (string) $request->request->get('requiredSkills')))));
            $missions = array_values(array_filter(array_map('trim', $request->request->all('missions'))));
            $requiredDocuments = array_values(array_unique(array_filter(array_map('trim', $request->request->all('requiredDocuments')))));
            $recommendedDocuments = array_values(array_unique(array_filter(array_map('trim', $request->request->all('recommendedDocuments')))));
            $fullDescription = $description;
            if ([] !== $missions) {
                $fullDescription .= "\n\nMissions principales:\n- " . implode("\n- ", $missions);
            }

            if ([] === $errors) {
                $offer = (new JobOffer())
                    ->setEmployer($user)
                    ->setTitle($title)
                    ->setPositions(max(1, (int) $request->request->get('positions', 1)))
                    ->setDescription($fullDescription)
                    ->setContractType($contract)
                    ->setLocation($location)
                    ->setRequiredEducation($requiredEducation)
                    ->setEducationField('' === $educationField ? null : $educationField)
                    ->setRequiredSkills($skills)
                    ->setRequiredExperienceYears((int) $request->request->get('requiredExperienceYears', 0))
                    ->setRequiredDocuments($requiredDocuments ?: null)
                    ->setRecommendedDocuments($recommendedDocuments ?: null)
                    ->setSalaryMin($this->nullableInt($request->request->get('salaryMin')))
                    ->setSalaryMax($this->nullableInt($request->request->get('salaryMax')))
                    ->setIsRemoteAllowed((bool) $request->request->get('isRemoteAllowed'))
                    ->setStatus(JobOfferStatus::Active);

                if ('' !== $deadline) {
                    $offer->setDeadline(new \DateTimeImmutable($deadline));
                }

                $entityManager->persist($offer);
                $entityManager->flush();
                $this->addFlash('success', 'Votre offre a été publiée avec succès.');

                return $this->redirectToRoute('recruiter_offers');
            }
        }

        return $this->render('recruiter/offer_new.html.twig', ['errors' => $errors]);
    }

    #[Route('/recruteur/candidatures', name: 'recruiter_applications', methods: ['GET'])]
    public function applications(DashboardStatsService $dashboardStats): Response
    {
        $user = $this->requireRecruiterUser();

        return $this->render('recruiter/applications.html.twig', $dashboardStats->getRecruiterApplicationsPage($user));
    }

    #[Route('/recruteur/statistiques', name: 'recruiter_stats', methods: ['GET'])]
    public function stats(DashboardStatsService $dashboardStats): Response
    {
        $user = $this->requireRecruiterUser();

        return $this->render('recruiter/stats.html.twig', $dashboardStats->getRecruiterStatsPage($user));
    }

    #[Route('/recruteur/parametres', name: 'recruiter_settings', methods: ['GET'])]
    public function settings(DashboardStatsService $dashboardStats): Response
    {
        $user = $this->requireRecruiterUser();

        return $this->render('recruiter/settings.html.twig', $dashboardStats->getRecruiterSettingsPage($user));
    }

    #[Route('/go/recruteur', name: 'recruiter_entry', methods: ['GET'])]
    public function entry(): RedirectResponse
    {
        if (null === $this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        return $this->redirectToRoute('recruiter_dashboard');
    }

    private function requireRecruiterUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = trim((string) $value);

        return '' === $value ? null : (int) $value;
    }
}
