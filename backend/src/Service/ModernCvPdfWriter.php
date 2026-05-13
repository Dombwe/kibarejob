<?php

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;

class ModernCvPdfWriter
{
    private const PAGE_WIDTH = 595.0;
    private const PAGE_HEIGHT = 842.0;
    private const MARGIN = 42.0;
    private const LEFT_WIDTH = 142.0;
    private const GAP = 22.0;
    private const RIGHT_WIDTH = 347.0;
    private const BODY_TOP = 722.0;
    private const BODY_BOTTOM = 52.0;

    /**
     * @param array<string, mixed> $payload
     */
    public function writeGenerated(string $path, CandidateProfile $profile, array $payload): void
    {
        $this->write($path, [
            'name' => $this->fullName($profile),
            'title' => $this->stringValue($payload['jobTitle'] ?? '') ?: 'Candidat',
            'contact' => $this->contact($profile, $payload),
            'skills' => $this->stringList($payload['skills'] ?? $profile->getSkills()),
            'languages' => $this->languages($payload['languages'] ?? $profile->getLanguages()),
            'interests' => $this->stringList($payload['interests'] ?? $profile->getInterests()),
            'summary' => $this->stringValue($payload['summary'] ?? '') ?: $this->defaultSummary($profile),
            'experiences' => $this->stringList($payload['experiences'] ?? $profile->getExperiences()),
            'education' => $this->education($profile, $payload),
            'projects' => $this->stringList($payload['projects'] ?? $payload['certifications'] ?? []),
        ]);
    }

    public function writeAdapted(string $path, CandidateProfile $profile, JobOffer $offer): void
    {
        $company = $offer->getEmployer()->getEmployer()?->getCompanyName() ?: 'Entreprise';
        $matchedSkills = $this->matchedSkills($profile, $offer);

        $this->write($path, [
            'name' => $this->fullName($profile),
            'title' => $offer->getTitle(),
            'contact' => $this->contact($profile),
            'skills' => [] === $matchedSkills ? $profile->getSkills() : $matchedSkills,
            'languages' => $this->languages($profile->getLanguages()),
            'interests' => $profile->getInterests(),
            'summary' => sprintf(
                'Profil disponible %s, basé à %s, avec des compétences alignées sur le poste %s chez %s.',
                $profile->getAvailability() ?: 'selon vos besoins',
                $profile->getCity() ?: 'Non renseigné',
                $offer->getTitle(),
                $company,
            ),
            'experiences' => $this->rankedExperiences($profile, $offer),
            'education' => $this->education($profile),
            'projects' => $this->stringList($offer->getRequiredDocuments() ?? []),
            'projectsTitle' => 'Documents préparés',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function write(string $path, array $data): void
    {
        $pages = [];
        $stream = '';
        $y = self::BODY_TOP;
        $pageNumber = 0;

        $newPage = function (bool $continued = false) use (&$pages, &$stream, &$y, &$pageNumber, $data): void {
            if ('' !== $stream) {
                $pages[] = $stream;
            }

            ++$pageNumber;
            $stream = '';
            $y = self::BODY_TOP;
            $this->drawPageFrame($stream, $data, $continued);
        };

        $newPage(false);
        $this->drawLeftColumn($stream, $data);

        $this->drawRightSection($stream, $y, 'Profil / A propos', $this->paragraphs($data['summary'] ?? ''), $newPage);
        $this->drawRightSection($stream, $y, 'Expériences professionnelles', $this->items($data['experiences'] ?? []), $newPage);
        $this->drawRightSection($stream, $y, 'Formation', $this->items($data['education'] ?? []), $newPage);

        $projects = $this->items($data['projects'] ?? []);
        if ([] !== $projects) {
            $this->drawRightSection($stream, $y, $this->stringValue($data['projectsTitle'] ?? '') ?: 'Certifications / Projets clés', $projects, $newPage);
        }

        $pages[] = $stream;
        $this->savePdf($path, $pages);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function drawPageFrame(string &$stream, array $data, bool $continued): void
    {
        $name = mb_strtoupper($this->stringValue($data['name'] ?? 'Candidat'), 'UTF-8');
        $title = $this->stringValue($data['title'] ?? 'Candidat');

        $this->rect($stream, 0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT, [1, 1, 1]);
        $this->rect($stream, self::MARGIN, self::BODY_BOTTOM, self::LEFT_WIDTH, self::BODY_TOP - self::BODY_BOTTOM + 12, [0.965, 0.973, 0.980]);
        $this->line($stream, self::MARGIN + self::LEFT_WIDTH + 10, self::BODY_BOTTOM, self::MARGIN + self::LEFT_WIDTH + 10, self::BODY_TOP + 12, [0.82, 0.86, 0.90], 0.8);
        $this->text($stream, self::MARGIN, 790, $name, 'F2', $continued ? 16 : 23, [0.15, 0.22, 0.29]);
        $this->text($stream, self::MARGIN, 766, $continued ? $title . ' - suite' : $title, 'F1', 11.5, [0.37, 0.50, 0.60]);
        $this->line($stream, self::MARGIN, 748, self::PAGE_WIDTH - self::MARGIN, 748, [0.72, 0.78, 0.71], 1.2);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function drawLeftColumn(string &$stream, array $data): void
    {
        $y = self::BODY_TOP - 8;
        $this->drawLeftSection($stream, $y, 'Contact', $this->contactLines($data['contact'] ?? []));
        $this->drawLeftSection($stream, $y, 'Compétences', $this->items($data['skills'] ?? []));
        $this->drawLeftSection($stream, $y, 'Langues', $this->items($data['languages'] ?? []));

        $interests = $this->items($data['interests'] ?? []);
        if ([] !== $interests) {
            $this->drawLeftSection($stream, $y, 'Centres d’intérêt', $interests);
        }
    }

    /**
     * @param string[] $lines
     */
    private function drawLeftSection(string &$stream, float &$y, string $title, array $lines): void
    {
        if ([] === $lines || $y < 90) {
            return;
        }

        $this->text($stream, self::MARGIN + 14, $y, mb_strtoupper($title, 'UTF-8'), 'F2', 9.5, [0.15, 0.22, 0.29]);
        $y -= 10;
        $this->line($stream, self::MARGIN + 14, $y, self::MARGIN + self::LEFT_WIDTH - 14, $y, [0.72, 0.78, 0.71], 0.7);
        $y -= 15;

        foreach ($lines as $line) {
            foreach ($this->wrap($line, 25) as $chunk) {
                if ($y < 62) {
                    return;
                }
                $this->text($stream, self::MARGIN + 18, $y, $chunk, 'F1', 8.9, [0.28, 0.34, 0.42]);
                $y -= 12;
            }
            $y -= 2;
        }

        $y -= 12;
    }

    /**
     * @param string[] $lines
     * @param callable(bool): void $newPage
     */
    private function drawRightSection(string &$stream, float &$y, string $title, array $lines, callable $newPage): void
    {
        if ([] === $lines) {
            return;
        }

        $this->ensureSpace($stream, $y, 54, $newPage);
        $x = self::MARGIN + self::LEFT_WIDTH + self::GAP;
        $this->text($stream, $x, $y, mb_strtoupper($title, 'UTF-8'), 'F2', 11.5, [0.15, 0.22, 0.29]);
        $y -= 10;
        $this->line($stream, $x, $y, $x + self::RIGHT_WIDTH, $y, [0.72, 0.78, 0.71], 0.8);
        $y -= 17;

        foreach ($lines as $line) {
            $line = $this->stringValue($line);
            if ('' === $line) {
                continue;
            }

            $isBullet = str_starts_with($line, '- ');
            $text = $isBullet ? mb_substr($line, 2, null, 'UTF-8') : $line;
            $wrapped = $this->wrap($text, $isBullet ? 64 : 70);
            $height = max(1, count($wrapped)) * 12 + 4;
            $this->ensureSpace($stream, $y, $height, $newPage);

            if ($isBullet) {
                $this->text($stream, $x + 4, $y, '-', 'F2', 10.2, [0.37, 0.50, 0.60]);
                $textX = $x + 15;
            } else {
                $textX = $x;
            }

            foreach ($wrapped as $index => $chunk) {
                $font = (!$isBullet && 0 === $index && $this->looksLikeHeading($chunk)) ? 'F2' : 'F1';
                $this->text($stream, $textX, $y, $chunk, $font, 10.2, [0.25, 0.30, 0.38]);
                $y -= 12;
            }
            $y -= 4;
        }

        $y -= 10;
    }

    /**
     * @param callable(bool): void $newPage
     */
    private function ensureSpace(string &$stream, float &$y, float $height, callable $newPage): void
    {
        if ($y - $height < self::BODY_BOTTOM) {
            $newPage(true);
        }
    }

    /**
     * @param string[] $pages
     */
    private function savePdf(string $path, array $pages): void
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $pageObjectIds = [];
        $nextObjectId = 5;

        foreach ($pages as $pageStream) {
            $contentObjectId = $nextObjectId++;
            $pageObjectId = $nextObjectId++;
            $pageObjectIds[] = $pageObjectId;
            $objects[$contentObjectId] = "<< /Length " . strlen($pageStream) . " >>\nstream\n" . $pageStream . "endstream";
            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
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
        for ($id = 1; $id <= $maxId; ++$id) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        file_put_contents($path, $pdf);
    }

    /**
     * @param array{0: float, 1: float, 2: float} $color
     */
    private function text(string &$stream, float $x, float $y, string $text, string $font, float $size, array $color): void
    {
        $stream .= sprintf(
            "BT\n%.3F %.3F %.3F rg\n/%s %.2F Tf\n1 0 0 1 %.2F %.2F Tm\n(%s) Tj\nET\n",
            $color[0],
            $color[1],
            $color[2],
            $font,
            $size,
            $x,
            $y,
            $this->pdfEscape($text),
        );
    }

    /**
     * @param array{0: float, 1: float, 2: float} $color
     */
    private function rect(string &$stream, float $x, float $y, float $w, float $h, array $color): void
    {
        $stream .= sprintf("q\n%.3F %.3F %.3F rg\n%.2F %.2F %.2F %.2F re f\nQ\n", $color[0], $color[1], $color[2], $x, $y, $w, $h);
    }

    /**
     * @param array{0: float, 1: float, 2: float} $color
     */
    private function line(string &$stream, float $x1, float $y1, float $x2, float $y2, array $color, float $width): void
    {
        $stream .= sprintf("q\n%.3F %.3F %.3F RG\n%.2F w\n%.2F %.2F m %.2F %.2F l S\nQ\n", $color[0], $color[1], $color[2], $width, $x1, $y1, $x2, $y2);
    }

    private function pdfEscape(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n", "\t"], [' ', ' ', ' ', ' '], $value);
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        $encoded = false === $encoded ? $value : $encoded;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    /**
     * @return string[]
     */
    private function wrap(string $text, int $maxChars): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if ('' === $text) {
            return [];
        }

        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', $text) ?: [] as $word) {
            $candidate = '' === $line ? $word : $line . ' ' . $word;
            if (mb_strlen($candidate, 'UTF-8') > $maxChars && '' !== $line) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ('' !== $line) {
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @return string[]
     */
    private function items(mixed $value): array
    {
        return array_map(
            static fn (string $item): string => str_starts_with($item, '- ') ? $item : '- ' . $item,
            $this->stringList($value),
        );
    }

    /**
     * @return string[]
     */
    private function paragraphs(mixed $value): array
    {
        return $this->stringList(is_array($value) ? $value : preg_split('/\n+/', (string) $value));
    }

    /**
     * @return string[]
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,;\n]+/', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $item = implode(' - ', array_filter(array_map([$this, 'stringValue'], $item)));
            }
            $item = $this->documentLabelFromMixed($item);
            if ('' !== $item) {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @return string[]
     */
    private function languages(mixed $value): array
    {
        if (!is_array($value)) {
            return $this->stringList($value);
        }

        $languages = [];
        foreach ($value as $language) {
            if (is_array($language)) {
                $name = $this->stringValue($language['name'] ?? '');
                $level = $this->stringValue($language['level'] ?? '');
                $label = trim($name . ('' !== $level ? ' (' . $level . ')' : ''));
            } else {
                $label = $this->stringValue($language);
            }
            if ('' !== $label) {
                $languages[] = $label;
            }
        }

        return $languages;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function contact(CandidateProfile $profile, array $payload = []): array
    {
        return array_filter([
            'Téléphone' => $profile->getUser()->getPhone() ?: $this->stringValue($payload['phone'] ?? ''),
            'Email' => $profile->getUser()->getEmail(),
            'Ville' => $profile->getCity(),
            'Disponibilité' => $profile->getAvailability(),
            'LinkedIn' => $this->stringValue($payload['linkedin'] ?? ''),
            'Portfolio' => $this->stringValue($payload['portfolio'] ?? ''),
        ], static fn (?string $value): bool => null !== $value && '' !== trim($value));
    }

    /**
     * @param mixed $contact
     * @return string[]
     */
    private function contactLines(mixed $contact): array
    {
        if (!is_array($contact)) {
            return [];
        }

        $lines = [];
        foreach ($contact as $label => $value) {
            $value = $this->stringValue($value);
            if ('' !== $value) {
                $lines[] = $label . ' : ' . $value;
            }
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $payload
     * @return string[]
     */
    private function education(CandidateProfile $profile, array $payload = []): array
    {
        $items = [];
        $main = trim($profile->getEducationLevel() . ' - ' . (string) $profile->getEducationField(), ' -');
        if ('' !== $main) {
            $items[] = $main;
        }
        $items = array_merge($items, $this->stringList($payload['education'] ?? []));

        return [] === $items ? ['Non renseignée'] : $items;
    }

    private function defaultSummary(CandidateProfile $profile): string
    {
        $skills = array_slice($profile->getSkills(), 0, 3);

        return sprintf(
            'Profil basé à %s, disponible %s%s.',
            $profile->getCity() ?: 'Non renseigné',
            $profile->getAvailability() ?: 'selon vos besoins',
            [] === $skills ? '' : ', avec des compétences en ' . implode(', ', $skills),
        );
    }

    private function fullName(CandidateProfile $profile): string
    {
        return trim($profile->getFirstName() . ' ' . $profile->getLastName()) ?: 'Candidat Kibaré Job';
    }

    private function looksLikeHeading(string $value): bool
    {
        return str_contains($value, ' - ') || str_contains($value, ' chez ') || str_contains($value, ' – ');
    }

    private function stringValue(mixed $value): string
    {
        return trim((string) $value);
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

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = false === $converted ? $value : $converted;
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
