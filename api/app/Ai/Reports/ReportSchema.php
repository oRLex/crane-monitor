<?php

namespace App\Ai\Reports;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * JSON schema sent to the model (structured outputs) and the server-side validation of what
 * comes back. Structured outputs guarantee the shape; validation guards the semantics we
 * care about (enums, sizes, times that exist in the facts).
 */
final class ReportSchema
{
    /** @return array<string, mixed> */
    public static function json(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary', 'risk_level', 'highlights', 'safety_events', 'recommendations'],
            'properties' => [
                'summary' => $string,
                'risk_level' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'highlights' => ['type' => 'array', 'items' => $string],
                'safety_events' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['time', 'kind', 'severity', 'description'],
                    'properties' => [
                        'time' => $string,
                        'kind' => ['type' => 'string', 'enum' => ['wind', 'overload', 'offline']],
                        'severity' => ['type' => 'string', 'enum' => ['warning', 'critical']],
                        'description' => $string,
                    ],
                ]],
                'recommendations' => ['type' => 'array', 'items' => $string],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(mixed $report, array $facts): array
    {
        $knownTimes = array_column($facts['notable_alarms'] ?? [], 'raised_at');
        $validator = Validator::make(is_array($report) ? $report : [], [
            'summary' => ['required', 'string', 'min:20', 'max:1200'],
            'risk_level' => ['required', 'in:low,medium,high'],
            'highlights' => ['present', 'array', 'max:6'],
            'highlights.*' => ['string', 'max:300'],
            'safety_events' => ['present', 'array', 'max:5'],
            // Every event the model reports must be one of the facts — no invented incidents.
            'safety_events.*.time' => ['required', 'string', Rule::in($knownTimes)],
            'safety_events.*.kind' => ['required', 'in:wind,overload,offline'],
            'safety_events.*.severity' => ['required', 'in:warning,critical'],
            'safety_events.*.description' => ['required', 'string', 'max:400'],
            'recommendations' => ['present', 'array', 'max:4'],
            'recommendations.*' => ['string', 'max:400'],
        ]);

        return $validator->validate();
    }
}
