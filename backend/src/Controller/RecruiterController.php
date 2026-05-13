<?php

namespace App\Controller;

use App\Entity\Enum\ContractType;
use App\Entity\Enum\JobOfferStatus;
use App\Entity\Enum\SwipeStatus;
use App\Entity\JobOffer;
use App\Entity\Notification;
use App\Entity\Swipe;
use App\Entity\User;
use App\Service\DashboardStatsService;
use App\Service\NotificationService;
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

        return $this->render('recruiter/offers.html.twig', $this->withRecruiterNotifications(
            $dashboardStats->getRecruiterOffersPage($user),
            $dashboardStats,
            $user
        ));
    }

    #[Route('/recruteur/offres/nouvelle', name: 'recruiter_offer_new', methods: ['GET', 'POST'])]
    public function newOffer(Request $request, EntityManagerInterface $entityManager, DashboardStatsService $dashboardStats): Response
    {
        $user = $this->requireRecruiterUser();
        $errors = [];

        if ($request->isMethod('POST')) {
            $publishAction = (string) $request->request->get('publishAction', 'publish');
            $publishMode = (string) $request->request->get('publishMode', 'now');
            $title = trim((string) $request->request->get('title'));
            $description = trim((string) $request->request->get('description'));
            $contractType = (string) $request->request->get('contractType', ContractType::Cdi->value);
            $location = trim((string) $request->request->get('location'));
            $requiredEducation = (string) $request->request->get('requiredEducation', 'Aucun');
            $educationField = trim((string) $request->request->get('educationField'));
            $requiredExperienceYears = max(0, (int) $request->request->get('requiredExperienceYears', 0));
            $requiredExperienceYearsMax = $this->nullableInt($request->request->get('requiredExperienceYearsMax'));
            $deadline = (string) $request->request->get('deadline');
            $scheduledPublishAt = trim((string) $request->request->get('scheduledPublishAt'));
            $isDraft = 'draft' === $publishAction;

            if (!$isDraft && '' === $title) {
                $errors['title'][] = 'Le titre du poste est obligatoire.';
            }
            if (!$isDraft && '' === $description) {
                $errors['description'][] = 'La description du poste est obligatoire.';
            }
            if (!$isDraft && '' === $location) {
                $errors['location'][] = 'Le lieu de travail est obligatoire.';
            }
            if (!$isDraft && 'scheduled' === $publishMode && '' === $scheduledPublishAt) {
                $errors['scheduledPublishAt'][] = 'Indiquez une date de publication.';
            }
            if (null !== $requiredExperienceYearsMax && $requiredExperienceYearsMax < $requiredExperienceYears) {
                $errors['requiredExperienceYearsMax'][] = 'L experience maximum doit etre superieure ou egale au minimum.';
            }

            $contract = ContractType::tryFrom($contractType) ?? ContractType::Cdi;
            $skills = array_values(array_filter(array_map('trim', explode(',', (string) $request->request->get('requiredSkills')))));
            $missions = array_values(array_filter(array_map('trim', $request->request->all('missions'))));
            $requiredDocuments = array_values(array_unique(array_filter(array_map('trim', $request->request->all('requiredDocuments')))));
            $recommendedDocuments = array_values(array_unique(array_filter(array_map('trim', $request->request->all('recommendedDocuments')))));
            $fullDescription = '' === $description ? 'Description à compléter.' : $description;
            if ([] !== $missions) {
                $fullDescription .= "\n\nMissions principales:\n- " . implode("\n- ", $missions);
            }

            if ([] === $errors) {
                $publicationDate = null;
                if (!$isDraft && 'scheduled' === $publishMode && '' !== $scheduledPublishAt) {
                    $publicationDate = new \DateTimeImmutable($scheduledPublishAt);
                }

                $status = JobOfferStatus::Active;
                if ($isDraft || ($publicationDate instanceof \DateTimeImmutable && $publicationDate > new \DateTimeImmutable())) {
                    $status = JobOfferStatus::Draft;
                }

                $offer = (new JobOffer())
                    ->setEmployer($user)
                    ->setTitle('' === $title ? 'Offre sans titre' : $title)
                    ->setPositions(max(1, (int) $request->request->get('positions', 1)))
                    ->setDescription($fullDescription)
                    ->setContractType($contract)
                    ->setLocation('' === $location ? 'Lieu à renseigner' : $location)
                    ->setRequiredEducation($requiredEducation)
                    ->setEducationField('' === $educationField ? null : $educationField)
                    ->setRequiredSkills($skills)
                    ->setRequiredExperienceYears($requiredExperienceYears)
                    ->setRequiredExperienceYearsMax($requiredExperienceYearsMax)
                    ->setRequiredDocuments($requiredDocuments ?: null)
                    ->setRecommendedDocuments($recommendedDocuments ?: null)
                    ->setSalaryMin($this->nullableInt($request->request->get('salaryMin')))
                    ->setSalaryMax($this->nullableInt($request->request->get('salaryMax')))
                    ->setIsRemoteAllowed((bool) $request->request->get('isRemoteAllowed'))
                    ->setApplicationEmail($user->getEmail())
                    ->setScheduledPublishAt($status === JobOfferStatus::Draft ? $publicationDate : null)
                    ->setStatus($status);

                if ('' !== $deadline) {
                    $offer->setDeadline(new \DateTimeImmutable($deadline));
                }

                $entityManager->persist($offer);
                $entityManager->flush();
                $this->addFlash('success', match (true) {
                    $isDraft => 'Votre offre a été enregistrée en brouillon.',
                    $offer->getScheduledPublishAt() instanceof \DateTimeImmutable => 'Votre offre a été planifiée avec succès.',
                    default => 'Votre offre a été publiée avec succès.',
                });

                return $this->redirectToRoute('recruiter_offers');
            }
        }

        return $this->render('recruiter/offer_new.html.twig', $this->withRecruiterNotifications(
            [
                'errors' => $errors,
                'offerMode' => 'create',
                'offerForm' => $this->offerFormContext(),
            ],
            $dashboardStats,
            $user
        ));
    }

    #[Route('/recruteur/offres/{id}/modifier', name: 'recruiter_offer_edit', methods: ['GET', 'POST'])]
    public function editOffer(string $id, Request $request, EntityManagerInterface $entityManager, DashboardStatsService $dashboardStats): Response
    {
        $user = $this->requireRecruiterUser();
        $offer = $this->requireOwnedOffer($user, $id, $entityManager);
        if (JobOfferStatus::Active === $offer->getStatus()) {
            $this->addFlash('error', 'Une offre publiée ne peut plus être modifiée. Vous pouvez la clôturer puis créer une nouvelle version.');

            return $this->redirectToRoute('recruiter_offers');
        }

        $errors = [];
        if ($request->isMethod('POST')) {
            $publishAction = (string) $request->request->get('publishAction', 'draft');
            $publishMode = (string) $request->request->get('publishMode', 'now');
            $title = trim((string) $request->request->get('title'));
            $description = trim((string) $request->request->get('description'));
            $contractType = (string) $request->request->get('contractType', ContractType::Cdi->value);
            $location = trim((string) $request->request->get('location'));
            $requiredEducation = (string) $request->request->get('requiredEducation', 'Aucun');
            $educationField = trim((string) $request->request->get('educationField'));
            $requiredExperienceYears = max(0, (int) $request->request->get('requiredExperienceYears', 0));
            $requiredExperienceYearsMax = $this->nullableInt($request->request->get('requiredExperienceYearsMax'));
            $deadline = (string) $request->request->get('deadline');
            $scheduledPublishAt = trim((string) $request->request->get('scheduledPublishAt'));
            $isDraft = 'draft' === $publishAction;

            if (!$isDraft && '' === $title) {
                $errors['title'][] = 'Le titre du poste est obligatoire.';
            }
            if (!$isDraft && '' === $description) {
                $errors['description'][] = 'La description du poste est obligatoire.';
            }
            if (!$isDraft && '' === $location) {
                $errors['location'][] = 'Le lieu de travail est obligatoire.';
            }
            if (!$isDraft && 'scheduled' === $publishMode && '' === $scheduledPublishAt) {
                $errors['scheduledPublishAt'][] = 'Indiquez une date de publication.';
            }
            if (null !== $requiredExperienceYearsMax && $requiredExperienceYearsMax < $requiredExperienceYears) {
                $errors['requiredExperienceYearsMax'][] = 'L experience maximum doit etre superieure ou egale au minimum.';
            }

            if ([] === $errors) {
                $contract = ContractType::tryFrom($contractType) ?? ContractType::Cdi;
                $skills = array_values(array_filter(array_map('trim', explode(',', (string) $request->request->get('requiredSkills')))));
                $missions = array_values(array_filter(array_map('trim', $request->request->all('missions'))));
                $requiredDocuments = array_values(array_unique(array_filter(array_map('trim', $request->request->all('requiredDocuments')))));
                $recommendedDocuments = array_values(array_unique(array_filter(array_map('trim', $request->request->all('recommendedDocuments')))));
                $fullDescription = '' === $description ? 'Description à compléter.' : $description;
                if ([] !== $missions) {
                    $fullDescription .= "\n\nMissions principales:\n- " . implode("\n- ", $missions);
                }

                $publicationDate = null;
                if (!$isDraft && 'scheduled' === $publishMode && '' !== $scheduledPublishAt) {
                    $publicationDate = new \DateTimeImmutable($scheduledPublishAt);
                }

                $status = JobOfferStatus::Active;
                if ($isDraft || ($publicationDate instanceof \DateTimeImmutable && $publicationDate > new \DateTimeImmutable())) {
                    $status = JobOfferStatus::Draft;
                }

                $offer
                    ->setTitle('' === $title ? 'Offre sans titre' : $title)
                    ->setPositions(max(1, (int) $request->request->get('positions', 1)))
                    ->setDescription($fullDescription)
                    ->setContractType($contract)
                    ->setLocation('' === $location ? 'Lieu à renseigner' : $location)
                    ->setRequiredEducation($requiredEducation)
                    ->setEducationField('' === $educationField ? null : $educationField)
                    ->setRequiredSkills($skills)
                    ->setRequiredExperienceYears($requiredExperienceYears)
                    ->setRequiredExperienceYearsMax($requiredExperienceYearsMax)
                    ->setRequiredDocuments($requiredDocuments ?: null)
                    ->setRecommendedDocuments($recommendedDocuments ?: null)
                    ->setSalaryMin($this->nullableInt($request->request->get('salaryMin')))
                    ->setSalaryMax($this->nullableInt($request->request->get('salaryMax')))
                    ->setIsRemoteAllowed((bool) $request->request->get('isRemoteAllowed'))
                    ->setApplicationEmail($offer->getApplicationEmail() ?: $user->getEmail())
                    ->setScheduledPublishAt($status === JobOfferStatus::Draft ? $publicationDate : null)
                    ->setStatus($status);

                if ('' !== $deadline) {
                    $offer->setDeadline(new \DateTimeImmutable($deadline));
                }

                $entityManager->flush();
                $this->addFlash('success', match (true) {
                    $isDraft => 'Les modifications ont été enregistrées en brouillon.',
                    $offer->getScheduledPublishAt() instanceof \DateTimeImmutable => 'Les modifications ont été enregistrées et la publication est planifiée.',
                    default => 'L’offre a été publiée avec les dernières modifications.',
                });

                return $this->redirectToRoute('recruiter_offers');
            }
        }

        return $this->render('recruiter/offer_new.html.twig', $this->withRecruiterNotifications(
            [
                'errors' => $errors,
                'offerMode' => 'edit',
                'offerForm' => $this->offerFormContext($offer),
            ],
            $dashboardStats,
            $user
        ));
    }

    #[Route('/recruteur/offres/{id}/candidatures', name: 'recruiter_offer_applications', methods: ['GET'])]
    public function offerApplications(string $id, EntityManagerInterface $entityManager, DashboardStatsService $dashboardStats): Response
    {
        $user = $this->requireRecruiterUser();
        $offer = $this->requireOwnedOffer($user, $id, $entityManager);

        return $this->render('recruiter/applications.html.twig', $this->withRecruiterNotifications(
            $dashboardStats->getRecruiterApplicationsPage($user, $offer),
            $dashboardStats,
            $user
        ));
    }

    #[Route('/recruteur/offres/{id}/publier', name: 'recruiter_offer_publish', methods: ['POST'])]
    public function publishOffer(string $id, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $user = $this->requireRecruiterUser();
        $offer = $this->requireOwnedOffer($user, $id, $entityManager);
        if (!$this->isCsrfTokenValid('offer_publish_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $offer->setStatus(JobOfferStatus::Active)->setScheduledPublishAt(null);
        $entityManager->flush();
        $this->addFlash('success', 'L’offre est maintenant publiée.');

        return $this->redirectToRoute('recruiter_offers');
    }

    #[Route('/recruteur/offres/{id}/cloturer', name: 'recruiter_offer_close', methods: ['POST'])]
    public function closeOffer(string $id, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $user = $this->requireRecruiterUser();
        $offer = $this->requireOwnedOffer($user, $id, $entityManager);
        if (!$this->isCsrfTokenValid('offer_close_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $offer->setStatus(JobOfferStatus::Closed)->setScheduledPublishAt(null);
        $entityManager->flush();
        $this->addFlash('success', 'L’offre a été clôturée.');

        return $this->redirectToRoute('recruiter_offers');
    }

    #[Route('/recruteur/offres/{id}/supprimer', name: 'recruiter_offer_delete', methods: ['POST'])]
    public function deleteOffer(string $id, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $user = $this->requireRecruiterUser();
        $offer = $this->requireOwnedOffer($user, $id, $entityManager);
        if (!$this->isCsrfTokenValid('offer_delete_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $offer->setIsDeleted(true)->setStatus(JobOfferStatus::Closed)->setScheduledPublishAt(null);
        $entityManager->flush();
        $this->addFlash('success', 'L’offre a été supprimée.');

        return $this->redirectToRoute('recruiter_offers');
    }

    #[Route('/recruteur/candidatures', name: 'recruiter_applications', methods: ['GET'])]
    public function applications(DashboardStatsService $dashboardStats): Response
    {
        $user = $this->requireRecruiterUser();

        return $this->render('recruiter/applications.html.twig', $this->withRecruiterNotifications(
            $dashboardStats->getRecruiterApplicationsPage($user),
            $dashboardStats,
            $user
        ));
    }

    #[Route('/recruteur/candidatures/{id}/statut/{status}', name: 'recruiter_application_status', methods: ['POST'])]
    public function updateApplicationStatus(
        string $id,
        string $status,
        Request $request,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService,
    ): RedirectResponse {
        $user = $this->requireRecruiterUser();
        $swipe = $this->requireOwnedApplication($user, $id, $entityManager);
        if (!$this->isCsrfTokenValid('application_status_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $newStatus = SwipeStatus::tryFrom($status);
        if (!$newStatus instanceof SwipeStatus) {
            $this->addFlash('error', 'Statut de candidature invalide.');

            return $this->redirectToApplications($swipe);
        }

        $swipe->setStatus($newStatus);
        if (SwipeStatus::Viewed === $newStatus && null === $swipe->getViewedAt()) {
            $swipe->setViewedAt(new \DateTimeImmutable());
        }

        $notificationService->notify(
            $swipe->getCandidate(),
            'application_status',
            'Statut de candidature mis à jour',
            sprintf('Votre candidature pour %s est maintenant : %s.', $swipe->getOffer()->getTitle(), $this->candidateStatusLabel($newStatus)),
            [
                'swipeId' => (string) $swipe->getId(),
                'offerId' => (string) $swipe->getOffer()->getId(),
                'status' => $newStatus->value,
            ],
        );

        $entityManager->flush();
        $this->addFlash('success', 'Le statut de la candidature a été mis à jour.');

        return $this->redirectToApplications($swipe);
    }

    #[Route('/recruteur/notifications/lues', name: 'recruiter_notifications_read_all', methods: ['POST'])]
    public function markNotificationsRead(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $user = $this->requireRecruiterUser();
        if (!$this->isCsrfTokenValid('recruiter_notifications_read_all', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $notifications = $entityManager->getRepository(Notification::class)->findBy([
            'user' => $user,
            'isDeleted' => false,
            'isRead' => false,
        ]);

        foreach ($notifications as $notification) {
            if ($notification instanceof Notification) {
                $notification->setIsRead(true);
            }
        }

        $entityManager->flush();
        $this->addFlash('success', 'Vos notifications ont été marquées comme lues.');

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('recruiter_dashboard'));
    }

    #[Route('/recruteur/statistiques', name: 'recruiter_stats', methods: ['GET'])]
    public function stats(DashboardStatsService $dashboardStats): Response
    {
        $user = $this->requireRecruiterUser();

        return $this->render('recruiter/stats.html.twig', $this->withRecruiterNotifications(
            $dashboardStats->getRecruiterStatsPage($user),
            $dashboardStats,
            $user
        ));
    }

    #[Route('/recruteur/parametres', name: 'recruiter_settings', methods: ['GET'])]
    public function settings(DashboardStatsService $dashboardStats): Response
    {
        $user = $this->requireRecruiterUser();

        return $this->render('recruiter/settings.html.twig', $this->withRecruiterNotifications(
            $dashboardStats->getRecruiterSettingsPage($user),
            $dashboardStats,
            $user
        ));
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

    private function requireOwnedOffer(User $user, string $id, EntityManagerInterface $entityManager): JobOffer
    {
        $offer = $entityManager->getRepository(JobOffer::class)->find($id);
        if (!$offer instanceof JobOffer || $offer->isDeleted() || $offer->getEmployer() !== $user) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        return $offer;
    }

    private function requireOwnedApplication(User $user, string $id, EntityManagerInterface $entityManager): Swipe
    {
        $swipe = $entityManager->getRepository(Swipe::class)->find($id);
        if (!$swipe instanceof Swipe || $swipe->isDeleted() || $swipe->getOffer()->isDeleted() || $swipe->getOffer()->getEmployer() !== $user) {
            throw $this->createNotFoundException('Candidature introuvable.');
        }

        return $swipe;
    }

    private function redirectToApplications(Swipe $swipe): RedirectResponse
    {
        return $this->redirectToRoute('recruiter_offer_applications', [
            'id' => (string) $swipe->getOffer()->getId(),
        ]);
    }

    private function candidateStatusLabel(SwipeStatus $status): string
    {
        return match ($status) {
            SwipeStatus::Sent => 'envoyée',
            SwipeStatus::Viewed => 'consultée',
            SwipeStatus::Interview => 'entretien',
            SwipeStatus::Rejected => 'refusée',
            SwipeStatus::Hired => 'retenue',
        };
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = trim((string) $value);

        return '' === $value ? null : (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function offerFormContext(?JobOffer $offer = null): array
    {
        if (!$offer instanceof JobOffer) {
            return [
                'title' => '',
                'positions' => 1,
                'description' => '',
                'contractType' => ContractType::Cdi->value,
                'location' => '',
                'isRemoteAllowed' => false,
                'deadline' => '',
                'salaryMin' => '',
                'salaryMax' => '',
                'requiredEducation' => 'Bac +3',
                'educationField' => '',
                'requiredExperienceYears' => 0,
                'requiredExperienceYearsMax' => '',
                'requiredSkills' => 'JavaScript, React, Node.js',
                'requiredDocuments' => ['CV', 'Lettre de motivation'],
                'recommendedDocuments' => [],
                'publishMode' => 'now',
                'scheduledPublishAt' => '',
            ];
        }

        return [
            'title' => $offer->getTitle(),
            'positions' => $offer->getPositions(),
            'description' => $offer->getDescription(),
            'contractType' => $offer->getContractType()->value,
            'location' => $offer->getLocation(),
            'isRemoteAllowed' => $offer->isRemoteAllowed(),
            'deadline' => $offer->getDeadline()->format('Y-m-d'),
            'salaryMin' => $offer->getSalaryMin(),
            'salaryMax' => $offer->getSalaryMax(),
            'requiredEducation' => $offer->getRequiredEducation(),
            'educationField' => $offer->getEducationField() ?? '',
            'requiredExperienceYears' => $offer->getRequiredExperienceYears(),
            'requiredExperienceYearsMax' => $offer->getRequiredExperienceYearsMax(),
            'requiredSkills' => implode(', ', $offer->getRequiredSkills()),
            'requiredDocuments' => $offer->getRequiredDocuments() ?: ['CV', 'Lettre de motivation'],
            'recommendedDocuments' => $offer->getRecommendedDocuments() ?: [],
            'publishMode' => $offer->getScheduledPublishAt() instanceof \DateTimeImmutable ? 'scheduled' : 'now',
            'scheduledPublishAt' => $offer->getScheduledPublishAt()?->format('Y-m-d\TH:i') ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function withRecruiterNotifications(array $context, DashboardStatsService $dashboardStats, User $user): array
    {
        $context['recruiterNotifications'] = $dashboardStats->getRecruiterNotifications($user);

        return $context;
    }
}
