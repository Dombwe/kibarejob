<?php

namespace App\Controller;

use App\Entity\CandidateProfile;
use App\Entity\Employer;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthEmailService;
use App\Service\ValidationService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        ValidationService $validationService,
        AuthEmailService $authEmailService,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('recruiter_dashboard');
        }

        $errors = [];
        $form = [
            'email' => $validationService->normalizeEmail($request->request->get('email')),
            'phone' => trim((string) $request->request->get('phone')),
            'companyName' => trim((string) $request->request->get('companyName')),
            'sector' => trim((string) $request->request->get('sector')),
            'countryCode' => strtoupper(trim((string) $request->request->get('countryCode', 'BF'))),
            'countryName' => trim((string) $request->request->get('countryName', 'Burkina Faso')),
            'city' => trim((string) $request->request->get('city', 'Ouagadougou')),
        ];

        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password');
            $passwordConfirm = (string) $request->request->get('passwordConfirm');

            if ('' === $form['email'] || false === filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'][] = 'Email invalide.';
            }
            if ($userRepository->findOneBy(['email' => $form['email']]) instanceof User) {
                $errors['email'][] = 'Cet email est déjà utilisé';
            }
            if (!$validationService->isValidBurkinaPhone($form['phone'] ?: null)) {
                $errors['phone'][] = 'Le téléphone doit respecter le format +226XXXXXXXX.';
            }
            if ('' === $form['companyName']) {
                $errors['companyName'][] = "Le nom de l'entreprise est obligatoire.";
            }
            if ('' === $form['sector']) {
                $errors['sector'][] = "Le secteur d'activité est obligatoire.";
            }
            if (!preg_match('/^[A-Z]{2}$/', $form['countryCode'])) {
                $errors['country'][] = 'Le pays sélectionné est invalide.';
            }
            if ('' === $form['countryName']) {
                $errors['country'][] = 'Le pays est obligatoire.';
            }
            if ('' === $form['city']) {
                $errors['city'][] = 'La ville est obligatoire.';
            }
            if ($password !== $passwordConfirm) {
                $errors['passwordConfirm'][] = 'Les mots de passe ne correspondent pas.';
            }

            $passwordErrors = $validationService->validatePassword($password);
            if ([] !== $passwordErrors) {
                $errors['password'] = $passwordErrors;
            }

            if ([] === $errors) {
                $user = (new User())
                    ->setEmail($form['email'])
                    ->setPhone('' === $form['phone'] ? null : $form['phone'])
                    ->setRoles(['ROLE_EMPLOYER'])
                    ->setProfileCompletedPercent(20)
                    ->setIsEmailVerified(false);
                $user->setPasswordHash($passwordHasher->hashPassword($user, $password));
                $verificationToken = $authEmailService->createEmailVerificationToken($user);

                $employer = (new Employer())
                    ->setUser($user)
                    ->setCompanyName($form['companyName'])
                    ->setSector($form['sector'])
                    ->setCountryCode($form['countryCode'])
                    ->setCountryName($form['countryName'])
                    ->setCities([$form['city']]);

                $entityManager->persist($user);
                $entityManager->persist($employer);
                $entityManager->flush();
                $authEmailService->sendEmailVerification($user, $verificationToken);

                $this->addFlash('success', 'Un email de confirmation a été envoyé dans votre boîte mail.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('auth/register.html.twig', [
            'errors' => $errors,
            'form' => $form,
        ]);
    }

    #[Route('/verify-email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(
        Request $request,
        UserRepository $userRepository,
        AuthEmailService $authEmailService,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $token = (string) $request->query->get('token', '');

        if ('' === $token) {
            $this->addFlash('error', 'Lien de confirmation invalide.');

            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->findOneBy([
            'emailVerificationTokenHash' => $authEmailService->hashToken($token),
        ]);

        if (!$user instanceof User || !$authEmailService->isEmailVerificationTokenValid($user, $token)) {
            $this->addFlash('error', 'Lien de confirmation invalide ou expiré.');

            return $this->redirectToRoute('app_login');
        }

        $authEmailService->markEmailVerified($user);
        $entityManager->flush();

        $this->addFlash('success', 'Adresse email confirmée. Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        ValidationService $validationService,
        AuthEmailService $authEmailService,
        EntityManagerInterface $entityManager,
    ): Response {
        $email = $validationService->normalizeEmail($request->request->get('email'));
        $errors = [];

        if ($request->isMethod('POST')) {
            if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'][] = 'Email invalide.';
            }

            if ([] === $errors) {
                $user = $userRepository->findOneBy(['email' => $email]);

                if ($user instanceof User && $user->isActive() && !$user->isDeleted()) {
                    $token = $authEmailService->createPasswordResetToken($user);
                    $entityManager->flush();
                    $authEmailService->sendPasswordReset($user, $token);
                }

                $this->addFlash('success', 'Si un compte existe avec cet email, un lien de réinitialisation a été envoyé.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('auth/forgot_password.html.twig', [
            'email' => $email,
            'errors' => $errors,
        ]);
    }

    #[Route('/reset-password', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        Request $request,
        UserRepository $userRepository,
        ValidationService $validationService,
        AuthEmailService $authEmailService,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $token = (string) $request->query->get('token', $request->request->get('token', ''));
        $user = '' === $token ? null : $userRepository->findOneBy([
            'passwordResetTokenHash' => $authEmailService->hashToken($token),
        ]);

        if (!$user instanceof User || !$authEmailService->isPasswordResetTokenValid($user, $token)) {
            $this->addFlash('error', 'Lien de réinitialisation invalide ou expiré.');

            return $this->redirectToRoute('app_login');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password');
            $passwordConfirm = (string) $request->request->get('passwordConfirm');

            if ($password !== $passwordConfirm) {
                $errors['passwordConfirm'][] = 'Les mots de passe ne correspondent pas.';
            }

            $passwordErrors = $validationService->validatePassword($password);
            if ([] !== $passwordErrors) {
                $errors['password'] = $passwordErrors;
            }

            if ([] === $errors) {
                $user->setPasswordHash($passwordHasher->hashPassword($user, $password));
                $authEmailService->clearPasswordResetToken($user);
                $authEmailService->markEmailVerified($user);
                $entityManager->flush();

                $this->addFlash('success', 'Mot de passe réinitialisé. Vous pouvez vous connecter.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('auth/reset_password.html.twig', [
            'token' => $token,
            'errors' => $errors,
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('Déconnexion');
    }

    #[Route('/connect/google', name: 'connect_google_start', methods: ['GET'])]
    public function connectGoogle(ClientRegistry $clientRegistry): RedirectResponse
    {
        if ('' === (string) ($_ENV['GOOGLE_CLIENT_ID'] ?? '')) {
            $this->addFlash('error', 'Connexion Google non configurée. Renseigné GOOGLE_CLIENT_ID et GOOGLE_CLIENT_SECRET.');

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
            $this->addFlash('error', 'Impossible de récupérer le compte Google.');

            return $this->redirectToRoute('app_login');
        }

        $email = mb_strtolower($googleUser->getEmail());
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $user = (new User())
                ->setEmail($email)
                ->setRoles(['ROLE_CANDIDATE'])
                ->setProfileCompletedPercent(20)
                ->setIsEmailVerified(true);
            $user->setPasswordHash($passwordHasher->hashPassword($user, bin2hex(random_bytes(24))));

            $profile = (new CandidateProfile())
                ->setUser($user)
                ->setFirstName($googleUser->getFirstName() ?: 'Candidat')
                ->setLastName($googleUser->getLastName() ?: 'KIBARE-JOB')
                ->setPhotoUrl($googleUser->getAvatar())
                ->setCity('Ouagadougou')
                ->setEducationLevel('Aucun')
                ->setSkills([])
                ->setLanguages([['name' => 'Français', 'level' => 'Débutant']])
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
