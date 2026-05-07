<?php

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;

class MotivationLetterService
{
    public function generate(CandidateProfile $candidate, JobOffer $offer): string
    {
        $skills = implode(', ', array_slice($candidate->getSkills(), 0, 5));

        return sprintf(
            "Bonjour,\n\nJe vous adresse ma candidature pour le poste de %s a %s. Mon profil correspond a vos besoins, notamment grace a mes competences en %s et ma disponibilite %s.\n\nJe reste disponible pour un entretien afin de vous presenter ma motivation.\n\nCordialement,\n%s %s",
            $offer->getTitle(),
            $offer->getLocation(),
            '' !== $skills ? $skills : 'competences operationnelles',
            $candidate->getAvailability(),
            $candidate->getFirstName(),
            $candidate->getLastName(),
        );
    }
}
