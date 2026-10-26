<?php

namespace App\Telemetry\Bus;

use App\Telemetry\TelemetryProcessor;
use Closure;
use LogicException;

/** No broker: publishing processes in-process. For local dev without RabbitMQ and for tests. */
class SyncBus implements TelemetryBus
{
    public function __construct(private readonly Closure $processor) {}

    public function publish(array $points): void
    {
        /** @var TelemetryProcessor $processor */
        $processor = ($this->processor)();
        $processor->process($points);
    }

    public function consume(Closure $handler): void
    {
        throw new LogicException('The sync telemetry bus has no consumer; telemetry:simulate processes in-process.');
    }
}
