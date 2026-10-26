<?php

namespace App\Ai\Llm;

use App\Ai\Tools\Tool;

/**
 * Provider-neutral request. Messages use the API's snake_case block shapes
 * (text, tool_use, tool_result, thinking) so they can be persisted and replayed as-is.
 */
final readonly class LlmRequest
{
    /**
     * @param  list<array{role: string, content: string|list<array<string, mixed>>}>  $messages
     * @param  list<Tool>  $tools
     * @param  array<string, mixed>|null  $jsonSchema  structured output schema
     */
    public function __construct(
        public string $feature,
        public string $system,
        public array $messages,
        public array $tools = [],
        public ?array $jsonSchema = null,
        public ?string $effort = null,
        public int $maxTokens = 16000,
    ) {}
}
