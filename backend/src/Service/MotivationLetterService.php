<?php

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;

class MotivationLetterService
{
    public function generate(CandidateProfile $candidate, JobOffer $offer): string
    {
        $matchedSkills = $this->matchedSkills($candidate, $offer);
        $skills = implode(', ', array_slice([] !== $matchedSkills ? $matchedSkills : $candidate->getSkills(), 0, 5));
        $company = $offer->getEmployer()->getEmployer()?->getCompanyName() ?: 'votre structure';
        $education = trim($candidate->getEducationLevel() . ' ' . (string) $candidate->getEducationField());
        $experience = $this->bestExperience($candidate, $offer);

        return sprintf(
            "Bonjour,\n\nJe vous adresse ma candidature pour le poste de %s au sein de %s.\n\n%s%sMon profil correspond à vos besoins, notamment grâce à mes compétences en %s. Je suis disponible %s et prêt à contribuer avec sérieux, rigueur et sens du résultat.\n\nJe reste à votre disposition pour un entretien afin d’échanger sur ma candidature.\n\nCordialement,\n%s %s",
            $offer->getTitle(),
            $company,
            '' !== $education ? 'Ma formation en ' . $education . ' constitue une base solide pour ce poste. ' : '',
            null !== $experience ? 'Mon expérience la plus pertinente : ' . $experience . '. ' : '',
            '' !== $skills ? $skills : 'compétences opérationnelles',
            $candidate->getAvailability() ?: 'selon vos besoins',
            $candidate->getFirstName(),
            $candidate->getLastName(),
        );
    }

    /**
     * @return string[]
     */
    private function matchedSkills(CandidateProfile $candidate, JobOffer $offer): array
    {
        $required = array_map([$this, 'normalize'], $offer->getRequiredSkills() ?? []);
        if ([] === $required) {
            return [];
        }

        $matched = [];
        foreach ($candidate->getSkills() as $skill) {
            $normalizedSkill = $this->normalize((string) $skill);
            foreach ($required as $needle) {
                if ('' !== $needle && (str_contains($normalizedSkill, $needle) || str_contains($needle, $normalizedSkill))) {
                    $matched[] = (string) $skill;
                    break;
                }
            }
        }

        return array_values(array_unique($matched));
    }

    private function bestExperience(CandidateProfile $candidate, JobOffer $offer): ?string
    {
        $offerText = $this->normalize($offer->getTitle() . ' ' . implode(' ', $offer->getRequiredSkills() ?? []));
        $fallback = null;
        $bestScore = 0;

        foreach ($candidate->getExperiences() as $experience) {
            $text = trim((string) $experience);
            if ('' === $text) {
                continue;
            }
            $fallback ??= $text;
            $score = 0;
            foreach (preg_split('/\s+/', $this->normalize($text)) ?: [] as $word) {
                if (mb_strlen($word) >= 4 && str_contains($offerText, $word)) {
                    ++$score;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $fallback = $text;
            }
        }

        return $fallback;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = false === $converted ? $value : $converted;
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
