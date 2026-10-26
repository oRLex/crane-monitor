<?php

namespace App\Ai;

use App\Ai\Llm\LlmResponse;
use App\Models\AiUsage;
use App\Models\User;

class UsageRecorder
{
    public function __construct(private readonly TokenBudget $budget) {}

    public function record(string $feature, LlmResponse $response, ?User $user, int $latencyMs): AiUsage
    {
        if ($user !== null) {
            $this->budget->consume($user, $response->totalTokens());
        }

        return AiUsage::query()->create([
            'feature' => $feature,
            'model' => $response->model,
            'tenant_id' => $user?->tenant_id,
            'user_id' => $user?->id,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'cache_read_tokens' => $response->cacheReadTokens,
            'cache_write_tokens' => $response->cacheWriteTokens,
            'cost_usd' => $response->mock ? 0 : self::cost($response->model, $response),
            'latency_ms' => $latencyMs,
            'stop_reason' => $response->stopReason,
            'mock' => $response->mock,
        ]);
    }

    public static function cost(string $model, LlmResponse $r): float
    {
        // Responses may carry a dated or fallback model id; price by the longest matching prefix.
        $prices = null;
        foreach ((array) config('ai.pricing') as $id => $p) {
            if (str_starts_with($model, (string) $id)) {
                $prices = $p;
            }
        }
        if ($prices === null) {
            return 0.0;
        }

        return ($r->inputTokens * $prices['input'] + $r->outputTokens * $prices['output']
            + $r->cacheReadTokens * $prices['cache_read'] + $r->cacheWriteTokens * $prices['cache_write']) / 1_000_000;
    }
}
