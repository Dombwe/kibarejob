<?php

namespace App\Controller;

use App\Entity\CandidateProfile;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class WebAuthController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('recruiter_dashboard');
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('Logout is handled by Symfony.');
    }

    #[Route('/connect/google', name: 'connect_google_start', methods: ['GET'])]
    public function connectGoogle(ClientRegistry $clientRegistry): RedirectResponse
    {
        if ('' === (string) ($_ENV['GOOGLE_CLIENT_ID'] ?? '')) {
            $this->addFlash('error', 'Connexion Google non configuree. Renseigne GOOGLE_CLIENT_ID et GOOGLE_CLIENT_SECRET.');

            return $this->redirectToRoute('app_login');
        }

        return $clientRegistry->getClient('google')->redirect(['email', 'profile']);
    }

    #[Route('/connect/google/check', name: 'connect_google_check', methods: ['GET'])]
    public function connectGoogleCheck(
        ClientRegistry $clientRegistry,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
    ): Response {
        $googleUser = $clientRegistry->getClient('google')->fetchUser();

        if (!$googleUser instanceof GoogleUser || null === $googleUser->getEmail()) {
            $this->addFlash('error', 'Impossible de recuperer le compte Google.');

            return $this->redirectToRoute('app_login');
        }

        $email = mb_strtolower($googleUser->getEmail());
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $user = (new User())
                ->setEmail($email)
                ->setRoles(['ROLE_CANDIDATE'])
                ->setProfileCompletedPercent(20);
            $user->setPasswordHash($passwordHasher->hashPassword($user, bin2hex(random_bytes(24))));

            $profile = (new CandidateProfile())
                ->setUser($user)
                ->setFirstName($googleUser->getFirstName() ?: 'Candidat')
                ->setLastName($googleUser->getLastName() ?: 'KIBARE-JOB')
                ->setPhotoUrl($googleUser->getAvatar())
                ->setCity('Ouagadougou')
                ->setEducationLevel('Aucun')
                ->setSkills([])
                ->setLanguages([['name' => 'Francais', 'level' => 'Debutant']])
                ->setAvailability('Immediate');

            $entityManager->persist($user);
            $entityManager->persist($profile);
        }

        $user->setLastLogin(new \DateTimeImmutable());
        $entityManager->flush();

        $response = $security->login($user, 'form_login', 'main');

        return $response ?? $this->redirectToRoute('recruiter_dashboard');
    }
}
