<?php

namespace Tests\Unit;

use App\Ai\Knowledge\HashEmbeddings;
use PHPUnit\Framework\TestCase;

class HashEmbeddingsTest extends TestCase
{
    public function test_vectors_are_the_same_on_32_and_64_bit_php(): void
    {
        // crc32("limiter overload") has the high bit set: negative on 32-bit PHP (Wasmer).
        // Reference values from 64-bit PHP, so stored embeddings stay valid across runtimes.
        $v = (new HashEmbeddings(1024))->embed(['limiter overload'], 'document')[0];

        $this->assertCount(1024, $v);
        $this->assertEqualsWithDelta([552 => 2 / 3, 740 => -1 / 3, 886 => 2 / 3], array_filter($v), 1e-12);
    }
}
