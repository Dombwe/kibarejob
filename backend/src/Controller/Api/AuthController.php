<?php

namespace App\Controller\Api;

use App\Entity\CandidateProfile;
use App\Entity\Employer;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthEmailService;
use App\Service\ValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        private readonly ValidationService $validationService,
        private readonly AuthEmailService $authEmailService,
    ) {
    }

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $email = $this->validationService->normalizeEmail($payload['email'] ?? null);
        $password = (string) ($payload['password'] ?? '');
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user instanceof User || !$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new BadCredentialsException('Identifiants invalides.');
        }

        if (!$user->isActive() || $user->isDeleted()) {
            return $this->json(['message' => 'Compte inactif ou supprime.'], JsonResponse::HTTP_FORBIDDEN);
        }

        if (!$user->isEmailVerified()) {
            return $this->json([
                'message' => 'Adresse email non confirmée. Vérifiez votre boîte mail avant de vous connecter.',
                'emailVerificationRequired' => true,
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $user->setLastLogin(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json([
            'token' => $this->jwtManager->create($user),
            ...$this->issueRefreshToken($user),
            'user' => $this->serializeUser($user),
        ]);
    }

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $email = $this->validationService->normalizeEmail($payload['email'] ?? null);
        $phone = $payload['phone'] ?? null;
        $password = (string) ($payload['password'] ?? '');
        $accountType = (string) ($payload['accountType'] ?? $payload['account_type'] ?? 'candidat');

        $errors = [];
        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Email invalide.';
        }

        if (!$this->validationService->isValidBurkinaPhone($phone)) {
            $errors['phone'][] = 'Le téléphone doit respecter le format +226XXXXXXXX.';
        }

        if ($this->userRepository->findOneBy(['email' => $email]) instanceof User) {
            $errors['email'][] = 'Cet email est déjà utilisé.';
        }

        if ($phone && $this->userRepository->findOneBy(['phone' => $phone]) instanceof User) {
            $errors['phone'][] = 'Ce téléphone est déjà utilisé.';
        }

        $passwordErrors = $this->validationService->validatePassword($password);
        if ([] !== $passwordErrors) {
            $errors['password'] = $passwordErrors;
        }

        if (!in_array($accountType, ['candidat', 'employeur'], true)) {
            $errors['accountType'][] = 'Type de compte invalide.';
        }

        if ([] !== $errors) {
            return $this->json(['errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = (new User())
            ->setEmail($email)
            ->setPhone($phone)
            ->setRoles([$accountType === 'employeur' ? 'ROLE_EMPLOYER' : 'ROLE_CANDIDATE'])
            ->setProfileCompletedPercent(10)
            ->setIsEmailVerified(false);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
        $verificationToken = $this->authEmailService->createEmailVerificationToken($user);

        if ('employeur' === $accountType) {
            $profile = (new Employer())
                ->setUser($user)
                ->setCompanyName((string) ($payload['companyName'] ?? $payload['company_name'] ?? 'Entreprise'))
                ->setSector((string) ($payload['sector'] ?? 'Non renseigné'))
                ->setCountryCode((string) ($payload['countryCode'] ?? $payload['country_code'] ?? 'BF'))
                ->setCountryName((string) ($payload['countryName'] ?? $payload['country_name'] ?? 'Burkina Faso'))
                ->setCities($this->arrayValue($payload['cities'] ?? ['Ouagadougou']));
            $this->entityManager->persist($profile);
        } else {
            $profile = (new CandidateProfile())
                ->setUser($user)
                ->setFirstName((string) ($payload['firstName'] ?? $payload['first_name'] ?? 'Candidat'))
                ->setLastName((string) ($payload['lastName'] ?? $payload['last_name'] ?? 'KIBARE-JOB'))
                ->setCity((string) ($payload['city'] ?? 'Ouagadougou'))
                ->setEducationLevel((string) ($payload['educationLevel'] ?? $payload['education_level'] ?? 'Aucun'))
                ->setSkills($this->arrayValue($payload['skills'] ?? []))
                ->setLanguages($this->arrayValue($payload['languages'] ?? [['name' => 'Français', 'level' => 'Débutant']]))
                ->setAvailability((string) ($payload['availability'] ?? 'Immediate'));
            $this->entityManager->persist($profile);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->authEmailService->sendEmailVerification($user, $verificationToken);

        $response = [
            'message' => 'Compte créé. Un email de confirmation vient de vous être envoyé.',
            'emailVerificationRequired' => true,
            'user' => $this->serializeUser($user),
        ];

        return $this->json($this->withLocalDebugToken($response, 'verificationToken', $verificationToken), JsonResponse::HTTP_CREATED);
    }

    #[Route('/verify-email', name: 'api_auth_verify_email', methods: ['GET', 'POST'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $token = (string) ($payload['token'] ?? $request->query->get('token', ''));

        if ('' === $token) {
            return $this->json(['message' => 'Token de confirmation manquant.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy([
            'emailVerificationTokenHash' => $this->authEmailService->hashToken($token),
        ]);

        if (!$user instanceof User || !$this->authEmailService->isEmailVerificationTokenValid($user, $token)) {
            return $this->json(['message' => 'Lien de confirmation invalide ou expiré.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->authEmailService->markEmailVerified($user);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Adresse email confirmée. Vous pouvez maintenant vous connecter.',
            'user' => $this->serializeUser($user),
        ]);
    }

    #[Route('/resend-verification', name: 'api_auth_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $email = $this->validationService->normalizeEmail($payload['email'] ?? null);
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user instanceof User && !$user->isDeleted() && !$user->isEmailVerified()) {
            $token = $this->authEmailService->createEmailVerificationToken($user);
            $this->entityManager->flush();
            $this->authEmailService->sendEmailVerification($user, $token);
        }

        $response = [
            'message' => 'Si un compte non confirmé existe avec cet email, un nouveau lien a été envoyé.',
        ];

        return $this->json(isset($token) ? $this->withLocalDebugToken($response, 'verificationToken', $token) : $response);
    }

    #[Route('/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $email = $this->validationService->normalizeEmail($payload['email'] ?? null);
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user instanceof User && $user->isActive() && !$user->isDeleted()) {
            $token = $this->authEmailService->createPasswordResetToken($user);
            $this->entityManager->flush();
            $this->authEmailService->sendPasswordReset($user, $token);
        }

        $response = [
            'message' => 'Si un compte existe avec cet email, un lien de réinitialisation a été envoyé.',
        ];

        return $this->json(isset($token) ? $this->withLocalDebugToken($response, 'passwordResetToken', $token) : $response);
    }

    #[Route('/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $token = (string) ($payload['token'] ?? $request->query->get('token', ''));
        $password = (string) ($payload['password'] ?? '');

        if ('' === $token) {
            return $this->json(['message' => 'Token de réinitialisation manquant.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $passwordErrors = $this->validationService->validatePassword($password);
        if ([] !== $passwordErrors) {
            return $this->json(['errors' => ['password' => $passwordErrors]], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->userRepository->findOneBy([
            'passwordResetTokenHash' => $this->authEmailService->hashToken($token),
        ]);

        if (!$user instanceof User || !$this->authEmailService->isPasswordResetTokenValid($user, $token)) {
            return $this->json(['message' => 'Lien de réinitialisation invalide ou expiré.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
        $this->authEmailService->clearPasswordResetToken($user);
        $this->authEmailService->markEmailVerified($user);
        $this->entityManager->flush();

        return $this->json(['message' => 'Mot de passe réinitialisé. Vous pouvez vous connecter.']);
    }

    #[Route('/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $tokenValue = (string) ($payload['refresh_token'] ?? $request->request->get('refresh_token', ''));

        if ('' === $tokenValue) {
            return $this->json(['message' => 'Refresh token manquant.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $refreshToken = $this->refreshTokenManager->get($tokenValue);
        if (null === $refreshToken || !$refreshToken->isValid()) {
            return $this->json(['message' => 'Refresh token invalide ou expiré.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $user = $this->userRepository->findOneBy(['email' => $refreshToken->getUsername()]);
        if (!$user instanceof User || !$user->isActive() || $user->isDeleted() || !$user->isEmailVerified()) {
            return $this->json(['message' => 'Utilisateur introuvable ou inactif.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'token' => $this->jwtManager->create($user),
            'refresh_token' => $refreshToken->getRefreshToken(),
            'refresh_token_expiration' => $refreshToken->getValid()?->getTimestamp(),
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param mixed $value
     *
     * @return array<int|string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'roles' => $user->getRoles(),
            'isEmailVerified' => $user->isEmailVerified(),
            'profileCompletedPercent' => $user->getProfileCompletedPercent(),
            'subscriptionTier' => $user->getSubscriptionTier()->value,
        ];
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    private function withLocalDebugToken(array $response, string $key, string $token): array
    {
        if ('prod' === $this->getParameter('kernel.environment')) {
            return $response;
        }

        $response['debug'][$key] = $token;

        return $response;
    }

    /**
     * @return array{refresh_token: string|null, refresh_token_expiration: int|null}
     */
    private function issueRefreshToken(User $user): array
    {
        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, 2592000);
        $this->refreshTokenManager->save($refreshToken);

        return [
            'refresh_token' => $refreshToken->getRefreshToken(),
            'refresh_token_expiration' => $refreshToken->getValid()?->getTimestamp(),
        ];
    }
}
