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

    #[Route('/google/mobile', name: 'api_auth_google_mobile', methods: ['POST'])]
    public function googleMobile(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $idToken = trim((string) ($payload['idToken'] ?? $payload['id_token'] ?? ''));

        if ('' === $idToken) {
            return $this->json(['message' => 'Jeton Google manquant.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $googleProfile = $this->fetchGoogleTokenInfo($idToken);
        } catch (\RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $allowedAudiences = array_filter(array_map('trim', [
            (string) ($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?: ''),
            (string) ($_ENV['GOOGLE_WEB_CLIENT_ID'] ?? getenv('GOOGLE_WEB_CLIENT_ID') ?: ''),
            (string) ($_ENV['GOOGLE_ANDROID_CLIENT_ID'] ?? getenv('GOOGLE_ANDROID_CLIENT_ID') ?: ''),
        ]));

        if ([] !== $allowedAudiences && !in_array((string) ($googleProfile['aud'] ?? ''), $allowedAudiences, true)) {
            return $this->json(['message' => 'Jeton Google refuse pour cette application.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!in_array($googleProfile['email_verified'] ?? false, [true, 'true', '1', 1], true)) {
            return $this->json(['message' => 'Adresse email Google non verifiee.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $email = $this->validationService->normalizeEmail($googleProfile['email'] ?? null);
        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Impossible de recuperer l email Google.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $user = (new User())
                ->setEmail($email)
                ->setRoles(['ROLE_CANDIDATE'])
                ->setProfileCompletedPercent(20)
                ->setIsEmailVerified(true);
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(24))));
            $this->entityManager->persist($user);
        }

        if (!$user->isActive() || $user->isDeleted()) {
            return $this->json(['message' => 'Compte inactif ou supprime.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $this->ensureMobileCandidateProfile($user, $googleProfile);

        $user
            ->setIsEmailVerified(true)
            ->setLastLogin(new \DateTimeImmutable());
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

        $emailUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($emailUser instanceof User && !$emailUser->isDeleted()) {
            $errors['email'][] = 'Cet email est déjà utilisé.';
        }

        $phoneUser = $phone ? $this->userRepository->findOneBy(['phone' => $phone]) : null;
        if ($phoneUser instanceof User && !$phoneUser->isDeleted()) {
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

        $this->releaseDeletedIdentity($emailUser, $email);
        if ($phoneUser instanceof User && $phoneUser !== $emailUser) {
            $this->releaseDeletedIdentity($phoneUser, $phoneUser->getEmail());
        }
        $this->entityManager->flush();

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
    private function fetchGoogleTokenInfo(string $idToken): array
    {
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken);
        $body = false;

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if (false !== $curl) {
                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_HTTPHEADER => ['Accept: application/json'],
                ]);
                $body = curl_exec($curl);
                $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                curl_close($curl);

                if ($statusCode >= 400) {
                    $body = false;
                }
            }
        }

        if (false === $body) {
            $body = @file_get_contents($url);
        }

        if (false === $body || '' === $body) {
            throw new \RuntimeException('Verification Google impossible pour le moment.');
        }

        $data = json_decode($body, true);
        if (!is_array($data) || isset($data['error'])) {
            throw new \RuntimeException('Jeton Google invalide ou expire.');
        }

        return $data;
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
     * @param array<string, mixed> $googleProfile
     */
    private function ensureMobileCandidateProfile(User $user, array $googleProfile): void
    {
        $roles = array_values(array_filter(
            $user->getRoles(),
            static fn (string $role): bool => 'ROLE_USER' !== $role
        ));

        if (!in_array('ROLE_CANDIDATE', $roles, true)) {
            $roles[] = 'ROLE_CANDIDATE';
            $user->setRoles(array_values(array_unique($roles)));
        }

        $firstName = trim((string) ($googleProfile['given_name'] ?? $user->getFirstName() ?? 'Candidat'));
        $lastName = trim((string) ($googleProfile['family_name'] ?? $user->getLastName() ?? 'KIBARE-JOB'));

        if ('' === $firstName) {
            $firstName = 'Candidat';
        }

        if ('' === $lastName) {
            $lastName = 'KIBARE-JOB';
        }

        if (null === $user->getFirstName() || '' === trim($user->getFirstName())) {
            $user->setFirstName($firstName);
        }

        if (null === $user->getLastName() || '' === trim($user->getLastName())) {
            $user->setLastName($lastName);
        }

        if ($user->getCandidateProfile() instanceof CandidateProfile) {
            return;
        }

        $profile = (new CandidateProfile())
            ->setUser($user)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setPhotoUrl(is_string($googleProfile['picture'] ?? null) ? $googleProfile['picture'] : null)
            ->setCity('Ouagadougou')
            ->setEducationLevel('Aucun')
            ->setSkills([])
            ->setLanguages([['name' => 'Francais', 'level' => 'Debutant']])
            ->setAvailability('Immediate');

        $user->setCandidateProfile($profile);
        $this->entityManager->persist($profile);
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

    private function releaseDeletedIdentity(?User $user, string $requestedEmail): void
    {
        if (!$user instanceof User || !$user->isDeleted()) {
            return;
        }

        $suffix = null !== $user->getId() ? (string) $user->getId() : bin2hex(random_bytes(8));

        $user
            ->setEmail(sprintf('deleted+%s+%s', $suffix, $requestedEmail))
            ->setPhone(null)
            ->setIsActive(false)
            ->setIsEmailVerified(false);
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
