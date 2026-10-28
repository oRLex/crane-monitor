<?php

namespace App\Telemetry;

final readonly class TelemetryPoint
{
    public function __construct(
        public string $craneId,
        /** Unix epoch, ms (see EpochMs). */
        public float $ts,
        public float $loadT,
        public float $radiusM,
        public float $loadMomentPct,
        public float $windMs,
        public float $slewDeg,
        public float $hookHeightM,
        public bool $online,
    ) {}

    public function withOnline(bool $online): self
    {
        return new self($this->craneId, $this->ts, $this->loadT, $this->radiusM, $this->loadMomentPct, $this->windMs, $this->slewDeg, $this->hookHeightM, $online);
    }

    /** @return array{craneId: string, ts: float, loadT: float, radiusM: float, loadMomentPct: float, windMs: float, slewDeg: float, hookHeightM: float, online: bool} */
    public function toArray(): array
    {
        return [
            'craneId' => $this->craneId,
            'ts' => $this->ts,
            'loadT' => $this->loadT,
            'radiusM' => $this->radiusM,
            'loadMomentPct' => $this->loadMomentPct,
            'windMs' => $this->windMs,
            'slewDeg' => $this->slewDeg,
            'hookHeightM' => $this->hookHeightM,
            'online' => $this->online,
        ];
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            (string) $a['craneId'], (float) $a['ts'], (float) $a['loadT'], (float) $a['radiusM'],
            (float) $a['loadMomentPct'], (float) $a['windMs'], (float) $a['slewDeg'],
            (float) $a['hookHeightM'], (bool) $a['online'],
        );
    }
}
