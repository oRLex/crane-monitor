<?php

namespace Tests\Unit;

use App\Telemetry\CraneSpec;
use App\Telemetry\Random;
use App\Telemetry\Simulator;
use PHPUnit\Framework\TestCase;

class SimulatorTest extends TestCase
{
    /** @return list<CraneSpec> */
    private function cranes(): array
    {
        return [
            new CraneSpec('c-101', 's-podil', 8, 60, 172),
            new CraneSpec('c-102', 's-podil', 12, 65, 280),
            new CraneSpec('c-201', 's-warsaw', 8, 60, 172),
        ];
    }

    public function test_prng_matches_the_original_typescript_mulberry32(): void
    {
        $r = new Random(42);
        // Values produced by the JS implementation for seed 42.
        foreach ([0.6011037519201636, 0.44829055899754167, 0.8524657934904099] as $expected) {
            $this->assertEqualsWithDelta($expected, $r->next(), 1e-15);
        }
    }

    public function test_ticks_default_to_now_in_epoch_ms(): void
    {
        $point = (new Simulator($this->cranes(), 42))->tick()[0];

        // Epoch ms don't fit a 32-bit int (Wasmer's PHP); an overflow lands in January 1970.
        $this->assertEqualsWithDelta(microtime(true) * 1000, $point->ts, 5000);
    }

    public function test_deterministic_for_a_seed(): void
    {
        $a = new Simulator($this->cranes(), 42);
        $b = new Simulator($this->cranes(), 42);
        for ($i = 0; $i < 50; $i++) {
            $this->assertEquals($a->tick($i), $b->tick($i));
        }
    }

    public function test_physically_bounded_over_a_long_run(): void
    {
        $cranes = $this->cranes();
        $byId = array_column(array_map(fn ($c) => ['id' => $c->id, 'c' => $c], $cranes), 'c', 'id');
        $sim = new Simulator($cranes, 7, 0.01);
        $sawLoad = $sawOffline = false;
        for ($i = 0; $i < 2000; $i++) {
            foreach ($sim->tick($i) as $p) {
                $crane = $byId[$p->craneId];
                $this->assertGreaterThanOrEqual(0, $p->radiusM);
                $this->assertLessThanOrEqual($crane->jibLengthM, $p->radiusM);
                $this->assertLessThanOrEqual($crane->maxLoadT * 1.1, $p->loadT);
                $this->assertGreaterThanOrEqual(0, $p->windMs);
                $this->assertLessThan(360.1, $p->slewDeg);
                $sawLoad = $sawLoad || $p->loadT > 1;
                $sawOffline = $sawOffline || ! $p->online;
            }
        }
        $this->assertTrue($sawLoad);
        $this->assertTrue($sawOffline);
    }
}
