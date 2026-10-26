<?php

namespace Database\Seeders;

use App\Models\Crane;
use App\Models\Site;
use App\Models\TelemetryHourly;
use App\Models\Tenant;
use App\Models\User;
use App\Telemetry\CraneSpec;
use App\Telemetry\HourlyAggregator;
use App\Telemetry\Simulator;
use App\Telemetry\TelemetryProcessor;
use App\Telemetry\TelemetryStore;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Public HLS test streams stand in for on-site camera feeds.
    private const STREAM_A = 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8';

    private const STREAM_B = 'https://demo.unified-streaming.com/k8s/features/stable/video/tears-of-steel/tears-of-steel.ism/.m3u8';

    public function run(): void
    {
        foreach ([['t-budinvest', 'BudInvest Group'], ['t-skyline', 'Skyline Construction']] as [$id, $name]) {
            Tenant::query()->updateOrCreate(['id' => $id], ['name' => $name]);
        }

        foreach ([
            ['s-podil', 'Podil Residence', 'Kyiv', 't-budinvest'],
            ['s-obolon', 'Obolon Business Park', 'Kyiv', 't-budinvest'],
            ['s-warsaw', 'Wola Tower', 'Warsaw', 't-skyline'],
        ] as [$id, $name, $city, $tenant]) {
            Site::query()->updateOrCreate(['id' => $id], ['name' => $name, 'city' => $city, 'tenant_id' => $tenant]);
        }

        foreach ([
            ['c-101', 'TC-101', 'Flat-top 172', 'FT172-0042', 8, 60, 172, self::STREAM_A, 's-podil', 't-budinvest'],
            ['c-102', 'TC-102', 'Hammerhead 280', 'HH280-0107', 12, 65, 280, self::STREAM_B, 's-podil', 't-budinvest'],
            ['c-103', 'TC-103', 'Luffing 357', 'LJ357-0011', 20, 55, 357, null, 's-obolon', 't-budinvest'],
            ['c-201', 'WT-201', 'Flat-top 172', 'FT172-0088', 8, 60, 172, self::STREAM_A, 's-warsaw', 't-skyline'],
            ['c-202', 'WT-202', 'Hammerhead 280', 'HH280-0150', 12, 65, 280, self::STREAM_B, 's-warsaw', 't-skyline'],
        ] as [$id, $name, $model, $serial, $maxLoad, $jib, $moment, $stream, $site, $tenant]) {
            Crane::query()->updateOrCreate(['id' => $id], [
                'name' => $name, 'model' => $model, 'serial_number' => $serial, 'max_load_t' => $maxLoad,
                'jib_length_m' => $jib, 'rated_moment_tm' => $moment, 'stream_url' => $stream,
                'site_id' => $site, 'tenant_id' => $tenant,
            ]);
        }

        foreach ([
            ['admin@demo.io', 'Olena Admin', 'admin', null],
            ['operator@demo.io', 'Taras Operator', 'operator', null],
            ['manager@budinvest.demo', 'Iryna (BudInvest)', 'customer_manager', 't-budinvest'],
            ['viewer@skyline.demo', 'Piotr (Skyline)', 'customer_viewer', 't-skyline'],
        ] as [$email, $name, $role, $tenant]) {
            User::query()->updateOrCreate(['email' => $email], ['name' => $name, 'role' => $role, 'tenant_id' => $tenant]);
        }

        if (! app()->runningUnitTests()) {
            $this->replayHistory();
        }
    }

    /**
     * Replays the last 12 hours through the real processor (10 s ticks, no broadcasts) so
     * reports, the assistant and the alarm list have history right after deploy.
     */
    private function replayHistory(int $hours = 12, int $stepMs = 10_000): void
    {
        if (TelemetryHourly::query()->exists()) {
            return; // idempotent: deploys re-run the seeder
        }
        $cranes = Crane::query()->orderBy('id')->get()->map(CraneSpec::fromModel(...))->values()->all();
        $sim = new Simulator($cranes, seed: 2026, dropoutChance: 0.0008);
        $processor = new TelemetryProcessor(app(TelemetryStore::class), new HourlyAggregator($stepMs), broadcast: false);

        $start = now()->subHours($hours)->getTimestampMs();
        $end = now()->getTimestampMs();
        for ($ts = $start; $ts <= $end; $ts += $stepMs) {
            $processor->process($sim->tick($ts));
        }
        $processor->flush();
    }
}
