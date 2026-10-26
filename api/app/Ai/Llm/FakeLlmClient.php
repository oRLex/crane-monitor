<?php

namespace App\Ai\Llm;

use App\Ai\Tools\Tool;
use Closure;
use Illuminate\Support\Str;

/**
 * Deterministic stand-in for Claude, used when no API key is configured and in tests.
 *
 * It is not trying to be smart: it routes on keywords to the same tools the real model
 * would use and assembles the answer from their results, so the full pipeline (tool calls,
 * authorization, persistence, streaming, usage ledger, structured output validation) runs
 * for real. Tests can script exact responses with FakeLlmClient::script().
 */
class FakeLlmClient implements LlmClient
{
    /** @var list<LlmResponse|Closure(LlmRequest): LlmResponse> */
    private static array $script = [];

    /** @var list<LlmRequest> */
    public static array $requests = [];

    /** @param LlmResponse|Closure(LlmRequest): LlmResponse ...$responses */
    public static function script(LlmResponse|Closure ...$responses): void
    {
        self::$script = array_values([...self::$script, ...$responses]);
    }

    public static function reset(): void
    {
        self::$script = [];
        self::$requests = [];
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        self::$requests[] = $request;
        if (self::$script !== []) {
            $next = array_shift(self::$script);

            return $next instanceof Closure ? $next($request) : $next;
        }

        return $request->jsonSchema !== null ? $this->structured($request) : $this->chat($request);
    }

    public function model(): string
    {
        return 'mock';
    }

    public function isMock(): bool
    {
        return true;
    }

    public static function text(string $text): LlmResponse
    {
        return new LlmResponse([['type' => 'text', 'text' => $text]], 'end_turn', 'mock', mock: true);
    }

    /** @param array<string, mixed> $input */
    public static function toolCall(string $name, array $input, string $preface = ''): LlmResponse
    {
        $content = $preface === '' ? [] : [['type' => 'text', 'text' => $preface]];
        $content[] = ['type' => 'tool_use', 'id' => 'toolu_'.Str::random(16), 'name' => $name, 'input' => (object) $input];

        return new LlmResponse($content, 'tool_use', 'mock', mock: true);
    }

    private function chat(LlmRequest $request): LlmResponse
    {
        $last = $request->messages[array_key_last($request->messages) ?? 0] ?? ['role' => 'user', 'content' => ''];
        $results = is_array($last['content']) ? array_values(array_filter($last['content'], fn ($b) => ($b['type'] ?? null) === 'tool_result')) : [];

        return $results !== [] ? $this->answer($request, $results) : $this->route($request);
    }

    /** Pick tools from keywords in the latest user message (+ the conversation's focus). */
    private function route(LlmRequest $request): LlmResponse
    {
        $available = array_map(fn (Tool $t) => $t->name(), $request->tools);
        $text = $this->lastUserText($request);
        $all = strtolower(implode("\n", array_map(fn ($m) => is_string($m['content']) ? $m['content'] : implode(' ', array_map(fn ($b) => $b['text'] ?? '', $m['content'])), $request->messages)));
        $q = strtolower($text);
        $crane = $this->craneId($q) ?? $this->craneId($all);

        $calls = [];
        if (preg_match('/\b(ack|acknowledge)\b/', $q) && preg_match('/\b([0-9a-hjkmnp-tv-z]{26})\b/', $q, $m)) {
            $calls[] = ['acknowledge_alarm', ['alarm_id' => strtoupper($m[1])]];
        } else {
            if ($crane !== null) {
                $calls[] = ['get_crane_status', ['crane_id' => $crane]];
            }
            if (preg_match('/alarm|explain|why|happen|incident|overload|wind|offline/', $q)) {
                $calls[] = ['list_alarms', array_filter(['crane_id' => $crane, 'hours' => 12])];
            }
            if (preg_match('/manual|procedure|limit|should|how|what to do|explain|why|rule|chart|park|restart|overload|wind|offline/', $q)) {
                $calls[] = ['search_docs', ['query' => $this->docsQuery($q), 'limit' => 3]];
            }
            if ($calls === [] || preg_match('/fleet|all cranes|overview|which crane/', $q)) {
                $calls[] = ['list_cranes', []];
            }
        }

        $content = [['type' => 'text', 'text' => 'Let me check the live data.']];
        foreach ($calls as [$name, $input]) {
            if (in_array($name, $available, true)) {
                $content[] = ['type' => 'tool_use', 'id' => 'toolu_'.Str::random(16), 'name' => $name, 'input' => (object) $input];
            }
        }
        if (count($content) === 1) {
            return $this->usage(self::text("_Mock mode:_ I don't have a tool that can answer that with your permissions."), $request);
        }

        return $this->usage(new LlmResponse($content, 'tool_use', 'mock', mock: true), $request);
    }

    /** @param list<array<string, mixed>> $results */
    private function answer(LlmRequest $request, array $results): LlmResponse
    {
        $uses = [];
        foreach ($request->messages as $m) {
            if ($m['role'] === 'assistant' && is_array($m['content'])) {
                foreach ($m['content'] as $b) {
                    if (($b['type'] ?? null) === 'tool_use') {
                        $uses[$b['id']] = $b['name'];
                    }
                }
            }
        }

        $lines = ['_Mock mode — no LLM configured; this answer is assembled from the tool results._', ''];
        foreach ($results as $r) {
            $name = $uses[$r['tool_use_id']] ?? 'tool';
            $data = json_decode(is_string($r['content']) ? $r['content'] : '{}', true) ?: [];
            if (isset($data['error'])) {
                $lines[] = "- `{$name}` failed: {$data['error']}";

                continue;
            }
            $lines = [...$lines, ...$this->describe($name, $data)];
        }

        return $this->usage(self::text(implode("\n", $lines)), $request);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return list<string>
     */
    private function describe(string $tool, array $d): array
    {
        return array_values(match ($tool) {
            'list_cranes' => ['**Fleet**', ...array_map(fn ($c) => "- {$c['name']} ({$c['site']}): {$c['status']}, {$c['open_alarms']} open alarm(s)", $d['cranes'] ?? []), ''],
            'get_crane_status' => [
                "**{$d['name']}** ({$d['model']}) is **{$d['status']}**."
                    .(isset($d['latest']) ? " Latest: {$d['latest']['load_t']} t at {$d['latest']['radius_m']} m ({$d['latest']['load_moment_pct']}% of rated moment), wind {$d['latest']['wind_ms']} m/s." : ''),
                '',
            ],
            'list_alarms' => [
                '**Alarms (last 12 h):** '.(implode(', ', array_map(fn ($c) => "{$c['count']}× {$c['kind']} {$c['severity']}", $d['counts'] ?? [])) ?: 'none'),
                ...array_map(fn ($a) => "- {$a['raised_at']}: {$a['message']} ({$a['severity']})".($a['cleared_at'] ? ', cleared' : ', **open**'), array_slice($d['alarms'] ?? [], 0, 3)),
                '',
            ],
            'search_docs' => ['**From the documentation:**', ...array_map(fn ($p) => '> '.Str::limit(preg_replace('/\s+/', ' ', $p['text']) ?? '', 220)." [{$p['citation']}]", $d['passages'] ?? []), ''],
            'acknowledge_alarm' => ["Alarm {$d['alarm_id']} acknowledged by {$d['acknowledged_by']}."],
            default => ['`'.$tool.'`: '.Str::limit(json_encode($d) ?: '', 300)],
        });
    }

    /** Builds a schema-valid shift report from the <facts> JSON in the prompt. */
    private function structured(LlmRequest $request): LlmResponse
    {
        preg_match('/<facts>(.*)<\/facts>/s', $this->lastUserText($request), $m);
        $f = json_decode($m[1] ?? '{}', true) ?: [];
        $critical = (int) ($f['alarms']['critical'] ?? 0);
        $overloads = (int) ($f['alarms']['by_kind']['overload'] ?? 0);
        $risk = $critical >= 5 ? 'high' : ($critical > 0 || $overloads > 0 ? 'medium' : 'low');

        $report = [
            'summary' => sprintf(
                '%s worked %d min over %d h with %d lifts; peak load moment %s%%, peak wind %s m/s, %d alarm(s) (%d critical).',
                $f['crane']['name'] ?? 'The crane', $f['totals']['operating_min'] ?? 0, $f['period']['hours'] ?? 0,
                $f['totals']['lifts'] ?? 0, $f['totals']['peak_load_moment_pct'] ?? 0, $f['totals']['peak_wind_ms'] ?? 0,
                $f['alarms']['total'] ?? 0, $critical,
            ),
            'risk_level' => $risk,
            'highlights' => array_values(array_filter([
                ($f['totals']['offline_min'] ?? 0) > 0 ? "Telemetry offline for {$f['totals']['offline_min']} min." : null,
                $overloads > 0 ? "{$overloads} overload alarm(s)." : null,
                ($f['alarms']['by_kind']['wind'] ?? 0) > 0 ? "{$f['alarms']['by_kind']['wind']} wind alarm(s)." : null,
            ])) ?: ['No notable events.'],
            'safety_events' => array_map(fn ($a) => [
                'time' => $a['raised_at'], 'kind' => $a['kind'], 'severity' => $a['severity'], 'description' => $a['message'],
            ], array_slice($f['notable_alarms'] ?? [], 0, 5)),
            'recommendations' => $risk === 'low' ? ['Continue normal operation.'] : (array_values(array_filter([
                $overloads > 0 ? 'Review lift planning for loads near the chart limit [load moment procedure].' : null,
                ($f['alarms']['by_kind']['wind'] ?? 0) > 0 ? 'Plan sail-area lifts for low-wind hours.' : null,
                ($f['totals']['offline_min'] ?? 0) > 30 ? 'Check gateway power and LTE signal.' : null,
            ])) ?: ['Review open alarms with the site manager.']),
        ];

        return $this->usage(new LlmResponse([['type' => 'text', 'text' => json_encode($report, JSON_THROW_ON_ERROR)]], 'end_turn', 'mock', mock: true), $request);
    }

    private function usage(LlmResponse $r, LlmRequest $request): LlmResponse
    {
        $in = (int) ceil((strlen($request->system) + strlen(json_encode($request->messages) ?: '')) / 4);
        $out = (int) ceil(strlen(json_encode($r->content) ?: '') / 4);

        return new LlmResponse($r->content, $r->stopReason, 'mock', $in, $out, mock: true);
    }

    private function lastUserText(LlmRequest $request): string
    {
        for ($i = count($request->messages) - 1; $i >= 0; $i--) {
            $m = $request->messages[$i];
            if ($m['role'] !== 'user') {
                continue;
            }
            if (is_string($m['content'])) {
                return $m['content'];
            }
            $text = implode("\n", array_map(fn ($b) => $b['text'] ?? '', array_filter($m['content'], fn ($b) => ($b['type'] ?? null) === 'text')));
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function craneId(string $text): ?string
    {
        if (preg_match('/\bc-(\d{3})\b/', $text, $m) || preg_match('/\b(?:tc|wt)-(\d{3})\b/', $text, $m)) {
            return 'c-'.$m[1];
        }

        return null;
    }

    private function docsQuery(string $q): string
    {
        foreach (['overload' => 'overload load moment procedure', 'wind' => 'wind limit out of service', 'offline' => 'telemetry link loss offline'] as $k => $query) {
            if (str_contains($q, $k)) {
                return $query;
            }
        }

        return Str::limit($q, 200, '');
    }
}
