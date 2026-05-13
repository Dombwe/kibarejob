<?php

namespace App\Controller\Api;

use App\Entity\CandidateProfile;
use App\Entity\User;
use App\Service\CandidateProfileCompletionService;
use App\Service\CacheService;
use App\Service\FileUploadService;
use App\Service\ScoreCacheService;
use App\Service\ValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profile')]
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FileUploadService $fileUploadService,
        private readonly ValidationService $validationService,
        private readonly CandidateProfileCompletionService $completionService,
        private readonly ScoreCacheService $scoreCacheService,
        private readonly CacheService $cacheService,
    ) {
    }

    #[Route('', name: 'api_profile_get', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        $user = $this->authenticatedUser();
        $profile = $user->getCandidateProfile();

        if (!$profile instanceof CandidateProfile) {
            return $this->json(['message' => 'Profil candidat introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json(['profile' => $this->serializeProfile($profile)]);
    }

    #[Route('', name: 'api_profile_update', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $profile = $user->getCandidateProfile();

        if (!$profile instanceof CandidateProfile) {
            $profile = (new CandidateProfile())->setUser($user);
            $this->entityManager->persist($profile);
        }

        $payload = $this->jsonPayload($request);
        $this->hydrateProfile($profile, $payload);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->completionService->refresh($profile);
        $this->scoreCacheService->invalidateCandidate($profile);
        $this->cacheService->invalidateFeed((string) $user->getId());

        $errors = $this->validationService->validateEntity($profile);
        if ([] !== $errors) {
            return $this->json(['errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->flush();

        return $this->json(['profile' => $this->serializeProfile($profile)]);
    }

    #[Route('/upload-cv', name: 'api_profile_upload_cv', methods: ['POST'])]
    public function uploadCv(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $profile = $user->getCandidateProfile();

        if (!$profile instanceof CandidateProfile) {
            return $this->json(['message' => 'Profil candidat introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('cv');
        $errors = $this->validationService->validateCvFile($file);

        if ([] !== $errors) {
            return $this->json(['errors' => ['cv' => $errors]], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $upload = $this->fileUploadService->upload($file, 'candidates/' . $user->getId() . '/cv');
        $profile
            ->setCvOriginalUrl($upload['url'])
            ->setCvLastUpdated(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->completionService->refresh($profile);

        $this->entityManager->flush();

        return $this->json([
            'message' => 'CV envoyé avec succès.',
            'cvUrl' => $profile->getCvOriginalUrl(),
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
    private function hydrateProfile(CandidateProfile $profile, array $payload): void
    {
        if (array_key_exists('firstName', $payload) || array_key_exists('first_name', $payload)) {
            $profile->setFirstName((string) ($payload['firstName'] ?? $payload['first_name']));
        }
        if (array_key_exists('lastName', $payload) || array_key_exists('last_name', $payload)) {
            $profile->setLastName((string) ($payload['lastName'] ?? $payload['last_name']));
        }
        if (array_key_exists('city', $payload)) {
            $profile->setCity((string) $payload['city']);
        }
        if (array_key_exists('educationLevel', $payload) || array_key_exists('education_level', $payload)) {
            $profile->setEducationLevel((string) ($payload['educationLevel'] ?? ($payload['education_level'] ?? '')));
        }
        if (array_key_exists('educationField', $payload) || array_key_exists('education_field', $payload)) {
            $profile->setEducationField($this->nullableString($payload['educationField'] ?? ($payload['education_field'] ?? null)));
        }
        if (array_key_exists('skills', $payload) && is_array($payload['skills'])) {
            $profile->setSkills($payload['skills']);
        }
        if (array_key_exists('languages', $payload) && is_array($payload['languages'])) {
            $profile->setLanguages($payload['languages']);
        }
        if (array_key_exists('experiences', $payload) && is_array($payload['experiences'])) {
            $profile->setExperiences($this->stringList($payload['experiences']));
        }
        if (array_key_exists('interests', $payload) && is_array($payload['interests'])) {
            $profile->setInterests($this->stringList($payload['interests']));
        }
        if (array_key_exists('references', $payload) && is_array($payload['references'])) {
            $profile->setReferences($this->stringList($payload['references']));
        }
        if (array_key_exists('drivingLicense', $payload) || array_key_exists('driving_license', $payload)) {
            $profile->setDrivingLicense((bool) ($payload['drivingLicense'] ?? ($payload['driving_license'] ?? false)));
        }
        if (array_key_exists('drivingLicenseCategory', $payload) || array_key_exists('driving_license_category', $payload)) {
            $profile->setDrivingLicenseCategory($this->nullableString($payload['drivingLicenseCategory'] ?? ($payload['driving_license_category'] ?? null)));
        }
        if (array_key_exists('availability', $payload)) {
            $profile->setAvailability((string) $payload['availability']);
        }
        if (array_key_exists('salaryExpectation', $payload) || array_key_exists('salary_expectation', $payload)) {
            $value = $payload['salaryExpectation'] ?? ($payload['salary_expectation'] ?? null);
            $profile->setSalaryExpectation(null === $value || '' === $value ? null : (int) $value);
        }
        if (array_key_exists('birthDate', $payload) || array_key_exists('birth_date', $payload)) {
            $value = $payload['birthDate'] ?? ($payload['birth_date'] ?? null);
            $profile->setBirthDate($value ? new \DateTimeImmutable((string) $value) : null);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    /**
     * @param array<int, mixed> $items
     * @return string[]
     */
    private function stringList(array $items): array
    {
        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $items)));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProfile(CandidateProfile $profile): array
    {
        return [
            'userId' => (string) $profile->getUser()->getId(),
            'firstName' => $profile->getFirstName(),
            'lastName' => $profile->getLastName(),
            'photoUrl' => $profile->getPhotoUrl(),
            'birthDate' => $profile->getBirthDate()?->format('Y-m-d'),
            'city' => $profile->getCity(),
            'educationLevel' => $profile->getEducationLevel(),
            'educationField' => $profile->getEducationField(),
            'skills' => $profile->getSkills(),
            'languages' => $profile->getLanguages(),
            'experiences' => $profile->getExperiences(),
            'interests' => $profile->getInterests(),
            'references' => $profile->getReferences(),
            'drivingLicense' => $profile->hasDrivingLicense(),
            'drivingLicenseCategory' => $profile->getDrivingLicenseCategory(),
            'availability' => $profile->getAvailability(),
            'salaryExpectation' => $profile->getSalaryExpectation(),
            'cvOriginalUrl' => $profile->getCvOriginalUrl(),
            'cvGeneratedUrl' => $profile->getCvGeneratedUrl(),
            'cvLastUpdated' => $profile->getCvLastUpdated()?->format(DATE_ATOM),
            'profileCompletedPercent' => $profile->getUser()->getProfileCompletedPercent(),
        ];
    }
}
