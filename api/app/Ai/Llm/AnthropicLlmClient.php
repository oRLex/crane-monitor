<?php

namespace App\Ai\Llm;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Ai\Tools\Tool;

/**
 * Claude via the official SDK (beta namespace, for server-side refusal fallbacks).
 *
 * Cost/latency choices:
 * - system prompt and tool definitions are stable and marked for prompt caching;
 *   anything per-request (focus crane, time) goes into messages, never the system prompt
 * - tools are sorted by name so the cached prefix is byte-identical between requests
 * - effort is set per feature (low for chat/tool routing, medium for reports)
 * - SDK retries 408/409/429/5xx with backoff; we only classify what's left
 */
class AnthropicLlmClient implements LlmClient
{
    private Client $client;

    public function __construct(
        string $apiKey,
        private readonly string $model,
        private readonly string $defaultEffort,
        float $timeout,
        int $maxRetries,
    ) {
        $this->client = new Client(apiKey: $apiKey, requestOptions: ['timeout' => $timeout, 'maxRetries' => $maxRetries]);
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $tools = $request->tools;
        usort($tools, fn (Tool $a, Tool $b) => strcmp($a->name(), $b->name()));
        $toolDefs = array_map(fn (Tool $t) => $t->definition(), $tools);
        if ($toolDefs !== []) {
            $toolDefs[array_key_last($toolDefs)]['cache_control'] = ['type' => 'ephemeral'];
        }

        $outputConfig = ['effort' => $request->effort ?? $this->defaultEffort];
        if ($request->jsonSchema !== null) {
            $outputConfig['format'] = ['type' => 'json_schema', 'schema' => $request->jsonSchema];
        }

        try {
            /** @var BetaMessage $message */
            $message = $this->client->beta->messages->create(
                maxTokens: $request->maxTokens,
                messages: $request->messages,
                model: $this->model,
                system: [['type' => 'text', 'text' => $request->system, 'cacheControl' => ['type' => 'ephemeral']]],
                tools: $toolDefs === [] ? null : $toolDefs,
                outputConfig: $outputConfig,
                // On a safety decline the API re-runs the request on a fallback model in the same call.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (RateLimitException $e) {
            throw new LlmException('The AI service is rate limited, try again shortly.', retryable: true, previous: $e);
        } catch (APIConnectionException $e) {
            throw new LlmException('The AI service is unreachable.', retryable: true, previous: $e);
        } catch (APIStatusException $e) {
            throw new LlmException("AI request failed ({$e->getMessage()})", retryable: ($e->status ?? 0) >= 500, previous: $e);
        }

        $content = [];
        foreach ($message->content as $block) {
            /** @var array<string, mixed> $wire */
            $wire = json_decode(json_encode($block, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
            $content[] = $wire;
        }

        $usage = $message->usage;

        return new LlmResponse(
            content: $content,
            stopReason: $message->stopReason ?? 'end_turn',
            model: $message->model,
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cacheReadTokens: $usage->cacheReadInputTokens ?? 0,
            cacheWriteTokens: $usage->cacheCreationInputTokens ?? 0,
        );
    }

    public function model(): string
    {
        return $this->model;
    }

    public function isMock(): bool
    {
        return false;
    }
}
