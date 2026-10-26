<?php

namespace App\Ai\Knowledge;

final readonly class SearchHit
{
    public function __construct(
        public int $documentId,
        public int $chunkId,
        public int $ordinal,
        public string $documentTitle,
        public string $heading,
        public string $content,
        public float $score,
        public bool $tenantDocument,
    ) {}

    /** Short, stable id the model cites and the UI turns into a link. */
    public function citation(): string
    {
        return "D{$this->documentId}§{$this->ordinal}";
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'citation' => $this->citation(),
            'document_id' => $this->documentId,
            'document' => $this->documentTitle,
            'section' => $this->heading,
            'text' => $this->content,
            'score' => round($this->score, 4),
            'source' => $this->tenantDocument ? 'customer document' : 'platform library',
        ];
    }
}
