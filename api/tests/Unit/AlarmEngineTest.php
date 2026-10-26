<?php

namespace Tests\Unit;

use App\Domain\AlarmKind;
use App\Domain\Severity;
use App\Telemetry\AlarmEngine;
use App\Telemetry\OpenAlarm;
use App\Telemetry\TelemetryPoint;
use PHPUnit\Framework\TestCase;

class AlarmEngineTest extends TestCase
{
    private function point(array $over = []): TelemetryPoint
    {
        return TelemetryPoint::fromArray($over + [
            'craneId' => 'c-1', 'ts' => 1000, 'loadT' => 2, 'radiusM' => 20, 'loadMomentPct' => 40,
            'windMs' => 5, 'slewDeg' => 0, 'hookHeightM' => 30, 'online' => true,
        ]);
    }

    public function test_quiet_under_normal_conditions(): void
    {
        $this->assertSame([], AlarmEngine::evaluate($this->point()));
    }

    public function test_grades_wind_and_overload(): void
    {
        $grade = fn (array $over) => array_map(fn ($c) => [$c->kind, $c->severity], AlarmEngine::evaluate($this->point($over)));

        $this->assertSame([[AlarmKind::Wind, Severity::Warning]], $grade(['windMs' => 15]));
        $this->assertSame([[AlarmKind::Wind, Severity::Critical]], $grade(['windMs' => 21]));
        $this->assertSame([[AlarmKind::Overload, Severity::Warning]], $grade(['loadMomentPct' => 95]));
        $this->assertSame([[AlarmKind::Overload, Severity::Critical]], $grade(['loadMomentPct' => 104]));
    }

    public function test_only_offline_when_link_is_down(): void
    {
        $conditions = AlarmEngine::evaluate($this->point(['online' => false, 'windMs' => 30]));

        $this->assertCount(1, $conditions);
        $this->assertSame(AlarmKind::Offline, $conditions[0]->kind);
    }

    public function test_raises_once_escalates_then_clears(): void
    {
        $open = [];
        $step = function (array $over) use (&$open) {
            $r = AlarmEngine::reconcile($open, AlarmEngine::evaluate($this->point($over)));
            $clearIds = array_map(fn ($a) => $a->id, $r['clear']);
            $open = array_values(array_filter($open, fn ($a) => ! in_array($a->id, $clearIds, true)));
            foreach ($r['raise'] as $i => $c) {
                $open[] = new OpenAlarm(uniqid((string) $i), $c->kind, $c->severity);
            }

            return [count($r['clear']), count($r['raise'])];
        };

        $this->assertSame([0, 1], $step(['windMs' => 15]));
        $this->assertSame([0, 0], $step(['windMs' => 16]), 'same severity: no duplicate');
        $this->assertSame([1, 1], $step(['windMs' => 22]), 'escalation clears warning, raises critical');
        $this->assertSame([0, 0], $step(['windMs' => 15]), 'de-escalation keeps the critical alarm open');
        $this->assertSame([1, 0], $step([]));
        $this->assertSame([], $open);
    }

    public function test_status_priority(): void
    {
        $this->assertSame('offline', AlarmEngine::status(null, false));
        $this->assertSame('offline', AlarmEngine::status($this->point(['online' => false]), true));
        $this->assertSame('alarm', AlarmEngine::status($this->point(), true));
        $this->assertSame('operating', AlarmEngine::status($this->point(), false));
        $this->assertSame('idle', AlarmEngine::status($this->point(['loadT' => 0]), false));
    }
}
