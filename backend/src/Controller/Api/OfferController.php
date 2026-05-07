<?php

namespace App\Controller\Api;

use App\Entity\Employer;
use App\Entity\Enum\ContractType;
use App\Entity\Enum\JobOfferStatus;
use App\Entity\JobOffer;
use App\Entity\User;
use App\Repository\JobOfferRepository;
use App\Service\CacheService;
use App\Service\ScoreCacheService;
use App\Service\SubscriptionService;
use App\Service\ValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/employer/offers')]
class OfferController extends AbstractController
{
    public function __construct(
        private readonly JobOfferRepository $offerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SubscriptionService $subscriptionService,
        private readonly ValidationService $validationService,
        private readonly CacheService $cacheService,
        private readonly ScoreCacheService $scoreCacheService,
    ) {
    }

    #[Route('', name: 'api_employer_offers_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $employer = $this->getEmployer();

        if (!$this->subscriptionService->canCreateOffer($employer)) {
            return $this->json([
                'success' => false,
                'error' => 'Limite d offres gratuites atteinte. Abonnez-vous au plan Standard ou Pro pour creer plus d offres.',
                'remaining_offers' => $this->subscriptionService->getRemainingOffers($employer),
                'upgrade_url' => '/api/subscription/plans',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $payload = $this->jsonPayload($request);
        $offer = (new JobOffer())->setEmployer($employer->getUser());
        $this->hydrateOffer($offer, $payload);

        $errors = $this->validationService->validateEntity($offer);
        if ([] !== $errors) {
            return $this->json(['success' => false, 'errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $employer->setOffersUsedThisMonth($employer->getOffersUsedThisMonth() + 1);
        $this->entityManager->persist($offer);
        $this->entityManager->flush();

        $this->cacheService->invalidateFeed();

        return $this->json([
            'success' => true,
            'offer' => $this->serializeOffer($offer),
            'remaining_offers' => $this->subscriptionService->getRemainingOffers($employer),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('', name: 'api_employer_offers_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $employer = $this->getEmployer();
        $offers = $this->offerRepository->findBy([
            'employer' => $employer->getUser(),
            'isDeleted' => false,
        ], ['createdAt' => 'DESC']);

        return $this->json([
            'offers' => array_map(fn (JobOffer $offer): array => $this->serializeOffer($offer), $offers),
            'remaining_offers' => $this->subscriptionService->getRemainingOffers($employer),
        ]);
    }

    #[Route('/{id}', name: 'api_employer_offers_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $offer = $this->findOwnedOffer($id);
        $this->hydrateOffer($offer, $this->jsonPayload($request));

        $errors = $this->validationService->validateEntity($offer);
        if ([] !== $errors) {
            return $this->json(['success' => false, 'errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->flush();
        $this->cacheService->invalidateFeed();
        $this->scoreCacheService->invalidateOffer($offer);

        return $this->json(['success' => true, 'offer' => $this->serializeOffer($offer)]);
    }

    #[Route('/{id}', name: 'api_employer_offers_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $offer = $this->findOwnedOffer($id);
        $offer
            ->setIsDeleted(true)
            ->setStatus(JobOfferStatus::Closed);

        $this->entityManager->flush();
        $this->cacheService->invalidateFeed();
        $this->scoreCacheService->invalidateOffer($offer);

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function getEmployer(): Employer
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getEmployer() instanceof Employer) {
            throw $this->createAccessDeniedException('Profil employeur requis.');
        }

        return $user->getEmployer();
    }

    private function findOwnedOffer(string $id): JobOffer
    {
        $offer = $this->offerRepository->find($id);
        $employer = $this->getEmployer();

        if (!$offer instanceof JobOffer || $offer->isDeleted() || $offer->getEmployer()->getId()?->toString() !== $employer->getUser()->getId()?->toString()) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        return $offer;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateOffer(JobOffer $offer, array $payload): void
    {
        if (array_key_exists('title', $payload)) {
            $offer->setTitle((string) $payload['title']);
        }
        if (array_key_exists('description', $payload)) {
            $offer->setDescription((string) $payload['description']);
        }
        if (array_key_exists('requiredSkills', $payload) || array_key_exists('required_skills', $payload)) {
            $offer->setRequiredSkills($this->arrayValue($payload['requiredSkills'] ?? $payload['required_skills']));
        }
        if (array_key_exists('requiredEducation', $payload) || array_key_exists('required_education', $payload)) {
            $offer->setRequiredEducation((string) ($payload['requiredEducation'] ?? $payload['required_education']));
        }
        if (array_key_exists('requiredExperienceYears', $payload) || array_key_exists('required_experience_years', $payload)) {
            $offer->setRequiredExperienceYears((int) ($payload['requiredExperienceYears'] ?? $payload['required_experience_years']));
        }
        if (array_key_exists('contractType', $payload) || array_key_exists('contract_type', $payload)) {
            $offer->setContractType(ContractType::from((string) ($payload['contractType'] ?? $payload['contract_type'])));
        }
        if (array_key_exists('location', $payload)) {
            $offer->setLocation((string) $payload['location']);
        }
        if (array_key_exists('salaryMin', $payload) || array_key_exists('salary_min', $payload)) {
            $offer->setSalaryMin($this->nullableInt($payload['salaryMin'] ?? $payload['salary_min']));
        }
        if (array_key_exists('salaryMax', $payload) || array_key_exists('salary_max', $payload)) {
            $offer->setSalaryMax($this->nullableInt($payload['salaryMax'] ?? $payload['salary_max']));
        }
        if (array_key_exists('isRemoteAllowed', $payload) || array_key_exists('is_remote_allowed', $payload)) {
            $offer->setIsRemoteAllowed((bool) ($payload['isRemoteAllowed'] ?? $payload['is_remote_allowed']));
        }
        if (array_key_exists('requiredDocuments', $payload) || array_key_exists('required_documents', $payload)) {
            $offer->setRequiredDocuments($this->nullableArray($payload['requiredDocuments'] ?? $payload['required_documents']));
        }
        if (array_key_exists('recommendedDocuments', $payload) || array_key_exists('recommended_documents', $payload)) {
            $offer->setRecommendedDocuments($this->nullableArray($payload['recommendedDocuments'] ?? $payload['recommended_documents']));
        }
        if (array_key_exists('deadline', $payload)) {
            $offer->setDeadline(new \DateTimeImmutable((string) $payload['deadline']));
        }
        if (array_key_exists('status', $payload)) {
            $offer->setStatus(JobOfferStatus::from((string) $payload['status']));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }

    private function nullableInt(mixed $value): ?int
    {
        return null === $value || '' === $value ? null : (int) $value;
    }

    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function nullableArray(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOffer(JobOffer $offer): array
    {
        return [
            'id' => (string) $offer->getId(),
            'title' => $offer->getTitle(),
            'description' => $offer->getDescription(),
            'requiredSkills' => $offer->getRequiredSkills(),
            'requiredEducation' => $offer->getRequiredEducation(),
            'requiredExperienceYears' => $offer->getRequiredExperienceYears(),
            'contractType' => $offer->getContractType()->value,
            'location' => $offer->getLocation(),
            'salaryMin' => $offer->getSalaryMin(),
            'salaryMax' => $offer->getSalaryMax(),
            'isRemoteAllowed' => $offer->isRemoteAllowed(),
            'requiredDocuments' => $offer->getRequiredDocuments(),
            'recommendedDocuments' => $offer->getRecommendedDocuments(),
            'deadline' => $offer->getDeadline()->format('Y-m-d'),
            'isBoosted' => $offer->isBoosted(),
            'viewsCount' => $offer->getViewsCount(),
            'applicationsCount' => $offer->getApplicationsCount(),
            'createdAt' => $offer->getCreatedAt()->format(DATE_ATOM),
            'status' => $offer->getStatus()->value,
        ];
    }
}
