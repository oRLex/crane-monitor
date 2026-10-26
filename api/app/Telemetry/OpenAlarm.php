<?php

namespace App\Telemetry;

use App\Domain\AlarmKind;
use App\Domain\Severity;

final readonly class OpenAlarm
{
    public function __construct(
        public string $id,
        public AlarmKind $kind,
        public Severity $severity,
    ) {}
}
