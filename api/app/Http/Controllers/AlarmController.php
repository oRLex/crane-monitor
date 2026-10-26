<?php

namespace App\Http\Controllers;

use App\Events\AlarmChanged;
use App\Models\Alarm;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlarmController
{
    public function index(Request $request): JsonResponse
    {
        $alarms = Alarm::query()
            ->when($request->query('crane'), fn ($q, $crane) => $q->where('crane_id', $crane))
            ->when($request->boolean('open'), fn ($q) => $q->open())
            ->orderByDesc('raised_at')
            ->limit(min(200, (int) $request->query('limit', '50')))
            ->get();

        return response()->json(['alarms' => $alarms->map->toWire()->values()]);
    }

    /** Idempotent; scope is checked by route middleware, tenant by the global scope (404). */
    public function acknowledge(Request $request, Alarm $alarm): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($alarm->acknowledged_at === null) {
            $alarm->update(['acknowledged_at' => now(), 'acknowledged_by' => $user->name]);
            broadcast(new AlarmChanged($alarm));
        }

        return response()->json(['alarm' => $alarm->toWire()]);
    }
}
