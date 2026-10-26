<?php

namespace App\Telemetry;

use App\Models\Crane;

/** The physical parameters the simulator needs, decoupled from Eloquent. */
final readonly class CraneSpec
{
    public function __construct(
        public string $id,
        public string $siteId,
        public float $maxLoadT,
        public float $jibLengthM,
        public float $ratedMomentTm,
    ) {}

    public static function fromModel(Crane $crane): self
    {
        return new self($crane->id, $crane->site_id, $crane->max_load_t, $crane->jib_length_m, $crane->rated_moment_tm);
    }
}
