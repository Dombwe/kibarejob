<?php

namespace App\Service;

class DocumentExtractorService
{
    public function __construct(private readonly ?string $tesseractBinary = null)
    {
    }

    /**
     * @return array{text: string, method: string, confidence: int}
     */
    public function extract(string $absolutePath, ?string $mimeType = null): array
    {
        if (!is_file($absolutePath)) {
            return ['text' => '', 'method' => 'missing_file', 'confidence' => 0];
        }

        if ($this->canUseTesseract($mimeType)) {
            $text = $this->runTesseract($absolutePath);
            if ('' !== trim($text)) {
                return ['text' => trim($text), 'method' => 'tesseract', 'confidence' => 70];
            }
        }

        $fileName = pathinfo($absolutePath, PATHINFO_FILENAME);
        $normalized = str_replace(['-', '_'], ' ', $fileName);

        return [
            'text' => trim($normalized),
            'method' => 'local_filename_fallback',
            'confidence' => 20,
        ];
    }

    private function canUseTesseract(?string $mimeType): bool
    {
        return null !== $this->tesseractBinary
            && '' !== $this->tesseractBinary
            && in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/tiff'], true);
    }

    private function runTesseract(string $absolutePath): string
    {
        $command = sprintf('%s %s stdout 2>NUL', escapeshellcmd((string) $this->tesseractBinary), escapeshellarg($absolutePath));
        $output = shell_exec($command);

        return is_string($output) ? $output : '';
    }
}
