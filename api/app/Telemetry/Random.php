<?php

namespace App\Telemetry;

/**
 * mulberry32 — the same seeded PRNG the original TypeScript simulator used, bit-for-bit.
 *
 * Unsigned 32-bit words are kept as [hi16, lo16] pairs so the arithmetic is identical on
 * 64-bit PHP and on 32-bit PHP (Wasmer), where ints overflow into floats.
 */
final class Random
{
    /** @var array{int, int} */
    private array $a;

    public function __construct(int $seed)
    {
        $this->a = [($seed >> 16) & 0xFFFF, $seed & 0xFFFF];
    }

    public function next(): float
    {
        $this->a = self::add($this->a, [0x6D2B, 0x79F5]);
        $t = $this->a;
        $t = self::imul(self::xor($t, self::shr($t, 15)), self::or($t, 1));
        $t = self::xor($t, self::add($t, self::imul(self::xor($t, self::shr($t, 7)), self::or($t, 61))));
        $t = self::xor($t, self::shr($t, 14));

        return ($t[0] * 65536 + $t[1]) / 4294967296;
    }

    /**
     * @param  array{int, int}  $a
     * @param  array{int, int}  $b
     * @return array{int, int}
     */
    private static function add(array $a, array $b): array
    {
        $lo = $a[1] + $b[1];

        return [($a[0] + $b[0] + ($lo >> 16)) & 0xFFFF, $lo & 0xFFFF];
    }

    /**
     * JS Math.imul (low 32 bits of the product). Partial products may exceed a 32-bit int
     * and become floats; they stay below 2^53, so they are exact.
     *
     * @param  array{int, int}  $a
     * @param  array{int, int}  $b
     * @return array{int, int}
     */
    private static function imul(array $a, array $b): array
    {
        $low = $a[1] * $b[1];
        $carry = floor($low / 65536);

        return [(int) fmod($carry + $a[0] * $b[1] + $a[1] * $b[0], 65536), (int) fmod($low, 65536)];
    }

    /**
     * @param  array{int, int}  $a
     * @param  array{int, int}  $b
     * @return array{int, int}
     */
    private static function xor(array $a, array $b): array
    {
        return [$a[0] ^ $b[0], $a[1] ^ $b[1]];
    }

    /**
     * @param  array{int, int}  $a
     * @return array{int, int}
     */
    private static function or(array $a, int $small): array
    {
        return [$a[0], $a[1] | $small];
    }

    /**
     * Logical right shift by 0 < $n < 16.
     *
     * @param  array{int, int}  $a
     * @return array{int, int}
     */
    private static function shr(array $a, int $n): array
    {
        return [$a[0] >> $n, (($a[1] >> $n) | ($a[0] << (16 - $n))) & 0xFFFF];
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
