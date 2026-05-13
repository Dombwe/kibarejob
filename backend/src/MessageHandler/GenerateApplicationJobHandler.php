<?php

namespace App\MessageHandler;

use App\Entity\CandidateDocument;
use App\Entity\Enum\SwipeDirection;
use App\Entity\JobOffer;
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
        private readonly string $storagePath,
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
        $isFirstProcessing = null === $swipe->getDocumentsSent();

        $swipe
            ->setCvUsedUrl($this->safeAdaptCv($candidateProfile, $offer))
            ->setMotivationLetterText($this->safeMotivationLetter($candidateProfile, $offer))
            ->setDocumentsSent(array_map(static fn (CandidateDocument $document): string => (string) $document->getId(), $documents));

        $emailSent = $this->sendApplicationEmail($swipe, $documents);
        if ($isFirstProcessing) {
            $offer->setApplicationsCount($offer->getApplicationsCount() + 1);
        }

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

    private function safeAdaptCv(\App\Entity\CandidateProfile $candidateProfile, JobOffer $offer): string
    {
        try {
            $url = $this->cvAdapterService->adaptCv($candidateProfile, $offer);
            if ('' !== trim($url)) {
                return $url;
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('La generation du CV adapte a echoue.', [
                'offerId' => (string) $offer->getId(),
                'candidateId' => (string) $candidateProfile->getUser()->getId(),
                'exception' => $exception,
            ]);
        }

        return $candidateProfile->getCvGeneratedUrl() ?: ($candidateProfile->getCvOriginalUrl() ?: '');
    }

    private function safeMotivationLetter(\App\Entity\CandidateProfile $candidateProfile, JobOffer $offer): string
    {
        try {
            $letter = trim($this->motivationLetterService->generate($candidateProfile, $offer));
            if ('' !== $letter) {
                return $letter;
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('La generation de lettre de motivation a echoue.', [
                'offerId' => (string) $offer->getId(),
                'candidateId' => (string) $candidateProfile->getUser()->getId(),
                'exception' => $exception,
            ]);
        }

        $name = trim($candidateProfile->getFirstName() . ' ' . $candidateProfile->getLastName()) ?: $candidateProfile->getUser()->getDisplayName();

        return sprintf(
            "Bonjour,\n\nJe vous adresse ma candidature pour le poste de %s. Mon profil, mes documents et ma disponibilite ont ete prepares via KIBARE-JOB.\n\nJe reste disponible pour un entretien afin de vous presenter ma motivation.\n\nCordialement,\n%s",
            $offer->getTitle(),
            $name,
        );
    }

    /**
     * @param CandidateDocument[] $documents
     */
    private function sendApplicationEmail(Swipe $swipe, array $documents): bool
    {
        $offer = $swipe->getOffer();
        $candidate = $swipe->getCandidate();
        $profile = $candidate->getCandidateProfile();
        $recipient = $this->resolveRecipient($offer);
        $subject = $offer->getTitle();

        if ('' === trim($recipient)) {
            $swipe
                ->setEmailSent(false)
                ->setEmailRecipient(null)
                ->setEmailSender($this->mailFrom)
                ->setEmailReplyTo($candidate->getEmail())
                ->setEmailSubject($subject)
                ->setEmailBody($this->applicationEmailBody($swipe, $candidate->getDisplayName()))
                ->setEmailError('Aucun email recruteur disponible pour cette offre. Utilisez le bouton de candidature sur le site source.');

            return false;
        }

        $candidateName = null === $profile
            ? $candidate->getDisplayName()
            : trim($profile->getFirstName() . ' ' . $profile->getLastName());
        $body = $this->applicationEmailBody($swipe, $candidateName);

        $swipe
            ->setEmailSent(false)
            ->setEmailRecipient($recipient)
            ->setEmailSender($this->mailFrom)
            ->setEmailReplyTo($candidate->getEmail())
            ->setEmailSubject($subject)
            ->setEmailBody($body)
            ->setEmailSentAt(null)
            ->setEmailError(null);

        $email = (new Email())
            ->from($this->mailFrom)
            ->replyTo($candidate->getEmail())
            ->to($recipient)
            ->subject($subject)
            ->text($body);

        if (mb_strtolower($recipient) !== mb_strtolower($candidate->getEmail())) {
            $email->cc($candidate->getEmail());
        }

        foreach ($this->attachmentPaths($swipe, $documents) as $attachment) {
            $email->attachFromPath($attachment['path'], $attachment['name']);
        }

        try {
            $this->mailer->send($email);
            $swipe
                ->setEmailSent(true)
                ->setEmailSentAt(new \DateTimeImmutable())
                ->setEmailError(null);

            return true;
        } catch (\Throwable $exception) {
            $swipe
                ->setEmailSent(false)
                ->setEmailSentAt(null)
                ->setEmailError($exception->getMessage());

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
            $swipe->getMotivationLetterText() ?: 'Je reste disponible pour un entretien afin de vous présenter ma motivation.',
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
        $cvPath = $this->localPathFromUrl($swipe->getCvUsedUrl());
        $hasGeneratedCv = null !== $cvPath && str_ends_with(mb_strtolower($cvPath), '.pdf');

        foreach ($documents as $document) {
            $documentLabel = mb_strtolower($document->getTitle() . ' ' . $document->getType()->value);
            if ($hasGeneratedCv && (str_contains($documentLabel, 'cv') || str_contains($documentLabel, 'curriculum'))) {
                continue;
            }

            $path = $this->localPathFromUrl($document->getFileUrl());
            if (null !== $path && str_ends_with(mb_strtolower($path), '.pdf')) {
                $paths[] = ['path' => $path, 'name' => $this->safeAttachmentName($document->getTitle()) . '.pdf'];
            }
        }

        if ($hasGeneratedCv) {
            array_unshift($paths, ['path' => $cvPath, 'name' => 'CV.pdf']);
        }

        $letterPath = $this->motivationLetterAttachmentPath($swipe);
        if (null !== $letterPath) {
            $paths[] = ['path' => $letterPath, 'name' => 'Lettre-de-motivation.pdf'];
        }

        return $paths;
    }

    private function resolveRecipient(JobOffer $offer): string
    {
        $applicationEmail = trim((string) $offer->getApplicationEmail());
        if ('' !== $applicationEmail) {
            return $applicationEmail;
        }

        if ($this->isExternalOffer($offer)) {
            return '';
        }

        return trim((string) $offer->getEmployer()->getEmail());
    }

    private function isExternalOffer(JobOffer $offer): bool
    {
        return null !== $offer->getSourceType() || null !== $offer->getExternalSourceName();
    }

    private function motivationLetterAttachmentPath(Swipe $swipe): ?string
    {
        $letter = trim((string) $swipe->getMotivationLetterText());
        if ('' === $letter) {
            return null;
        }

        $dir = $this->resolveStorageRoot()
            . DIRECTORY_SEPARATOR . 'candidates' . DIRECTORY_SEPARATOR . (string) $swipe->getCandidate()->getId()
            . DIRECTORY_SEPARATOR . 'generated';

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'lettre-motivation-' . (string) $swipe->getId() . '.pdf';
        $this->writeTextPdf($path, 'Lettre de motivation', $letter);

        return is_file($path) ? $path : null;
    }

    private function writeTextPdf(string $path, string $title, string $body): void
    {
        $text = trim($title . "\n\n" . $body);
        $wrapped = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ('' === $line) {
                $wrapped[] = '';
                continue;
            }

            foreach (explode("\n", wordwrap($line, 86, "\n", true)) as $chunk) {
                $wrapped[] = $chunk;
            }
        }

        $pages = array_chunk($wrapped, 42);
        if ([] === $pages) {
            $pages = [[]];
        }

        $objects = [];
        $pageObjectIds = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $nextObjectId = 4;
        foreach ($pages as $pageLines) {
            $contentObjectId = $nextObjectId++;
            $pageObjectId = $nextObjectId++;
            $pageObjectIds[] = $pageObjectId;

            $stream = "BT\n/F1 11 Tf\n50 790 Td\n15 TL\n";
            foreach ($pageLines as $line) {
                $stream .= '(' . $this->pdfEscape($line) . ") Tj\nT*\n";
            }
            $stream .= "ET\n";

            $objects[$contentObjectId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageObjectIds)) . '] /Count ' . count($pageObjectIds) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        file_put_contents($path, $pdf);
    }

    private function pdfEscape(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        $encoded = false === $encoded ? $value : $encoded;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    private function localPathFromUrl(?string $url): ?string
    {
        if (null === $url || '' === trim($url) || !str_starts_with($url, '/storage/')) {
            return null;
        }

        $path = $this->resolveStorageRoot() . DIRECTORY_SEPARATOR . ltrim(substr($url, strlen('/storage/')), '/\\');

        return is_file($path) ? $path : null;
    }

    private function resolveStorageRoot(): string
    {
        if (str_starts_with($this->storagePath, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $this->storagePath)) {
            return rtrim($this->storagePath, '/\\');
        }

        return $this->projectDir . DIRECTORY_SEPARATOR . trim($this->storagePath, '/\\');
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

        $required = array_values(array_filter(array_map(
            fn (mixed $value): string => mb_strtolower($this->documentLabelFromMixed($value)),
            $swipe->getOffer()->getRequiredDocuments() ?? [],
        )));
        $selected = [];
        foreach ($documents as $document) {
            if ($document->isPinned()) {
                $selected[(string) $document->getId()] = $document;
            }

            $haystack = mb_strtolower($document->getTitle() . ' ' . (string) $document->getDescription() . ' ' . $document->getType()->value);
            foreach ($required as $needle) {
                if (str_contains($haystack, $needle) || str_contains($needle, $haystack)) {
                    $selected[(string) $document->getId()] = $document;
                    break;
                }
            }
        }

        foreach ($documents as $document) {
            $selected[(string) $document->getId()] ??= $document;
        }

        return array_slice(array_values($selected), 0, 10);
    }

    private function documentLabelFromMixed(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            foreach (['label', 'name', 'title', 'type', 'document', 'documentType'] as $key) {
                if (array_key_exists($key, $value) && (is_string($value[$key]) || is_numeric($value[$key]))) {
                    return trim((string) $value[$key]);
                }
            }
        }

        return '';
    }
}
