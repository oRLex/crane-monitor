<?php

namespace App\Telemetry;

use App\Domain\AlarmKind;
use App\Domain\Severity;

/**
 * Pure alarm rules. Persistence and broadcasting live in TelemetryProcessor so these
 * stay unit-testable without a database.
 */
final class AlarmEngine
{
    public const WIND_WARNING = 14.0;   // m/s

    public const WIND_CRITICAL = 20.0;  // most tower cranes must stop at ~20 m/s

    public const MOMENT_WARNING = 90.0; // % of rated load moment

    public const MOMENT_CRITICAL = 100.0;

    /** @return list<Condition> */
    public static function evaluate(TelemetryPoint $p): array
    {
        if (! $p->online) {
            return [new Condition(AlarmKind::Offline, Severity::Critical, 'Telemetry link lost')];
        }

        $out = [];
        if ($p->windMs >= self::WIND_WARNING) {
            $severity = $p->windMs >= self::WIND_CRITICAL ? Severity::Critical : Severity::Warning;
            $out[] = new Condition(AlarmKind::Wind, $severity, sprintf('Wind %s m/s (limit %s m/s)', $p->windMs, self::WIND_CRITICAL));
        }
        if ($p->loadMomentPct >= self::MOMENT_WARNING) {
            $severity = $p->loadMomentPct >= self::MOMENT_CRITICAL ? Severity::Critical : Severity::Warning;
            $out[] = new Condition(AlarmKind::Overload, $severity, sprintf('Load moment %s%% (%s t at %s m)', $p->loadMomentPct, $p->loadT, $p->radiusM));
        }

        return $out;
    }

    /**
     * Reconciles a crane's open alarms against the latest conditions.
     *  - new condition  -> raise
     *  - worse severity -> clear the old alarm and raise an escalated one
     *  - condition gone -> clear
     *
     * @param  list<OpenAlarm>  $open  open alarms of this crane
     * @param  list<Condition>  $conditions
     * @return array{clear: list<OpenAlarm>, raise: list<Condition>}
     */
    public static function reconcile(array $open, array $conditions): array
    {
        $clear = [];
        $raise = [];
        foreach ($open as $alarm) {
            $cond = self::find($conditions, $alarm->kind);
            if ($cond === null || $cond->severity->rank() > $alarm->severity->rank()) {
                $clear[] = $alarm;
            }
        }
        foreach ($conditions as $cond) {
            $existing = null;
            foreach ($open as $alarm) {
                if ($alarm->kind === $cond->kind) {
                    $existing = $alarm;
                    break;
                }
            }
            if ($existing === null || $cond->severity->rank() > $existing->severity->rank()) {
                $raise[] = $cond;
            }
        }

        return ['clear' => $clear, 'raise' => $raise];
    }

    /** @param list<Condition> $conditions */
    private static function find(array $conditions, AlarmKind $kind): ?Condition
    {
        foreach ($conditions as $c) {
            if ($c->kind === $kind) {
                return $c;
            }
        }

        return null;
    }

    /** offline > alarm > operating > idle */
    public static function status(?TelemetryPoint $p, bool $hasOpenAlarm): string
    {
        if ($p === null || ! $p->online) {
            return 'offline';
        }
        if ($hasOpenAlarm) {
            return 'alarm';
        }

        return $p->loadT > 0.2 ? 'operating' : 'idle';
    }
}
