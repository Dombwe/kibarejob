<?php

namespace App\DataFixtures;

use App\Entity\CandidateProfile;
use App\Entity\Employer;
use App\Entity\JobOffer;
use App\Entity\SubscriptionPlan;
use App\Entity\Swipe;
use App\Entity\User;
use App\Entity\Enum\ContractType;
use App\Entity\Enum\EmployerSubscriptionTier;
use App\Entity\Enum\JobOfferStatus;
use App\Entity\Enum\SubscriptionTarget;
use App\Entity\Enum\SwipeDirection;
use App\Entity\Enum\SwipeStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadSubscriptionPlans($manager);

        $employerUsers = $this->loadEmployers($manager);
        $candidateUsers = $this->loadCandidates($manager);
        $offers = $this->loadJobOffers($manager, $employerUsers);
        $this->loadSwipes($manager, $candidateUsers, $offers);

        $manager->flush();
    }

    private function loadSubscriptionPlans(ObjectManager $manager): void
    {
        $plans = [
            ['Candidat Premium', SubscriptionTarget::Candidat, 0, ['swipes_illimites', 'cv_designer', 'lettres_ia_mock']],
            ['Employeur Standard', SubscriptionTarget::Employeur, 0, ['5_offres_actives', 'tri_candidatures', 'demandes_documents']],
            ['Employeur Pro', SubscriptionTarget::Employeur, 0, ['offres_illimitees', 'statistiques_avancees', 'boost_mock']],
        ];

        foreach ($plans as [$name, $target, $price, $features]) {
            $plan = (new SubscriptionPlan())
                ->setName($name)
                ->setTarget($target)
                ->setPrice($price)
                ->setPrice3months($price)
                ->setPrice6months($price)
                ->setPrice12months($price)
                ->setFeatures($features);
            $manager->persist($plan);
        }
    }

    /**
     * @return User[]
     */
    private function loadEmployers(ObjectManager $manager): array
    {
        $companies = [
            ['Sank Money', 'Fintech', ['Ouagadougou'], 'https://example.test/logos/sank-money.png'],
            ['Bobo Agro Services', 'Agriculture', ['Bobo-Dioulasso'], 'https://example.test/logos/bobo-agro.png'],
            ['Sahel Tech', 'Technologie', ['Ouagadougou', 'Koudougou'], 'https://example.test/logos/sahel-tech.png'],
            ['Wend Panga BTP', 'Construction', ['Ouagadougou'], 'https://example.test/logos/wend-panga.png'],
            ['Clinique Faso Sante', 'Sante', ['Bobo-Dioulasso', 'Ouagadougou'], 'https://example.test/logos/faso-sante.png'],
        ];

        $users = [];
        foreach ($companies as $index => [$name, $sector, $cities, $logo]) {
            $user = (new User())
                ->setEmail(sprintf('employeur%d@kibarejob.test', $index + 1))
                ->setPhone(sprintf('+2267000000%d', $index + 1))
                ->setRoles(['ROLE_EMPLOYER'])
                ->setProfileCompletedPercent(100);
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, 'password'));

            $employer = (new Employer())
                ->setUser($user)
                ->setCompanyName($name)
                ->setNif(sprintf('BF-%04d-KJOB', $index + 1))
                ->setSector($sector)
                ->setCompanySize(['1-10', '11-50', '51-200', '200+'][$index % 4])
                ->setCities($cities)
                ->setLogoUrl($logo)
                ->setDescription($name . ' recrute des profils motives pour accompagner sa croissance.')
                ->setWebsite('https://example.test')
                ->setSubscriptionTier($index === 0 ? EmployerSubscriptionTier::Pro : EmployerSubscriptionTier::Free)
                ->setIsValidated(true);

            $manager->persist($user);
            $manager->persist($employer);
            $users[] = $user;
        }

        return $users;
    }

    /**
     * @return User[]
     */
    private function loadCandidates(ObjectManager $manager): array
    {
        $names = [
            ['Awa', 'Ouedraogo', 'Ouagadougou', 'Licence', 'Gestion'],
            ['Moussa', 'Traore', 'Bobo-Dioulasso', 'Bac', 'Maintenance'],
            ['Fatou', 'Sawadogo', 'Ouagadougou', 'Master', 'Finance'],
            ['Issa', 'Kabore', 'Koudougou', 'BEPC', 'Logistique'],
            ['Mariam', 'Zongo', 'Bobo-Dioulasso', 'Licence', 'Informatique'],
            ['Abdoulaye', 'Compaore', 'Ouagadougou', 'Bac', 'Commercial'],
            ['Sali', 'Barry', 'Ouagadougou', 'Master', 'Ressources humaines'],
            ['Yacouba', 'Sanou', 'Bobo-Dioulasso', 'CEP', 'Agriculture'],
            ['Nadia', 'Ilboudo', 'Ouagadougou', 'Doctorat', 'Sante'],
            ['Paul', 'Somda', 'Koudougou', 'Licence', 'Genie civil'],
        ];

        $skills = [
            ['Vente', 'Relation client', 'Excel'],
            ['Maintenance', 'Electricite', 'Permis B'],
            ['Comptabilite', 'Analyse financiere', 'Excel'],
            ['Logistique', 'Stock', 'Livraison'],
            ['PHP', 'Flutter', 'MySQL'],
        ];

        $users = [];
        foreach ($names as $index => [$firstName, $lastName, $city, $education, $field]) {
            $user = (new User())
                ->setEmail(sprintf('candidat%d@kibarejob.test', $index + 1))
                ->setPhone(sprintf('+2267100000%d', $index + 1))
                ->setRoles(['ROLE_CANDIDATE'])
                ->setProfileCompletedPercent(80);
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, 'password'));

            $profile = (new CandidateProfile())
                ->setUser($user)
                ->setFirstName($firstName)
                ->setLastName($lastName)
                ->setCity($city)
                ->setEducationLevel($education)
                ->setEducationField($field)
                ->setSkills($skills[$index % count($skills)])
                ->setLanguages([
                    ['name' => 'Francais', 'level' => 'Courant'],
                    ['name' => 'Moore', 'level' => 'Conversationnel'],
                ])
                ->setDrivingLicense($index % 3 === 0)
                ->setDrivingLicenseCategory($index % 3 === 0 ? 'B' : null)
                ->setAvailability(['Immediate', '1 mois', '3 mois'][$index % 3])
                ->setSalaryExpectation(100000 + ($index * 25000));

            $manager->persist($user);
            $manager->persist($profile);
            $users[] = $user;
        }

        return $users;
    }

    /**
     * @param User[] $employerUsers
     *
     * @return JobOffer[]
     */
    private function loadJobOffers(ObjectManager $manager, array $employerUsers): array
    {
        $titles = [
            'Developpeur Symfony Junior',
            'Commercial terrain',
            'Assistant comptable',
            'Agent de saisie',
            'Technicien maintenance',
            'Charge RH',
            'Community manager',
            'Chauffeur livreur',
            'Gestionnaire de stock',
            'Assistant administratif',
            'Infirmier diplome',
            'Conducteur de travaux',
            'Analyste credit',
            'Developpeur Flutter',
            'Responsable boutique',
            'Agent call center',
            'Comptable senior',
            'Technicien reseau',
            'Chef de projet junior',
            'Magasinier',
        ];

        $offers = [];
        foreach ($titles as $index => $title) {
            $offer = (new JobOffer())
                ->setEmployer($employerUsers[$index % count($employerUsers)])
                ->setTitle($title)
                ->setDescription($title . ' recherche pour renforcer une equipe locale. Le poste exige rigueur, ponctualite et envie d apprendre.')
                ->setRequiredSkills($this->skillsForOffer($index))
                ->setRequiredEducation(['BEPC', 'Bac', 'Licence', 'Master'][$index % 4])
                ->setRequiredExperienceYears($index % 5)
                ->setContractType([ContractType::Cdi, ContractType::Cdd, ContractType::Stage, ContractType::Freelance, ContractType::LocalContract][$index % 5])
                ->setLocation($index % 3 === 0 ? 'Bobo-Dioulasso' : 'Ouagadougou')
                ->setSalaryMin(75000 + ($index * 10000))
                ->setSalaryMax(150000 + ($index * 15000))
                ->setIsRemoteAllowed($index % 6 === 0)
                ->setRequiredDocuments($index % 2 === 0 ? ['CV', 'Diplome'] : ['CV'])
                ->setRecommendedDocuments($index % 3 === 0 ? ['Permis B'] : ['Certificat'])
                ->setDeadline(new \DateTimeImmutable(sprintf('+%d days', 15 + $index)))
                ->setIsBoosted($index < 3)
                ->setViewsCount(40 + ($index * 7))
                ->setApplicationsCount($index % 9)
                ->setStatus(JobOfferStatus::Active);

            $manager->persist($offer);
            $offers[] = $offer;
        }

        return $offers;
    }

    /**
     * @param User[]     $candidateUsers
     * @param JobOffer[] $offers
     */
    private function loadSwipes(ObjectManager $manager, array $candidateUsers, array $offers): void
    {
        $createdPairs = [];
        $count = 0;

        foreach ($candidateUsers as $candidateIndex => $candidate) {
            foreach ($offers as $offerIndex => $offer) {
                if ($count >= 50) {
                    return;
                }

                if (($candidateIndex + $offerIndex) % 4 === 0) {
                    continue;
                }

                $key = $candidateIndex . '-' . $offerIndex;
                if (isset($createdPairs[$key])) {
                    continue;
                }

                $direction = [$count % 5 === 0 ? SwipeDirection::Superlike : SwipeDirection::Like, SwipeDirection::Dislike][$count % 2];
                $status = [SwipeStatus::Sent, SwipeStatus::Viewed, SwipeStatus::Interview, SwipeStatus::Rejected][$count % 4];

                $swipe = (new Swipe())
                    ->setCandidate($candidate)
                    ->setOffer($offer)
                    ->setDirection($direction)
                    ->setMatchScore(45 + (($candidateIndex * 9 + $offerIndex * 4) % 55))
                    ->setCvUsedUrl($direction === SwipeDirection::Dislike ? null : 'https://example.test/cv/generated-' . ($candidateIndex + 1) . '.pdf')
                    ->setMotivationLetterText($direction === SwipeDirection::Dislike ? null : 'Lettre de motivation generee localement pour cette offre.')
                    ->setDocumentsSent($direction === SwipeDirection::Dislike ? null : [])
                    ->setStatus($status)
                    ->setSentAt(new \DateTimeImmutable(sprintf('-%d days', $count % 20)));

                if ($status !== SwipeStatus::Sent) {
                    $swipe->setViewedAt(new \DateTimeImmutable(sprintf('-%d days', max(0, ($count % 20) - 1))));
                }

                $manager->persist($swipe);
                $createdPairs[$key] = true;
                ++$count;
            }
        }
    }

    /**
     * @return string[]
     */
    private function skillsForOffer(int $index): array
    {
        $skillGroups = [
            ['PHP', 'Symfony', 'MySQL'],
            ['Vente', 'Negociation', 'Terrain'],
            ['Comptabilite', 'Excel', 'Organisation'],
            ['Saisie', 'Pack Office', 'Rigueur'],
            ['Maintenance', 'Diagnostic', 'Electricite'],
        ];

        return $skillGroups[$index % count($skillGroups)];
    }
}
