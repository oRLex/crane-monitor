<?php

namespace Tests\Feature;

use App\Events\AlarmChanged;
use App\Events\TelemetryBatch;
use App\Models\Alarm;
use App\Models\Crane;
use App\Telemetry\Bus\TelemetryBus;
use App\Telemetry\TelemetryPoint;
use App\Telemetry\TelemetryProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TelemetryPipelineTest extends TestCase
{
    private function point(string $crane, int $ts, array $over = []): TelemetryPoint
    {
        return TelemetryPoint::fromArray($over + [
            'craneId' => $crane, 'ts' => $ts, 'loadT' => 2, 'radiusM' => 20, 'loadMomentPct' => 40,
            'windMs' => 5, 'slewDeg' => 0, 'hookHeightM' => 30, 'online' => true,
        ]);
    }

    public function test_bus_raises_persists_and_broadcasts_per_tenant(): void
    {
        Event::fake([TelemetryBatch::class, AlarmChanged::class]);
        $bus = app(TelemetryBus::class);
        $t = now()->getTimestampMs();

        $bus->publish([$this->point('c-101', $t, ['windMs' => 21]), $this->point('c-201', $t)]);
        $bus->publish([$this->point('c-101', $t + 1000, ['windMs' => 21]), $this->point('c-201', $t + 1000)]);
        $bus->publish([$this->point('c-101', $t + 2000), $this->point('c-201', $t + 2000)]);

        $alarms = Alarm::query()->get();
        $this->assertCount(1, $alarms, 'no duplicate while the condition persists');
        $this->assertSame('t-budinvest', $alarms[0]->tenant_id);
        $this->assertNotNull($alarms[0]->cleared_at);

        Event::assertDispatched(AlarmChanged::class, 2); // raised + cleared
        Event::assertDispatched(TelemetryBatch::class, fn (TelemetryBatch $e) => $e->tenantId === 't-skyline'
            && array_column($e->points, 'craneId') === ['c-201']);
    }

    public function test_snapshot_only_contains_visible_cranes(): void
    {
        Event::fake();
        app(TelemetryBus::class)->publish([$this->point('c-101', 1), $this->point('c-201', 1)]);

        $snapshot = $this->getAs(self::VIEWER, '/api/realtime/snapshot')->assertOk()->json();
        $this->assertSame(['c-201'], array_column($snapshot['points'], 'craneId'));
        $this->assertSame(['c-201', 'c-202'], array_keys($snapshot['history']));
    }

    public function test_a_bounded_simulator_run_flushes_its_hourly_aggregates(): void
    {
        // On Wasmer, telemetry:simulate runs as a short cron job; nothing may be left unflushed.
        Event::fake();
        config(['telemetry.tick_ms' => 1]);

        $this->artisan('telemetry:simulate', ['--ticks' => 3, '--seed' => 1])->assertSuccessful();

        $this->assertSame(3 * Crane::query()->count(), (int) DB::table('telemetry_hourly')->sum('samples'));
    }

    public function test_hourly_aggregates_are_additive(): void
    {
        Event::fake();
        $processor = app(TelemetryProcessor::class);
        $hour = now()->startOfHour()->getTimestampMs();
        $processor->process([$this->point('c-101', $hour, ['windMs' => 10, 'loadT' => 3])]);
        $processor->flush();
        $processor->process([$this->point('c-101', $hour + 1000, ['windMs' => 12, 'online' => false])]);
        $processor->flush();

        $row = DB::table('telemetry_hourly')->where('crane_id', 'c-101')->first();
        $this->assertSame(2, $row->samples);
        $this->assertEquals(12, $row->max_wind_ms);
        $this->assertSame(1, $row->operating_s);
        $this->assertSame(1, $row->offline_s);
        $this->assertSame(1, $row->lifts);
    }
}
