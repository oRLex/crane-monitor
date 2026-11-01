<?php

namespace Tests\Unit;

use App\Support\EpochMs;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class EpochMsTest extends TestCase
{
    public function test_round_trips_current_dates_on_32_bit_php(): void
    {
        // Carbon::createFromTimestampMs() casts to int: on 32-bit PHP (Wasmer) every date
        // collapsed to 1970-01-25 20:31 and all hourly aggregates landed in one bucket.
        $date = EpochMs::toDate(1793510761566.0);

        $this->assertSame('2026-11-01 05:26:01.566', $date->utc()->format('Y-m-d H:i:s.v'));
        $this->assertSame(1793510761566.0, EpochMs::of($date));
    }

    public function test_keeps_milliseconds_of_dates_before_the_epoch(): void
    {
        $this->assertSame(-1500.0, EpochMs::of(EpochMs::toDate(-1500.0)));
        $this->assertSame(EpochMs::of(new DateTimeImmutable('@0')), EpochMs::of(EpochMs::toDate(0.0)));
    }
}
