<?php

namespace App\Ai\Llm;

final readonly class LlmResponse
{
    /**
     * @param  list<array<string, mixed>>  $content  normalised snake_case blocks
     */
    public function __construct(
        public array $content,
        public string $stopReason,
        public string $model,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheReadTokens = 0,
        public int $cacheWriteTokens = 0,
        public bool $mock = false,
    ) {}

    public function text(): string
    {
        $parts = [];
        foreach ($this->content as $block) {
            if (($block['type'] ?? null) === 'text') {
                $parts[] = (string) $block['text'];
            }
        }

        return implode("\n", $parts);
    }

    /** @return list<array<string, mixed>> tool_use blocks (type, id, name, input) */
    public function toolUses(): array
    {
        return array_values(array_filter($this->content, fn (array $b) => ($b['type'] ?? null) === 'tool_use'));
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens + $this->cacheReadTokens + $this->cacheWriteTokens;
    }
}
