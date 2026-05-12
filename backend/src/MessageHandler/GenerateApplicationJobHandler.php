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
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

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
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $mailFrom,
        private readonly string $projectDir,
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

        $emailSent = $this->sendApplicationEmail($swipe, $documents);
        $offer->setApplicationsCount($offer->getApplicationsCount() + 1);

        $this->notificationService->notify(
            $swipe->getCandidate(),
            'application_sent',
            $emailSent ? 'Candidature envoyee' : 'Candidature preparee',
            $emailSent
                ? 'Votre candidature a ete envoyee pour le poste ' . $offer->getTitle() . '.'
                : 'Votre candidature a ete preparee. Postulez aussi sur le site source si l offre ne propose pas d email recruteur.',
            ['offerId' => (string) $offer->getId(), 'swipeId' => (string) $swipe->getId(), 'emailSent' => $emailSent],
        );

        $this->notificationService->notify(
            $offer->getEmployer(),
            'new_application',
            'Nouvelle candidature',
            'Une candidature pre-qualifiee est disponible pour ' . $offer->getTitle() . '.',
            ['offerId' => (string) $offer->getId(), 'swipeId' => (string) $swipe->getId(), 'emailSent' => $emailSent],
        );

        $this->entityManager->flush();
    }

    /**
     * @param CandidateDocument[] $documents
     */
    private function sendApplicationEmail(Swipe $swipe, array $documents): bool
    {
        $offer = $swipe->getOffer();
        $candidate = $swipe->getCandidate();
        $profile = $candidate->getCandidateProfile();
        $recipient = $offer->getApplicationEmail() ?: $offer->getEmployer()->getEmail();

        if ('' === trim($recipient)) {
            return false;
        }

        $candidateName = null === $profile
            ? $candidate->getDisplayName()
            : trim($profile->getFirstName() . ' ' . $profile->getLastName());

        $email = (new Email())
            ->from($this->mailFrom)
            ->replyTo($candidate->getEmail())
            ->to($recipient)
            ->subject($offer->getTitle())
            ->text($this->applicationEmailBody($swipe, $candidateName));

        foreach ($this->attachmentPaths($swipe, $documents) as $attachment) {
            $email->attachFromPath($attachment['path'], $attachment['name']);
        }

        try {
            $this->mailer->send($email);
            return true;
        } catch (\Throwable $exception) {
            $this->logger->warning('L email de candidature n a pas pu etre envoye.', [
                'swipeId' => (string) $swipe->getId(),
                'offerId' => (string) $offer->getId(),
                'recipient' => $recipient,
                'exception' => $exception,
            ]);

            return false;
        }
    }

    private function applicationEmailBody(Swipe $swipe, string $candidateName): string
    {
        $offer = $swipe->getOffer();

        return sprintf(
            "Bonjour,\n\nJe vous transmets ma candidature pour le poste de %s.\n\n%s\n\nCordialement,\n%s\n%s",
            $offer->getTitle(),
            $swipe->getMotivationLetterText() ?: 'Je reste disponible pour un entretien afin de vous presenter ma motivation.',
            $candidateName,
            $swipe->getCandidate()->getEmail(),
        );
    }

    /**
     * @param CandidateDocument[] $documents
     * @return array<int, array{path: string, name: string}>
     */
    private function attachmentPaths(Swipe $swipe, array $documents): array
    {
        $paths = [];

        foreach ($documents as $document) {
            $path = $this->localPathFromUrl($document->getFileUrl());
            if (null !== $path && str_ends_with(mb_strtolower($path), '.pdf')) {
                $paths[] = ['path' => $path, 'name' => $this->safeAttachmentName($document->getTitle()) . '.pdf'];
            }
        }

        $cvPath = $this->localPathFromUrl($swipe->getCvUsedUrl());
        if (null !== $cvPath && str_ends_with(mb_strtolower($cvPath), '.pdf')) {
            array_unshift($paths, ['path' => $cvPath, 'name' => 'CV-personnalise.pdf']);
        }

        return $paths;
    }

    private function localPathFromUrl(?string $url): ?string
    {
        if (null === $url || '' === trim($url) || !str_starts_with($url, '/storage/')) {
            return null;
        }

        $path = $this->projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . ltrim(substr($url, strlen('/storage/')), '/\\');

        return is_file($path) ? $path : null;
    }

    private function safeAttachmentName(string $name): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $name);

        return trim((string) $safe, '-') ?: 'document';
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
