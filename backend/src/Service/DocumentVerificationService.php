<?php

namespace App\Service;

use App\Entity\CandidateDocument;
use App\Entity\DocumentVerificationLog;
use App\Entity\Enum\VerificationAction;

class DocumentVerificationService
{
    public function __construct(private readonly DocumentExtractorService $extractorService)
    {
    }

    /**
     * @return array{score: int, action: VerificationAction, extractedText: string, signals: array<string, bool|int|string>, reason: string|null}
     */
    public function verify(CandidateDocument $document, string $absolutePath, ?string $mimeType = null): array
    {
        $extraction = $this->extractorService->extract($absolutePath, $mimeType);
        $text = mb_strtolower($extraction['text']);
        $fileExists = is_file($absolutePath);
        $fileSize = $fileExists ? filesize($absolutePath) : 0;

        $signals = [
            'fileExists' => $fileExists,
            'hasReadableContent' => strlen(trim($text)) > 8,
            'mentionsDocumentType' => str_contains($text, $document->getType()->value) || str_contains($text, mb_strtolower($document->getTitle())),
            'hasOfficialKeyword' => $this->hasAnyKeyword($text, ['diplome', 'certificat', 'attestation', 'ministere', 'universite', 'ecole', 'formation']),
            'sizeBytes' => (int) $fileSize,
            'extractionMethod' => $extraction['method'],
        ];

        $score = 0;
        $score += $signals['fileExists'] ? 20 : 0;
        $score += $signals['hasReadableContent'] ? 20 : 0;
        $score += $signals['mentionsDocumentType'] ? 20 : 0;
        $score += $signals['hasOfficialKeyword'] ? 25 : 0;
        $score += min(15, (int) floor(((int) $fileSize) / 100000));
        $score = max(0, min(100, $score));

        $action = $score >= 65 ? VerificationAction::AutoVerified : VerificationAction::Flagged;
        $reason = $score >= 65 ? null : 'Score insuffisant pour validation automatique.';

        return [
            'score' => $score,
            'action' => $action,
            'extractedText' => $extraction['text'],
            'signals' => $signals,
            'reason' => $reason,
        ];
    }

    public function createLog(CandidateDocument $document, array $result): DocumentVerificationLog
    {
        return (new DocumentVerificationLog())
            ->setDocument($document)
            ->setAiScore((int) $result['score'])
            ->setAction($result['action'])
            ->setReason($result['reason']);
    }

    /**
     * @param string[] $keywords
     */
    private function hasAnyKeyword(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
