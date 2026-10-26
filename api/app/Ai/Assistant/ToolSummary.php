<?php

namespace App\Ai\Assistant;

use App\Ai\Tools\ToolResult;

/** One-line, UI-facing description of a tool result (the model gets the full JSON). */
class ToolSummary
{
    public static function of(string $tool, ToolResult $result): string
    {
        if (! $result->ok) {
            return (string) $result->error;
        }
        $d = $result->data;

        return match ($tool) {
            'list_cranes' => count($d['cranes'] ?? []).' cranes',
            'get_crane_status' => ($d['name'] ?? '').' · '.($d['status'] ?? ''),
            'get_hourly_stats' => count($d['hours'] ?? []).' hours of stats',
            'list_alarms' => count($d['alarms'] ?? []).' alarms',
            'search_docs' => implode(' ', array_map(fn ($p) => "[{$p['citation']}] {$p['document']}", $d['passages'] ?? [])),
            'acknowledge_alarm' => 'acknowledged',
            default => 'done',
        };
    }
}
