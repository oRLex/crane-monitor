<?php

namespace App\Telemetry;

use App\Domain\AlarmKind;
use App\Domain\Severity;

final readonly class Condition
{
    public function __construct(
        public AlarmKind $kind,
        public Severity $severity,
        public string $message,
    ) {}
}
