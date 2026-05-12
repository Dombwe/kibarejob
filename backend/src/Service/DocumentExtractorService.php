<?php

namespace App\Service;

use Smalot\PdfParser\Parser;

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

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if ('docx' === $extension) {
            $text = $this->extractDocx($absolutePath);
            if ('' !== trim($text)) {
                return ['text' => $this->cleanText($text), 'method' => 'docx_xml', 'confidence' => 75];
            }
        }

        if ('pdf' === $extension || 'application/pdf' === $mimeType) {
            $text = $this->extractPdf($absolutePath);
            if ('' !== trim($text)) {
                return ['text' => $this->cleanText($text), 'method' => 'pdf_parser', 'confidence' => 80];
            }
        }

        if (str_starts_with((string) $mimeType, 'text/')) {
            $text = file_get_contents($absolutePath);
            if (is_string($text) && '' !== trim($text)) {
                return ['text' => $this->cleanText($text), 'method' => 'plain_text', 'confidence' => 80];
            }
        }

        if ($this->canUseTesseract($mimeType)) {
            $text = $this->runTesseract($absolutePath);
            if ('' !== trim($text)) {
                return ['text' => $this->cleanText($text), 'method' => 'tesseract', 'confidence' => 70];
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

    private function extractDocx(string $absolutePath): string
    {
        if (!class_exists(\ZipArchive::class)) {
            return '';
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($absolutePath)) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!is_string($xml)) {
            return '';
        }

        $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function extractPdf(string $absolutePath): string
    {
        if (class_exists(Parser::class)) {
            try {
                $parser = new Parser();
                $pdf = $parser->parseFile($absolutePath);
                $text = $pdf->getText();
                if ('' !== trim($text)) {
                    return $text;
                }
            } catch (\Throwable) {
                // Fallback below keeps extraction best-effort for uncommon PDFs.
            }
        }

        $content = file_get_contents($absolutePath);
        if (!is_string($content) || '' === $content) {
            return '';
        }

        $parts = [];
        if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress(ltrim($stream));
                $parts[] = is_string($decoded) ? $decoded : $stream;
            }
        }

        $source = implode("\n", $parts);
        if ('' === trim($source)) {
            $source = $content;
        }

        $texts = [];
        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', $source, $matches)) {
            foreach ($matches[0] as $value) {
                $texts[] = $this->decodePdfString(substr($value, 1, -1));
            }
        }

        if (preg_match_all('/<([0-9A-Fa-f]{4,})>/', $source, $hexMatches)) {
            foreach ($hexMatches[1] as $hex) {
                $decoded = @hex2bin($hex);
                if (is_string($decoded)) {
                    $texts[] = mb_convert_encoding($decoded, 'UTF-8', 'UTF-16BE,UTF-8,ISO-8859-1');
                }
            }
        }

        return implode(' ', $texts);
    }

    private function decodePdfString(string $value): string
    {
        $value = preg_replace('/\\\\([nrtbf])/', ' ', $value) ?? $value;
        $value = preg_replace('/\\\\([()\\\\])/', '$1', $value) ?? $value;
        $value = preg_replace_callback('/\\\\([0-7]{1,3})/', static fn (array $match): string => chr(octdec($match[1])), $value) ?? $value;

        return $value;
    }

    private function cleanText(string $text): string
    {
        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if (is_string($converted)) {
            $text = $converted;
        } else {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[^\P{C}\r\n\t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
