<?php

namespace App\Controller\Admin;

use App\Entity\CandidateDocument;
use App\Entity\Employer;
use App\Entity\JobOffer;
use App\Entity\Report;
use App\Entity\User;
use App\Repository\CandidateDocumentRepository;
use App\Repository\EmployerRepository;
use App\Repository\JobOfferRepository;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EmployerRepository $employerRepository,
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly CandidateDocumentRepository $documentRepository,
        private readonly ReportRepository $reportRepository,
    ) {
    }

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'users' => $this->userRepository->count(['isDeleted' => false]),
                'employersToValidate' => $this->employerRepository->count(['isValidated' => false, 'isDeleted' => false]),
                'activeOffers' => $this->jobOfferRepository->count(['status' => \App\Entity\Enum\JobOfferStatus::Active, 'isDeleted' => false]),
                'documentsToModerate' => $this->documentRepository->count(['isVerified' => false, 'isDeleted' => false]),
                'pendingReports' => $this->reportRepository->count(['status' => 'pending']),
            ],
            'latestUsers' => $this->userRepository->findBy(['isDeleted' => false], ['createdAt' => 'DESC'], 10),
            'activeOffers' => $this->jobOfferRepository->findBy(['status' => \App\Entity\Enum\JobOfferStatus::Active, 'isDeleted' => false], ['createdAt' => 'DESC'], 10),
            'flaggedDocuments' => $this->documentRepository->findBy(['isVerified' => false, 'isDeleted' => false], ['uploadedAt' => 'DESC'], 10),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('KIBARE-JOB Admin')
            ->setFaviconPath('/favicon.ico');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('Moderation');
        yield MenuItem::linkToCrud('Utilisateurs', 'fa fa-users', User::class);
        yield MenuItem::linkToCrud('Employeurs', 'fa fa-building', Employer::class);
        yield MenuItem::linkToCrud('Offres', 'fa fa-briefcase', JobOffer::class);
        yield MenuItem::linkToCrud('Documents', 'fa fa-file', CandidateDocument::class);
        yield MenuItem::linkToCrud('Signalements', 'fa fa-flag', Report::class);
    }
}
