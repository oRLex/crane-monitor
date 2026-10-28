<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Unix epoch in milliseconds, as a float: today's values (~1.8e12) overflow a 32-bit int
 * (PHP on Wasmer), while a float holds them exactly up to 2^53. JSON output is the same
 * as for an int (no fraction is printed).
 */
final class EpochMs
{
    public static function of(DateTimeInterface $at): float
    {
        return (float) $at->format('Uv');
    }

    public static function now(): float
    {
        return floor(microtime(true) * 1000);
    }
}
