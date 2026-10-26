<?php

namespace App\Http\Controllers;

use App\Ai\Llm\LlmClient;
use App\Ai\TokenBudget;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Cost and reliability view for admins: spend by day/feature/tenant, cache hit rate, latency. */
class AiUsageController
{
    public function __invoke(Request $request, LlmClient $llm, TokenBudget $budget): JsonResponse
    {
        $since = now()->subDays(min(30, max(1, (int) $request->query('days', '7'))))->startOfDay();
        $base = DB::table('ai_usage')->where('created_at', '>=', $since);

        $byDay = (clone $base)->selectRaw("to_char(created_at, 'YYYY-MM-DD') as day, feature, count(*) as calls, sum(input_tokens) as input, sum(output_tokens) as output, sum(cache_read_tokens) as cache_read, sum(cost_usd) as cost")
            ->groupBy('day', 'feature')->orderBy('day')->get();
        $byTenant = (clone $base)->selectRaw("coalesce(tenant_id, 'platform') as tenant, count(*) as calls, sum(input_tokens + output_tokens + cache_read_tokens + cache_write_tokens) as tokens, sum(cost_usd) as cost")
            ->groupBy('tenant')->get();
        $totals = (clone $base)->selectRaw('count(*) as calls, sum(cost_usd) as cost, sum(input_tokens) as input, sum(cache_read_tokens) as cache_read, sum(cache_write_tokens) as cache_write, percentile_cont(0.5) within group (order by latency_ms) as p50_ms, percentile_cont(0.95) within group (order by latency_ms) as p95_ms')->first() ?? (object) [];
        $stops = (clone $base)->selectRaw('stop_reason, count(*) as n')->groupBy('stop_reason')->pluck('n', 'stop_reason');

        $t = (array) $totals;
        $promptTokens = (int) ($t['input'] ?? 0) + (int) ($t['cache_read'] ?? 0) + (int) ($t['cache_write'] ?? 0);
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'model' => $llm->model(),
            'mock' => $llm->isMock(),
            'since' => $since->toIso8601String(),
            'totals' => [
                'calls' => (int) ($t['calls'] ?? 0),
                'cost_usd' => round((float) ($t['cost'] ?? 0), 4),
                'cache_hit_rate' => $promptTokens > 0 ? round((int) $t['cache_read'] / $promptTokens, 3) : null,
                'p50_ms' => isset($t['p50_ms']) ? (int) $t['p50_ms'] : null,
                'p95_ms' => isset($t['p95_ms']) ? (int) $t['p95_ms'] : null,
            ],
            'stop_reasons' => $stops,
            'by_day' => $byDay,
            'by_tenant' => $byTenant,
            'budget' => ['daily_limit' => $budget->limit(), 'used_today' => $budget->used($user)],
        ]);
    }
}
