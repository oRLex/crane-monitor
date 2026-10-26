<?php

namespace App\Console\Commands;

use App\Telemetry\Bus\TelemetryBus;
use App\Telemetry\TelemetryProcessor;
use Illuminate\Console\Command;

class ConsumeTelemetry extends Command
{
    protected $signature = 'telemetry:consume';

    protected $description = 'Consume telemetry from RabbitMQ: alarm rules, live state, aggregates, broadcasts';

    public function handle(TelemetryBus $bus, TelemetryProcessor $processor): int
    {
        $this->info('Consuming telemetry…');
        $bus->consume(fn (array $points) => $processor->process($points));
        $processor->flush();

        return self::SUCCESS;
    }
}
