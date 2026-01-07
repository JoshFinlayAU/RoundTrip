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
    public function index(Request $request): JsonResponse
    {
        $query = Target::query();

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('host', 'ilike', "%{$search}%");
            });
        }

        if ($groupId = $request->get('group_id')) {
            $query->where('group_id', $groupId);
        }

        $targets = $query->orderBy('name')
            ->get(['id', 'name', 'host', 'interval_seconds', 'enabled', 'group_id']);

        return response()->json($targets);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255', 'unique:targets,host'],
            'interval_seconds' => ['nullable', 'integer', 'min:1', 'max:3600'],
            'enabled' => ['nullable', 'boolean'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ]);

        $target = Target::create([
            'name' => $validated['name'],
            'host' => $validated['host'],
            'interval_seconds' => $validated['interval_seconds'] ?? 5,
            'enabled' => $validated['enabled'] ?? true,
            'group_id' => $validated['group_id'] ?? null,
        ]);

        return response()->json($target, 201);
    }

    public function show(Target $target): JsonResponse
    {
        return response()->json($target);
    }

    public function update(Request $request, Target $target): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'host' => ['sometimes', 'string', 'max:255', 'unique:targets,host,' . $target->id],
            'interval_seconds' => ['sometimes', 'integer', 'min:1', 'max:3600'],
            'enabled' => ['sometimes', 'boolean'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ]);

        $target->update($validated);

        return response()->json($target);
    }

    public function destroy(Target $target): JsonResponse
    {
        $target->delete();

        return response()->json(null, 204);
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
