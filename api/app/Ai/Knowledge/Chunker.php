<?php

namespace App\Ai\Knowledge;

/**
 * Splits markdown by headings, then by paragraphs up to ~maxChars. Each chunk keeps its
 * heading path ("Wind limits > Out of service") so a passage is understandable on its own
 * and citations point somewhere meaningful.
 */
class Chunker
{
    public function __construct(private readonly int $maxChars = 1200) {}

    /** @return list<array{heading: string, content: string}> */
    public function chunk(string $title, string $markdown): array
    {
        $sections = [];
        $current = ['heading' => $title, 'lines' => []];
        $path = [];
        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (preg_match('/^(#{1,4})\s+(.*)$/', $line, $m)) {
                $level = strlen($m[1]);
                if ($level === 1) {
                    continue; // document title
                }
                $sections[] = $current;
                $path = array_slice($path, 0, $level - 2);
                $path[] = trim($m[2]);
                $current = ['heading' => implode(' > ', $path), 'lines' => []];

                continue;
            }
            $current['lines'][] = $line;
        }
        $sections[] = $current;

        $chunks = [];
        foreach ($sections as $section) {
            $text = trim(implode("\n", $section['lines']));
            if ($text === '') {
                continue;
            }
            foreach ($this->split($text) as $part) {
                $chunks[] = ['heading' => $section['heading'], 'content' => $part];
            }
        }

        return $chunks;
    }

    /** @return list<string> */
    private function split(string $text): array
    {
        if (strlen($text) <= $this->maxChars) {
            return [$text];
        }
        $parts = [];
        $current = '';
        foreach (preg_split('/\n\s*\n/', $text) ?: [] as $paragraph) {
            if ($current !== '' && strlen($current) + strlen($paragraph) > $this->maxChars) {
                $parts[] = trim($current);
                $current = '';
            }
            $current .= $paragraph."\n\n";
        }
        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }
}
