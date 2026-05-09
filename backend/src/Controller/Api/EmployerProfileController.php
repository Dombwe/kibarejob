<?php

namespace App\Controller\Api;

use App\Entity\Employer;
use App\Entity\User;
use App\Service\FileUploadService;
use App\Service\ValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/employer/profile')]
class EmployerProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FileUploadService $fileUploadService,
        private readonly ValidationService $validationService,
    ) {
    }

    #[Route('', name: 'api_employer_profile_get', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        $user = $this->authenticatedUser();
        $profile = $user->getEmployer();

        if (!$profile instanceof Employer) {
            return $this->json(['message' => 'Profil employeur introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json(['profile' => $this->serializeProfile($profile)]);
    }

    #[Route('', name: 'api_employer_profile_update', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $profile = $user->getEmployer();

        if (!$profile instanceof Employer) {
            $profile = (new Employer())->setUser($user);
            $this->entityManager->persist($profile);
        }

        $this->hydrateProfile($profile, $this->jsonPayload($request));
        $user->setUpdatedAt(new \DateTimeImmutable());
        $user->setProfileCompletedPercent($this->calculateCompletion($profile));

        $errors = $this->validationService->validateEntity($profile);
        if ([] !== $errors) {
            return $this->json(['errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->flush();

        return $this->json(['profile' => $this->serializeProfile($profile)]);
    }

    #[Route('/upload-logo', name: 'api_employer_profile_upload_logo', methods: ['POST'])]
    public function uploadLogo(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $profile = $user->getEmployer();

        if (!$profile instanceof Employer) {
            return $this->json(['message' => 'Profil employeur introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('logo');
        $errors = $this->validationService->validateLogoFile($file);

        if ([] !== $errors) {
            return $this->json(['errors' => ['logo' => $errors]], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $upload = $this->fileUploadService->upload($file, 'employers/' . $user->getId() . '/logo');
        $profile->setLogoUrl($upload['url']);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Logo envoyé avec succès.',
            'logoUrl' => $profile->getLogoUrl(),
            'file' => $upload,
        ], JsonResponse::HTTP_CREATED);
    }

    private function authenticatedUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }

        return $user;
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
     * @param array<string, mixed> $payload
     */
    private function hydrateProfile(Employer $profile, array $payload): void
    {
        if (array_key_exists('companyName', $payload) || array_key_exists('company_name', $payload)) {
            $profile->setCompanyName((string) ($payload['companyName'] ?? $payload['company_name']));
        }
        if (array_key_exists('nif', $payload)) {
            $profile->setNif($this->nullableString($payload['nif']));
        }
        if (array_key_exists('sector', $payload)) {
            $profile->setSector((string) $payload['sector']);
        }
        if (array_key_exists('companySize', $payload) || array_key_exists('company_size', $payload)) {
            $profile->setCompanySize($this->nullableString($payload['companySize'] ?? $payload['company_size']));
        }
        if (array_key_exists('cities', $payload) && is_array($payload['cities'])) {
            $profile->setCities($payload['cities']);
        }
        if (array_key_exists('countryCode', $payload) || array_key_exists('country_code', $payload)) {
            $profile->setCountryCode((string) ($payload['countryCode'] ?? $payload['country_code']));
        }
        if (array_key_exists('countryName', $payload) || array_key_exists('country_name', $payload)) {
            $profile->setCountryName((string) ($payload['countryName'] ?? $payload['country_name']));
        }
        if (array_key_exists('description', $payload)) {
            $profile->setDescription($this->nullableString($payload['description']));
        }
        if (array_key_exists('website', $payload)) {
            $profile->setWebsite($this->nullableString($payload['website']));
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    private function calculateCompletion(Employer $profile): int
    {
        $fields = [
            $profile->getCompanyName(),
            $profile->getSector(),
            $profile->getCountryCode(),
            $profile->getCities(),
            $profile->getDescription(),
            $profile->getLogoUrl(),
            $profile->getWebsite(),
        ];

        $filled = 0;
        foreach ($fields as $field) {
            if ((is_array($field) && [] !== $field) || (!is_array($field) && null !== $field && '' !== $field)) {
                ++$filled;
            }
        }

        return (int) round(($filled / count($fields)) * 100);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProfile(Employer $profile): array
    {
        return [
            'userId' => (string) $profile->getUser()->getId(),
            'companyName' => $profile->getCompanyName(),
            'nif' => $profile->getNif(),
            'sector' => $profile->getSector(),
            'companySize' => $profile->getCompanySize(),
            'countryCode' => $profile->getCountryCode(),
            'countryName' => $profile->getCountryName(),
            'cities' => $profile->getCities(),
            'logoUrl' => $profile->getLogoUrl(),
            'description' => $profile->getDescription(),
            'website' => $profile->getWebsite(),
            'subscriptionTier' => $profile->getSubscriptionTier()->value,
            'subscriptionExpiry' => $profile->getSubscriptionExpiry()?->format('Y-m-d'),
            'isValidated' => $profile->isValidated(),
            'offersUsedThisMonth' => $profile->getOffersUsedThisMonth(),
            'applicationsViewedThisMonth' => $profile->getApplicationsViewedThisMonth(),
            'profileCompletedPercent' => $profile->getUser()->getProfileCompletedPercent(),
        ];
    }
}
