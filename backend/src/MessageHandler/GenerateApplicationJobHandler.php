<?php

namespace App\MessageHandler;

use App\Entity\CandidateDocument;
use App\Entity\Enum\SwipeDirection;
use App\Entity\Swipe;
use App\Message\GenerateApplicationJob;
use App\Repository\CandidateDocumentRepository;
use App\Repository\SwipeRepository;
use App\Service\CvAdapterService;
use App\Service\MotivationLetterService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GenerateApplicationJobHandler
{
    public function __construct(
        private readonly SwipeRepository $swipeRepository,
        private readonly CandidateDocumentRepository $documentRepository,
        private readonly CvAdapterService $cvAdapterService,
        private readonly MotivationLetterService $motivationLetterService,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GenerateApplicationJob $message): void
    {
        $swipe = $this->swipeRepository->find($message->getSwipeId());
        if (!$swipe instanceof Swipe || $swipe->isDeleted() || $swipe->getDirection() === SwipeDirection::Dislike) {
            return;
        }

        $candidateProfile = $swipe->getCandidate()->getCandidateProfile();
        if (null === $candidateProfile) {
            return;
        }

        $offer = $swipe->getOffer();
        $documents = $this->selectDocumentsForOffer($swipe);

        $swipe
            ->setCvUsedUrl($this->cvAdapterService->adaptCv($candidateProfile, $offer))
            ->setMotivationLetterText($this->motivationLetterService->generate($candidateProfile, $offer))
            ->setDocumentsSent(array_map(static fn (CandidateDocument $document): string => (string) $document->getId(), $documents));

        $offer->setApplicationsCount($offer->getApplicationsCount() + 1);

        $this->notificationService->notify(
            $swipe->getCandidate(),
            'application_sent',
            'Candidature envoyee',
            'Votre candidature a ete envoyee pour le poste ' . $offer->getTitle() . '.',
            ['offerId' => (string) $offer->getId(), 'swipeId' => (string) $swipe->getId()],
        );

        $this->notificationService->notify(
            $offer->getEmployer(),
            'new_application',
            'Nouvelle candidature',
            'Une candidature pre-qualifiee est disponible pour ' . $offer->getTitle() . '.',
            ['offerId' => (string) $offer->getId(), 'swipeId' => (string) $swipe->getId()],
        );

        $this->entityManager->flush();
    }

    /**
     * @return CandidateDocument[]
     */
    private function selectDocumentsForOffer(Swipe $swipe): array
    {
        $documents = $this->documentRepository->findBy([
            'candidate' => $swipe->getCandidate(),
            'isDeleted' => false,
            'isPublic' => true,
        ]);

        $required = array_map(static fn (mixed $value): string => mb_strtolower((string) $value), $swipe->getOffer()->getRequiredDocuments() ?? []);
        if ([] === $required) {
            return array_slice($documents, 0, 5);
        }

        $selected = [];
        foreach ($documents as $document) {
            if ($document->isPinned()) {
                $selected[(string) $document->getId()] = $document;
                continue;
            }

            $haystack = mb_strtolower($document->getTitle() . ' ' . (string) $document->getDescription() . ' ' . $document->getType()->value);
            foreach ($required as $needle) {
                if (str_contains($haystack, $needle) || str_contains($needle, $haystack)) {
                    $selected[(string) $document->getId()] = $document;
                    break;
                }
            }
        }

        return array_slice(array_values($selected), 0, 8);
    }
}
