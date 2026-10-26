<?php

namespace App\Console\Commands;

use App\Models\Crane;
use App\Telemetry\Bus\TelemetryBus;
use App\Telemetry\CraneSpec;
use App\Telemetry\Simulator;
use Illuminate\Console\Command;

/** Stand-in for the crane controllers + edge gateway: publishes a tick for every crane. */
class SimulateTelemetry extends Command
{
    protected $signature = 'telemetry:simulate {--seed=} {--ticks=0 : Stop after N ticks (0 = run forever)}';

    protected $description = 'Publish simulated crane telemetry to the telemetry bus';

    public function handle(TelemetryBus $bus): int
    {
        $cranes = Crane::query()->orderBy('id')->get()->map(CraneSpec::fromModel(...))->values()->all();
        $seed = $this->option('seed');
        $sim = new Simulator($cranes, $seed === null ? null : (int) $seed);
        $tickMs = (int) config('telemetry.tick_ms');
        $limit = (int) $this->option('ticks');

        $this->info(sprintf('Simulating %d cranes every %d ms via the %s bus', count($cranes), $tickMs, config('telemetry.bus')));
        for ($i = 0; $limit === 0 || $i < $limit; $i++) {
            $started = microtime(true);
            $bus->publish($sim->tick());
            $sleep = $tickMs * 1000 - (int) ((microtime(true) - $started) * 1_000_000);
            if ($sleep > 0) {
                usleep($sleep);
            }
        }

        return self::SUCCESS;
    }
}
