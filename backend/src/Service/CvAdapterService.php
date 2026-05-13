<?php

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;

class CvAdapterService
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $storagePath,
        private readonly ModernCvPdfWriter $modernCvPdfWriter,
    )
    {
    }

    public function adaptCv(CandidateProfile $candidate, JobOffer $offer): string
    {
        $sourceUrl = $candidate->getCvGeneratedUrl() ?: $candidate->getCvOriginalUrl();
        $dir = $this->resolveStorageRoot()
            . DIRECTORY_SEPARATOR . 'candidates' . DIRECTORY_SEPARATOR . (string) $candidate->getUser()->getId()
            . DIRECTORY_SEPARATOR . 'generated';

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return $sourceUrl ?: '';
        }

        $fileName = 'cv-adapte-' . $this->slug($offer->getTitle()) . '-' . date('YmdHis') . '.pdf';
        $path = $dir . DIRECTORY_SEPARATOR . $fileName;

        try {
            $this->modernCvPdfWriter->writeAdapted($path, $candidate, $offer);
        } catch (\Throwable) {
            return $sourceUrl ?: '';
        }

        return is_file($path)
            ? '/storage/candidates/' . (string) $candidate->getUser()->getId() . '/generated/' . $fileName
            : ($sourceUrl ?: '');
    }

    private function writeCvPdf(string $path, CandidateProfile $candidate, JobOffer $offer): void
    {
        $matchedSkills = $this->matchedSkills($candidate, $offer);
        $company = $offer->getEmployer()->getEmployer()?->getCompanyName() ?: 'Entreprise';
        $requiredSkills = array_values(array_filter(array_map(static fn (mixed $skill): string => trim((string) $skill), $offer->getRequiredSkills() ?? [])));
        $requiredDocuments = array_values(array_filter(array_map([$this, 'documentLabelFromMixed'], $offer->getRequiredDocuments() ?? [])));

        $lines = [
            mb_strtoupper(trim($candidate->getFirstName() . ' ' . $candidate->getLastName()) ?: 'Candidat Kibaré Job', 'UTF-8'),
            $offer->getTitle(),
            '',
            'Profil ciblé',
            sprintf(
                'Candidat disponible %s, basé à %s, avec un profil adapté au poste %s chez %s.',
                $candidate->getAvailability() ?: 'selon vos besoins',
                $candidate->getCity() ?: 'Non renseigné',
                $offer->getTitle(),
                $company,
            ),
        ];

        if ([] !== $requiredSkills) {
            $lines[] = 'Compétences attendues par l’offre : ' . implode(', ', array_slice($requiredSkills, 0, 8));
        }

        array_push(
            $lines,
            '',
            'Compétences mises en avant',
            [] === $matchedSkills ? implode(', ', array_slice($candidate->getSkills(), 0, 8)) : implode(', ', $matchedSkills),
            '',
            'Expériences pertinentes'
        );

        foreach (array_slice($this->rankedExperiences($candidate, $offer), 0, 6) as $experience) {
            $lines[] = '- ' . (string) $experience;
        }

        $lines[] = '';
        $lines[] = 'Formation';
        $lines[] = trim($candidate->getEducationLevel() . ' - ' . (string) $candidate->getEducationField(), ' -') ?: 'Non renseignée';

        if ([] !== $requiredDocuments) {
            $lines[] = '';
            $lines[] = 'Documents préparés';
            foreach (array_slice($requiredDocuments, 0, 8) as $document) {
                $lines[] = '- ' . $document;
            }
        }

        $lines[] = '';
        $lines[] = 'Informations complémentaires';
        $lines[] = 'Email : ' . $candidate->getUser()->getEmail();
        $lines[] = 'Disponibilité : ' . ($candidate->getAvailability() ?: 'Non renseignée');

        $this->writeTextPdf($path, $lines);
    }

    private function resolveStorageRoot(): string
    {
        if (str_starts_with($this->storagePath, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $this->storagePath)) {
            return rtrim($this->storagePath, '/\\');
        }

        return $this->projectDir . DIRECTORY_SEPARATOR . trim($this->storagePath, '/\\');
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

    /**
     * @return string[]
     */
    private function rankedExperiences(CandidateProfile $candidate, JobOffer $offer): array
    {
        $offerText = $this->normalize($offer->getTitle() . ' ' . $offer->getDescription() . ' ' . implode(' ', $offer->getRequiredSkills() ?? []));
        $ranked = [];

        foreach ($candidate->getExperiences() as $index => $experience) {
            $text = trim((string) $experience);
            if ('' === $text) {
                continue;
            }

            $score = 0;
            foreach (preg_split('/\s+/', $this->normalize($text)) ?: [] as $word) {
                if (mb_strlen($word) >= 4 && str_contains($offerText, $word)) {
                    ++$score;
                }
            }
            $ranked[] = ['score' => $score, 'index' => $index, 'text' => $text];
        }

        usort($ranked, static fn (array $a, array $b): int => [$b['score'], -$a['index']] <=> [$a['score'], -$b['index']]);

        return array_map(static fn (array $item): string => $item['text'], $ranked);
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

    /**
     * @param string[] $lines
     */
    private function writeTextPdf(string $path, array $lines): void
    {
        $wrapped = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ('' === $line) {
                $wrapped[] = '';
                continue;
            }

            foreach (explode("\n", wordwrap($line, 86, "\n", true)) as $chunk) {
                $wrapped[] = $chunk;
            }
        }

        $pages = array_chunk($wrapped, 42) ?: [[]];
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pageObjectIds = [];
        $nextObjectId = 4;

        foreach ($pages as $pageLines) {
            $contentObjectId = $nextObjectId++;
            $pageObjectId = $nextObjectId++;
            $pageObjectIds[] = $pageObjectId;
            $stream = "BT\n/F1 11 Tf\n50 790 Td\n15 TL\n";
            foreach ($pageLines as $index => $line) {
                $stream .= (0 === $index && '' !== $line ? "/F1 17 Tf\n" : "/F1 11 Tf\n");
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
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
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

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = false === $converted ? $value : $converted;
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function slug(string $value): string
    {
        return trim((string) strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $value)), '-') ?: 'offre';
    }
}
