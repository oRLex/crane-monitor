<?php

namespace App\Telemetry\Bus;

use App\Telemetry\TelemetryPoint;
use Closure;

/** Transport between the crane side (simulator / edge gateway) and the processor. */
interface TelemetryBus
{
    /** @param list<TelemetryPoint> $points */
    public function publish(array $points): void;

    /** @param Closure(list<TelemetryPoint>): void $handler */
    public function consume(Closure $handler): void;
}
