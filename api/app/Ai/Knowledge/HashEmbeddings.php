<?php

namespace App\Ai\Knowledge;

/**
 * Offline stand-in for a real embedding model: feature-hashed bag of word stems and bigrams.
 * Purely lexical (no synonyms), but deterministic and good enough for the demo, CI and
 * retrieval tests. Hybrid search pairs it with Postgres full-text search anyway.
 */
class HashEmbeddings implements Embeddings
{
    public function __construct(private readonly int $dimensions = 1024) {}

    public function embed(array $texts, string $inputType): array
    {
        return array_map($this->vector(...), $texts);
    }

    public function model(): string
    {
        return 'hash-bow-'.$this->dimensions;
    }

    /** @return list<float> */
    private function vector(string $text): array
    {
        $v = array_fill(0, $this->dimensions, 0.0);
        $words = preg_split('/[^a-z0-9%]+/', strtolower($text), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter(array_map($this->stem(...), $words), fn ($w) => strlen($w) > 2 && ! in_array($w, self::STOP, true)));
        foreach ($words as $i => $w) {
            $this->add($v, $w, 1.0);
            if (isset($words[$i + 1])) {
                $this->add($v, $w.' '.$words[$i + 1], 0.5);
            }
        }
        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $v))) ?: 1.0;

        return array_values(array_map(fn ($x) => $x / $norm, $v));
    }

    /** @param array<int, float> $v */
    private function add(array &$v, string $feature, float $weight): void
    {
        $h = crc32($feature);
        $v[$h % $this->dimensions] += ($h & 0x80000000) ? -$weight : $weight;
    }

    private function stem(string $w): string
    {
        return (string) preg_replace('/(ing|ed|es|s)$/', '', $w);
    }

    private const STOP = ['the', 'and', 'for', 'with', 'are', 'this', 'that', 'from', 'what', 'when', 'how', 'should', 'into', 'any', 'all', 'can', 'not', 'our', 'you', 'its', 'has', 'have', 'was', 'were', 'will', 'must', 'per'];
}
