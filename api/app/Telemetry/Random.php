<?php

namespace App\Telemetry;

/** mulberry32 — the same seeded PRNG the original TypeScript simulator used, bit-for-bit. */
final class Random
{
    private int $a;

    public function __construct(int $seed)
    {
        $this->a = $seed & 0xFFFFFFFF;
    }

    public function next(): float
    {
        $this->a = ($this->a + 0x6D2B79F5) & 0xFFFFFFFF;
        $t = $this->a;
        $t = self::imul($t ^ ($t >> 15), $t | 1);
        $t ^= ($t + self::imul($t ^ ($t >> 7), $t | 61)) & 0xFFFFFFFF;

        return (($t ^ ($t >> 14)) & 0xFFFFFFFF) / 4294967296;
    }

    /** JS Math.imul for unsigned 32-bit operands, without overflowing PHP's 64-bit ints. */
    private static function imul(int $a, int $b): int
    {
        $lo = ($a & 0xFFFF) * $b;
        $hi = (($a >> 16) * $b) & 0xFFFF;

        return ($lo + ($hi << 16)) & 0xFFFFFFFF;
    }

    public static function clamp(float $v, float $min, float $max): float
    {
        return min($max, max($min, $v));
    }

    /** JS-compatible Math.round (half up, not half away from zero). */
    public static function round(float $v, int $digits = 1): float
    {
        $f = 10 ** $digits;

        return floor($v * $f + 0.5) / $f;
    }
}
