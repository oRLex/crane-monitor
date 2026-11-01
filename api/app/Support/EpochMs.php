<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Unix epoch in milliseconds, as a float: today's values (~1.8e12) overflow a 32-bit int
 * (PHP on Wasmer), while a float holds them exactly up to 2^53. JSON output is the same
 * as for an int (no fraction is printed).
 */
final class EpochMs
{
    public static function of(DateTimeInterface $at): float
    {
        return (float) $at->format('U') * 1000 + (float) $at->format('v'); // not 'Uv': wrong before 1970
    }

    public static function now(): float
    {
        return floor(microtime(true) * 1000);
    }

    /** Use instead of Carbon::createFromTimestampMs(), which casts to int and overflows on 32-bit. */
    public static function toDate(float $ms): Carbon
    {
        $seconds = floor($ms / 1000);
        $date = Carbon::createFromFormat('U', sprintf('%.0F', $seconds))
            ?? throw new InvalidArgumentException("Invalid epoch ms: {$ms}");

        return $date->addMilliseconds((int) round($ms - $seconds * 1000))->setTimezone(date_default_timezone_get());
    }
}
