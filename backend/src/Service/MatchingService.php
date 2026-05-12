<?php

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Repository\CandidateDocumentRepository;

class MatchingService
{
    private const EDUCATION_LEVELS = [
        'Aucun' => 0,
        'CEP' => 1,
        'BEPC' => 2,
        'Bac' => 3,
        'Licence' => 4,
        'Master' => 5,
        'Doctorat' => 6,
    ];

    /**
     * @var array<string, string[]>
     */
    private array $candidateDocumentTextCache = [];

    public function __construct(private readonly CandidateDocumentRepository $documentRepository)
    {
    }

    public function calculateMatchScore(CandidateProfile $candidate, JobOffer $offer): int
    {
        $skillsScore = $this->calculateSkillsScore($candidate->getSkills(), $offer->getRequiredSkills());
        $locationScore = $this->calculateLocationScore($candidate, $offer);
        $experienceScore = $this->calculateExperienceScore($candidate, $offer);
        $documentsScore = $this->calculateDocumentsScore($candidate, $offer);

        return (int) round(
            ($skillsScore * 0.50)
            + ($locationScore * 0.20)
            + ($experienceScore * 0.20)
            + ($documentsScore * 0.10)
        );
    }

    /**
     * @param string[] $candidateSkills
     * @param string[] $requiredSkills
     */
    private function calculateSkillsScore(array $candidateSkills, array $requiredSkills): int
    {
        if ([] === $requiredSkills) {
            return 100;
        }

        $candidateSkills = array_map([$this, 'normalize'], $candidateSkills);
        $requiredSkills = array_map([$this, 'normalize'], $requiredSkills);
        $matches = 0;

        foreach ($requiredSkills as $requiredSkill) {
            foreach ($candidateSkills as $candidateSkill) {
                if ($candidateSkill === $requiredSkill || str_contains($candidateSkill, $requiredSkill) || str_contains($requiredSkill, $candidateSkill)) {
                    ++$matches;
                    break;
                }
            }
        }

        return (int) round(($matches / count($requiredSkills)) * 100);
    }

    private function calculateLocationScore(CandidateProfile $candidate, JobOffer $offer): int
    {
        if ($offer->isRemoteAllowed()) {
            return 100;
        }

        return $this->normalize($candidate->getCity()) === $this->normalize($offer->getLocation()) ? 100 : 35;
    }

    private function calculateExperienceScore(CandidateProfile $candidate, JobOffer $offer): int
    {
        $candidateLevel = self::EDUCATION_LEVELS[$candidate->getEducationLevel()] ?? 0;
        $requiredLevel = self::EDUCATION_LEVELS[$offer->getRequiredEducation()] ?? 0;
        $educationScore = $candidateLevel >= $requiredLevel ? 70 : max(0, 70 - (($requiredLevel - $candidateLevel) * 20));

        $experienceYears = $this->estimateExperienceYears($candidate);
        $requiredYears = $offer->getRequiredExperienceYears();
        $yearsScore = 0 === $requiredYears ? 30 : min(30, (int) round(($experienceYears / max(1, $requiredYears)) * 30));

        return min(100, $educationScore + $yearsScore);
    }

    private function calculateDocumentsScore(CandidateProfile $candidate, JobOffer $offer): int
    {
        $requiredDocuments = $offer->getRequiredDocuments() ?? [];
        if ([] === $requiredDocuments) {
            return 100;
        }

        $availableText = $this->getCandidateDocumentText($candidate);
        if ([] === $availableText) {
            return 0;
        }

        $matches = 0;
        foreach ($requiredDocuments as $requiredDocument) {
            $required = $this->normalize($this->textFromMixed($requiredDocument));
            if ('' === $required) {
                continue;
            }

            foreach ($availableText as $documentText) {
                if (str_contains($documentText, $required) || str_contains($required, $documentText)) {
                    ++$matches;
                    break;
                }
            }
        }

        return (int) round(($matches / count($requiredDocuments)) * 100);
    }

    private function textFromMixed(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            foreach (['name', 'label', 'title', 'type', 'document', 'documentType'] as $key) {
                if (array_key_exists($key, $value) && (is_string($value[$key]) || is_numeric($value[$key]))) {
                    return (string) $value[$key];
                }
            }

            return implode(' ', array_map([$this, 'textFromMixed'], $value));
        }

        return '';
    }

    /**
     * @return string[]
     */
    private function getCandidateDocumentText(CandidateProfile $candidate): array
    {
        $candidateId = (string) $candidate->getUser()->getId();
        if (array_key_exists($candidateId, $this->candidateDocumentTextCache)) {
            return $this->candidateDocumentTextCache[$candidateId];
        }

        $documents = $this->documentRepository->findBy([
            'candidate' => $candidate->getUser(),
            'isDeleted' => false,
        ]);

        $availableText = [];
        foreach ($documents as $document) {
            $availableText[] = $this->normalize($document->getTitle() . ' ' . (string) $document->getDescription() . ' ' . (string) $document->getDocumentNumber());
        }

        $this->candidateDocumentTextCache[$candidateId] = $availableText;

        return $availableText;
    }

    private function estimateExperienceYears(CandidateProfile $candidate): int
    {
        $score = (self::EDUCATION_LEVELS[$candidate->getEducationLevel()] ?? 0) - 2;
        $score += intdiv(count($candidate->getSkills()), 3);

        return max(0, $score);
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
