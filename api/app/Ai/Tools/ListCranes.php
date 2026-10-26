<?php

namespace App\Ai\Tools;

use App\Domain\Scope;
use App\Models\Crane;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class ListCranes extends Tool
{
    public function __construct(private readonly FleetTools $fleet) {}

    public function name(): string
    {
        return 'list_cranes';
    }

    public function description(): string
    {
        return 'List the cranes the user can see, with site, live status (operating/idle/alarm/offline), open alarm count and the latest telemetry reading.';
    }

    public function scope(): Scope
    {
        return Scope::CranesRead;
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(array $input, User $user): array
    {
        return ['cranes' => Crane::query()->with('site')->orderBy('id')->get()->map(fn (Crane $c) => $this->fleet->summary($c))->all()];
    }
}
