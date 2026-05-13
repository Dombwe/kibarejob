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
        'Bac + 1' => 4,
        'Bac +1' => 4,
        'Bac + 2' => 5,
        'Bac +2' => 5,
        'Bac + 3' => 6,
        'Bac +3' => 6,
        'Licence' => 6,
        'Bac + 4' => 7,
        'Bac +4' => 7,
        'Bac + 5' => 8,
        'Bac +5' => 8,
        'Master' => 8,
        'Bac + 6' => 9,
        'Bac +6' => 9,
        'Bac + 7' => 10,
        'Bac +7' => 10,
        'Bac + 8' => 11,
        'Bac +8' => 11,
        'Doctorat' => 11,
        'Bac + 9' => 12,
        'Bac +9' => 12,
        'Bac + 10' => 13,
        'Bac +10' => 13,
        'Bac + 11' => 14,
        'Bac +11' => 14,
        'Bac + 12' => 15,
        'Bac +12' => 15,
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
        $educationFieldScore = $this->calculateEducationFieldScore($candidate, $offer);
        $keywordScore = $this->calculateKeywordScore($candidate, $offer);

        return (int) round(
            ($skillsScore * 0.40)
            + ($locationScore * 0.15)
            + ($experienceScore * 0.20)
            + ($documentsScore * 0.10)
            + ($educationFieldScore * 0.05)
            + ($keywordScore * 0.10)
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

    private function calculateEducationFieldScore(CandidateProfile $candidate, JobOffer $offer): int
    {
        $candidateField = $this->normalize((string) $candidate->getEducationField());
        $offerField = $this->normalize((string) $offer->getEducationField());

        if ('' === $offerField) {
            return 100;
        }

        if ('' === $candidateField) {
            return 30;
        }

        return str_contains($candidateField, $offerField) || str_contains($offerField, $candidateField) ? 100 : 45;
    }

    private function calculateKeywordScore(CandidateProfile $candidate, JobOffer $offer): int
    {
        $candidateText = $this->normalize(implode(' ', [
            implode(' ', $candidate->getSkills()),
            implode(' ', $candidate->getExperiences()),
            implode(' ', $candidate->getInterests()),
            (string) $candidate->getEducationField(),
        ]));
        if ('' === $candidateText) {
            return 0;
        }

        $offerKeywords = $this->importantWords($offer->getTitle() . ' ' . $offer->getDescription() . ' ' . implode(' ', $offer->getRequiredSkills()));
        if ([] === $offerKeywords) {
            return 70;
        }

        $matches = 0;
        foreach ($offerKeywords as $keyword) {
            if (str_contains($candidateText, $keyword)) {
                ++$matches;
            }
        }

        return min(100, (int) round(($matches / count($offerKeywords)) * 140));
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
        $years = 0;
        foreach ($candidate->getExperiences() as $experience) {
            $text = $this->normalize((string) $experience);
            if (preg_match_all('/(\d+)\s*(?:ans|annees|années|year|years)/u', $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $years += (int) $match;
                }
                continue;
            }

            if (preg_match('/(20\d{2}|19\d{2})\s*[-–]\s*(20\d{2}|19\d{2}|present|actuel|aujourd)/u', $text, $match)) {
                $start = (int) $match[1];
                $end = is_numeric($match[2]) ? (int) $match[2] : (int) date('Y');
                $years += max(0, $end - $start);
            }
        }

        if ($years > 0) {
            return min(30, $years);
        }

        $score = (self::EDUCATION_LEVELS[$candidate->getEducationLevel()] ?? 0) - 2;
        $score += intdiv(count($candidate->getSkills()), 3);

        return max(0, $score);
    }

    /**
     * @return string[]
     */
    private function importantWords(string $text): array
    {
        $stopWords = ['poste', 'emploi', 'candidat', 'candidate', 'travail', 'mission', 'missions', 'profil', 'vous', 'nous', 'avec', 'pour', 'dans', 'des', 'les', 'une', 'sur', 'votre', 'notre', 'plus', 'minimum'];
        $words = preg_split('/\s+/', $this->normalize($text)) ?: [];
        $keywords = [];

        foreach ($words as $word) {
            if (mb_strlen($word) < 4 || in_array($word, $stopWords, true)) {
                continue;
            }
            $keywords[$word] = true;
        }

        return array_slice(array_keys($keywords), 0, 18);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = false === $converted ? $value : $converted;
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
