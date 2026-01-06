<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PingResult;
use App\Models\Target;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TargetController extends Controller
{
    public function index(): JsonResponse
    {
        $targets = Target::query()
            ->orderBy('name')
            ->get(['id', 'name', 'host', 'interval_seconds', 'enabled']);

        return response()->json($targets);
    }

    public function series(Request $request, Target $target): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])
            : CarbonImmutable::now();

        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])
            : $to->subHour();

        $limit = $validated['limit'] ?? 2000;

        $series = PingResult::query()
            ->where('target_id', $target->id)
            ->whereBetween('ts', [$from, $to])
            ->orderBy('ts')
            ->limit($limit)
            ->get(['ts', 'min_ms', 'avg_ms', 'max_ms', 'loss_pct']);

        return response()->json([
            'target' => $target->only(['id', 'name', 'host', 'interval_seconds', 'enabled']),
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'points' => $series,
        ]);
    }
}
