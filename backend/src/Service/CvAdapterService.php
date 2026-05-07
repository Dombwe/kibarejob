<?php

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;

class CvAdapterService
{
    public function adaptCv(CandidateProfile $candidate, JobOffer $offer): string
    {
        if (null !== $candidate->getCvGeneratedUrl()) {
            return $candidate->getCvGeneratedUrl();
        }

        if (null !== $candidate->getCvOriginalUrl()) {
            return $candidate->getCvOriginalUrl();
        }

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $candidate->getFirstName() . '-' . $candidate->getLastName() . '-' . $offer->getTitle()));

        return '/storage/generated-cv/' . trim((string) $slug, '-') . '.pdf';
    }
}
