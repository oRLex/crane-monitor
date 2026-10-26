<?php

namespace App\Http\Resources;

use App\Domain\Scope;
use App\Models\Crane;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Crane */
class CraneResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'model' => $this->model,
            'serialNumber' => $this->serial_number,
            'maxLoadT' => $this->max_load_t,
            'jibLengthM' => $this->jib_length_m,
            'ratedMomentTm' => $this->rated_moment_tm,
            // Field-level authorization: stream URLs never leave the API without video:read.
            'streamUrl' => $user instanceof User && $user->hasScope(Scope::VideoRead) ? $this->stream_url : null,
            'siteId' => $this->site_id,
            'tenantId' => $this->tenant_id,
            'site' => $this->whenLoaded('site', fn () => [
                'id' => $this->site->id,
                'name' => $this->site->name,
                'city' => $this->site->city,
                'tenantId' => $this->site->tenant_id,
            ]),
        ];
    }
}
