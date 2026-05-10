<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class JobDescriptionExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('job_description_text', [$this, 'toReadableText']),
        ];
    }

    public function toReadableText(?string $description): string
    {
        if (null === $description || '' === trim($description)) {
            return '';
        }

        $text = $this->fixMojibake($description);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\s*\/\s*p\s*>/i', "\n\n", $text) ?? $text;
        $text = preg_replace('/<\s*p\b[^>]*>/i', '', $text) ?? $text;
        $text = preg_replace('/<\s*li\b[^>]*>/i', "\n- ", $text) ?? $text;
        $text = preg_replace('/<\s*\/\s*li\s*>/i', '', $text) ?? $text;
        $text = preg_replace('/<\s*\/?\s*(ul|ol)\b[^>]*>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $this->fixMojibake($text);
        $text = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $text);
        $text = str_replace(["\xc2\xa0", "\t"], [' ', ' '], $text);
        $text = preg_replace('/[ ]{2,}/', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function fixMojibake(string $text): string
    {
        return strtr($text, [
            "\u{00e2}\u{0080}\u{0099}" => "'",
            "\u{00e2}\u{20ac}\u{2122}" => "'",
            "\u{00e2}\u{0080}\u{0098}" => "'",
            "\u{00e2}\u{20ac}\u{02dc}" => "'",
            "\u{00e2}\u{0080}\u{009c}" => '"',
            "\u{00e2}\u{20ac}\u{0153}" => '"',
            "\u{00e2}\u{0080}\u{009d}" => '"',
            "\u{00e2}\u{20ac}\u{009d}" => '"',
            "\u{00e2}\u{0080}\u{0093}" => '-',
            "\u{00e2}\u{20ac}\u{201c}" => '-',
            "\u{00e2}\u{0080}\u{0094}" => '-',
            "\u{00e2}\u{20ac}\u{201d}" => '-',
            "\u{00e2}\u{0080}\u{00a2}" => '-',
            "\u{00e2}\u{20ac}\u{00a2}" => '-',
            "\u{00e2}\u{0080}\u{00a6}" => '...',
            "\u{00e2}\u{20ac}\u{00a6}" => '...',
            "\u{00c2}\u{00a0}" => ' ',
            "\u{00c2}\u{00ab}" => '"',
            "\u{00c2}\u{00bb}" => '"',
            "\u{00c3}\u{0080}" => "\u{00c0}",
            "\u{00c3}\u{0082}" => "\u{00c2}",
            "\u{00c3}\u{0087}" => "\u{00c7}",
            "\u{00c3}\u{0089}" => "\u{00c9}",
            "\u{00c3}\u{0088}" => "\u{00c8}",
            "\u{00c3}\u{008a}" => "\u{00ca}",
            "\u{00c3}\u{00a0}" => "\u{00e0}",
            "\u{00c3}\u{00a2}" => "\u{00e2}",
            "\u{00c3}\u{00a7}" => "\u{00e7}",
            "\u{00c3}\u{00a9}" => "\u{00e9}",
            "\u{00c3}\u{00a8}" => "\u{00e8}",
            "\u{00c3}\u{00aa}" => "\u{00ea}",
            "\u{00c3}\u{00ae}" => "\u{00ee}",
            "\u{00c3}\u{00b4}" => "\u{00f4}",
            "\u{00c3}\u{00b9}" => "\u{00f9}",
            "\u{00c3}\u{00bb}" => "\u{00fb}",
        ]);
    }
}
