<?php

namespace App\Controller\Admin;

use App\Entity\ApplicationSetting;
use App\Entity\CandidateDocument;
use App\Entity\Employer;
use App\Entity\JobOffer;
use App\Entity\JobImportSource;
use App\Entity\Report;
use App\Entity\ScheduledCommand;
use App\Entity\User;
use App\Repository\CandidateDocumentRepository;
use App\Repository\EmployerRepository;
use App\Repository\JobOfferRepository;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

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
            ->setTitle('KIBARE-JOB')
            ->setFaviconPath('/favicon.ico');
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setPaginatorPageSize(20)
            ->setPaginatorRangeSize(3);
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addHtmlContentToHead(<<<'HTML'
<script>
    (function () {
        var savedTheme = localStorage.getItem('kibarejob-theme');
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.documentElement.classList.add('dark');
        }
    })();
</script>
HTML)
            ->addCssFile('assets/admin.css?v=20260509-cron-actions')
            ->addJsFile('assets/admin-theme.js?v=20260509-cron-actions');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::section('Modération');
        yield MenuItem::linkToCrud('Utilisateurs', 'fa fa-users', User::class);
        yield MenuItem::linkToCrud('Employeurs', 'fa fa-building', Employer::class);
        yield MenuItem::linkToCrud('Offres internes', 'fa fa-briefcase', JobOffer::class);
        yield MenuItem::linkToCrud('Offres externes', 'fa fa-earth-africa', JobOffer::class)
            ->setController(ExternalJobOfferCrudController::class);
        yield MenuItem::linkToCrud('Sources d’offres', 'fa fa-cloud-arrow-down', JobImportSource::class);
        yield MenuItem::section('Automatisation');
        yield MenuItem::linkToCrud('Commandes / Cron', 'fa fa-clock', ScheduledCommand::class);
        yield MenuItem::section('Configuration');
        yield MenuItem::linkToCrud('Serveur API mobile', 'fa fa-network-wired', ApplicationSetting::class);
        yield MenuItem::section('Contenu');
        yield MenuItem::linkToCrud('Documents', 'fa fa-file', CandidateDocument::class);
        yield MenuItem::linkToCrud('Signalements', 'fa fa-flag', Report::class);
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        $displayName = $user instanceof User ? $user->getDisplayName() : $user->getUserIdentifier();
        $menuItems = [
            MenuItem::section('Compte'),
        ];

        if ($user instanceof User && null !== $user->getId()) {
            $menuItems[] = MenuItem::linkToCrud('Modifier mes informations', 'fa fa-user-pen', User::class)
                ->setAction('edit')
                ->setEntityId((string) $user->getId());
        }

        $menuItems[] = MenuItem::linkToLogout('Déconnexion', 'fa fa-right-from-bracket');

        return UserMenu::new()
            ->displayUserName()
            ->displayUserAvatar()
            ->setName($displayName)
            ->setAvatarUrl(null)
            ->setMenuItems($menuItems);
    }
}



