<?php

namespace App\Http\Controllers;

use App\Http\Resources\CraneResource;
use App\Models\Alarm;
use App\Models\Crane;
use App\Telemetry\TelemetryStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CraneController
{
    public function index(): AnonymousResourceCollection
    {
        return CraneResource::collection(Crane::query()->with('site')->orderBy('id')->get());
    }

    /** Other tenants' cranes are filtered by the global scope, so binding 404s (not 403). */
    public function show(Crane $crane): CraneResource
    {
        return new CraneResource($crane->load('site'));
    }

    public function telemetry(Request $request, Crane $crane, TelemetryStore $store): JsonResponse
    {
        $limit = min(120, max(1, (int) $request->query('limit', '120')));

        return response()->json(['points' => array_map(fn ($p) => $p->toArray(), $store->history($crane->id, $limit))]);
    }

    /** Initial state for the real-time view; afterwards the client applies WebSocket events. */
    public function snapshot(TelemetryStore $store): JsonResponse
    {
        $cranes = Crane::query()->pluck('id');
        $points = [];
        $history = [];
        foreach ($cranes as $id) {
            if ($latest = $store->latest($id)) {
                $points[] = $latest->toArray();
            }
            $history[$id] = array_map(fn ($p) => $p->toArray(), $store->history($id, 60));
        }
        $alarms = Alarm::query()->whereIn('crane_id', $cranes)
            ->where(fn ($q) => $q->whereNull('cleared_at')->orWhere('raised_at', '>=', now()->subHours(6)))
            ->orderByDesc('raised_at')->limit(100)->get();

        return response()->json([
            'points' => $points,
            'history' => (object) $history,
            'alarms' => $alarms->map->toWire()->values(),
        ]);
    }
}
