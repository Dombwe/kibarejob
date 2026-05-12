<?php

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Repository\CandidateDocumentRepository;

class CandidateProfileCompletionService
{
    public function __construct(
        private readonly CandidateDocumentRepository $documentRepository,
    ) {
    }

    public function refresh(CandidateProfile $profile): int
    {
        $percent = $this->calculate($profile);
        $profile->getUser()->setProfileCompletedPercent($percent);

        return $percent;
    }

    public function calculate(CandidateProfile $profile): int
    {
        $documentCount = $this->documentRepository->count([
            'candidate' => $profile->getUser(),
            'isDeleted' => false,
        ]);

        $fields = [
            $profile->getFirstName(),
            $profile->getLastName(),
            $profile->getCity(),
            $profile->getEducationLevel(),
            $profile->getEducationField(),
            $profile->getSkills(),
            $profile->getLanguages(),
            $profile->getExperiences(),
            $profile->getInterests(),
            $profile->getAvailability(),
            $profile->getCvOriginalUrl() ?? $profile->getCvGeneratedUrl(),
            $documentCount > 0,
        ];

        $filled = 0;
        foreach ($fields as $field) {
            if ((is_array($field) && [] !== $field) || (!is_array($field) && null !== $field && '' !== $field && false !== $field)) {
                ++$filled;
            }
        }

        return (int) round(($filled / count($fields)) * 100);
    }
}
