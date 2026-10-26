<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateShiftReport;
use App\Models\Crane;
use App\Models\ShiftReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController
{
    public function index(Request $request): JsonResponse
    {
        $reports = ShiftReport::query()->with('crane')
            ->when($request->query('crane'), fn ($q, $c) => $q->where('crane_id', $c))
            ->latest()->limit(30)->get();

        return response()->json(['reports' => $reports->map->toWire()->values()]);
    }

    public function show(ShiftReport $report): JsonResponse
    {
        return response()->json(['report' => $report->load('crane')->toWire()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'crane_id' => ['required', 'string', 'max:40'],
            'hours' => ['sometimes', 'integer', 'between:1,24'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $crane = Crane::query()->findOrFail((string) $data['crane_id']);
        $end = now()->startOfMinute();

        $report = ShiftReport::query()->create([
            'tenant_id' => $crane->tenant_id,
            'crane_id' => $crane->id,
            'requested_by' => $user->id,
            'period_start' => $end->copy()->subHours($data['hours'] ?? 8),
            'period_end' => $end,
            'status' => 'queued',
        ]);
        GenerateShiftReport::dispatch($report->id);

        return response()->json(['report' => $report->fresh(['crane'])?->toWire()], 202);
    }
}
