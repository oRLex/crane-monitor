<?php

namespace App\Telemetry;

/**
 * Generates plausible crane telemetry: lift cycles (hoist -> travel -> lower -> idle),
 * per-site gusty wind, occasional near-overload lifts and connectivity drop-outs.
 * Stands in for the crane controllers + edge gateway (MQTT) of a real deployment.
 */
final class Simulator
{
    private const NEXT_PHASE = ['idle' => 'hoist', 'hoist' => 'travel', 'travel' => 'lower', 'lower' => 'idle'];

    private Random $rand;

    /** @var array<string, array{phase: string, phaseTicks: int, loadT: float, targetLoadT: float, radiusM: float, targetRadiusM: float, slewDeg: float, targetSlewDeg: float, hookHeightM: float, offlineTicks: int}> */
    private array $states = [];

    /** @var array<string, float> */
    private array $wind = [];

    /**
     * @param  array<int, CraneSpec>  $cranes
     * @param  float  $dropoutChance  Per-tick probability that a crane drops its link for a while.
     */
    public function __construct(
        private readonly array $cranes,
        ?int $seed = null,
        private readonly float $dropoutChance = 0.002,
    ) {
        $this->rand = new Random($seed ?? (int) (microtime(true) * 1000));
        foreach ($cranes as $crane) {
            $this->states[$crane->id] = [
                'phase' => 'idle',
                'phaseTicks' => (int) floor($this->rand->next() * 5),
                'loadT' => 0.0,
                'targetLoadT' => 0.0,
                'radiusM' => $crane->jibLengthM * 0.4,
                'targetRadiusM' => $crane->jibLengthM * 0.4,
                'slewDeg' => $this->rand->next() * 360,
                'targetSlewDeg' => $this->rand->next() * 360,
                'hookHeightM' => 40.0,
                'offlineTicks' => 0,
            ];
            if (! isset($this->wind[$crane->siteId])) {
                $this->wind[$crane->siteId] = 4 + $this->rand->next() * 6;
            }
        }
    }

    /** @return list<TelemetryPoint> */
    public function tick(?int $ts = null): array
    {
        $ts ??= (int) (microtime(true) * 1000);
        foreach ($this->wind as $siteId => $w) {
            // Mean-reverting random walk with rare strong gusts.
            $gust = $this->rand->next() < 0.01 ? 8 + $this->rand->next() * 8 : 0;
            $this->wind[$siteId] = Random::clamp($w + (8 - $w) * 0.05 + ($this->rand->next() - 0.5) * 1.6 + $gust, 0, 32);
        }

        return array_values(array_map(fn (CraneSpec $c) => $this->step($c, $ts), $this->cranes));
    }

    private function step(CraneSpec $crane, int $ts): TelemetryPoint
    {
        $s = &$this->states[$crane->id];
        $windMs = Random::round($this->wind[$crane->siteId] ?? 0);

        if ($s['offlineTicks'] > 0 || $this->rand->next() < $this->dropoutChance) {
            $s['offlineTicks'] = $s['offlineTicks'] > 0 ? $s['offlineTicks'] - 1 : 15 + (int) floor($this->rand->next() * 20);

            return $this->point($crane, $s, $ts, $windMs)->withOnline(false);
        }

        $s['phaseTicks']--;
        if ($s['phaseTicks'] <= 0) {
            $this->nextPhase($crane, $s, $windMs);
        }

        $ease = 0.25;
        $s['loadT'] += ($s['targetLoadT'] - $s['loadT']) * $ease;
        $s['radiusM'] += ($s['targetRadiusM'] - $s['radiusM']) * $ease * 0.6;
        $dSlew = self::jsMod($s['targetSlewDeg'] - $s['slewDeg'] + 540, 360) - 180;
        $s['slewDeg'] = self::jsMod($s['slewDeg'] + $dSlew * 0.2 + 360, 360);
        $targetHook = in_array($s['phase'], ['hoist', 'travel'], true) ? 55 : 8;
        $s['hookHeightM'] += ($targetHook - $s['hookHeightM']) * 0.3;

        return $this->point($crane, $s, $ts, $windMs);
    }

    /** @param array{phase: string, phaseTicks: int, loadT: float, targetLoadT: float, radiusM: float, targetRadiusM: float, slewDeg: float, targetSlewDeg: float, hookHeightM: float, offlineTicks: int} $s */
    private function nextPhase(CraneSpec $crane, array &$s, float $windMs): void
    {
        // High wind: cranes are parked (weathervaning) instead of starting a new lift.
        $s['phase'] = $s['phase'] === 'idle' && $windMs > 18 ? 'idle' : self::NEXT_PHASE[$s['phase']];
        $s['phaseTicks'] = 4 + (int) floor($this->rand->next() * 8);

        if ($s['phase'] === 'hoist') {
            $s['targetRadiusM'] = $crane->jibLengthM * (0.2 + $this->rand->next() * 0.75);
            $capacityAtRadius = min($crane->maxLoadT, $crane->ratedMomentTm / $s['targetRadiusM']);
            // ~6% of lifts are pushed close to or over the chart to exercise overload alarms.
            $utilisation = $this->rand->next() < 0.06 ? 0.92 + $this->rand->next() * 0.14 : 0.2 + $this->rand->next() * 0.6;
            $s['targetLoadT'] = $capacityAtRadius * $utilisation;
        } elseif ($s['phase'] === 'travel') {
            $s['targetSlewDeg'] = $this->rand->next() * 360;
            $s['targetRadiusM'] = Random::clamp($s['radiusM'] + ($this->rand->next() - 0.5) * 10, 3, $crane->jibLengthM);
        } elseif ($s['phase'] === 'idle') {
            $s['targetLoadT'] = 0.0;
        }
    }

    /** @param array{phase: string, phaseTicks: int, loadT: float, targetLoadT: float, radiusM: float, targetRadiusM: float, slewDeg: float, targetSlewDeg: float, hookHeightM: float, offlineTicks: int} $s */
    private function point(CraneSpec $crane, array $s, int $ts, float $windMs): TelemetryPoint
    {
        $loadT = Random::round(max(0, $s['loadT']), 2);
        $radiusM = Random::round($s['radiusM']);

        return new TelemetryPoint(
            craneId: $crane->id,
            ts: $ts,
            loadT: $loadT,
            radiusM: $radiusM,
            loadMomentPct: Random::round(($loadT * $radiusM) / $crane->ratedMomentTm * 100),
            windMs: $windMs,
            slewDeg: Random::round($s['slewDeg']),
            hookHeightM: Random::round($s['hookHeightM']),
            online: true,
        );
    }

    /** JS `%` on floats (sign follows the dividend), which fmod matches. */
    private static function jsMod(float $a, float $b): float
    {
        return fmod($a, $b);
    }
}
