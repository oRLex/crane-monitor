<?php

namespace App\Domain;

enum Severity: string
{
    case Warning = 'warning';
    case Critical = 'critical';

    public function rank(): int
    {
        return match ($this) {
            self::Warning => 1,
            self::Critical => 2,
        };
    }
}
