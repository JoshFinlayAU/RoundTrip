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
            'limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])
            : CarbonImmutable::now();

        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])
            : $to->subHour();

        $rangeMinutes = $from->diffInMinutes($to);
        
        // For longer ranges, use continuous aggregates or aggregate on-the-fly
        // Target ~2000 points max for good chart performance
        if ($rangeMinutes > 43200) { // > 1 month: use hourly continuous aggregate
            $series = \DB::select("
                SELECT 
                    bucket,
                    min_ms,
                    avg_ms,
                    max_ms,
                    CASE WHEN sample_count > 0 
                        THEN (lost_count::float / sample_count * 100) 
                        ELSE 0 
                    END as loss_pct
                FROM ping_results_hourly
                WHERE target_id = ? AND bucket BETWEEN ? AND ?
                ORDER BY bucket
            ", [$target->id, $from, $to]);
            
            $series = collect($series)->map(fn($row) => [
                'ts' => CarbonImmutable::parse($row->bucket)->format('Y-m-d\TH:i:s\Z'),
                'min_ms' => $row->min_ms ? round((float)$row->min_ms, 3) : null,
                'avg_ms' => $row->avg_ms ? round((float)$row->avg_ms, 3) : null,
                'max_ms' => $row->max_ms ? round((float)$row->max_ms, 3) : null,
                'loss_pct' => $row->loss_pct ? round((float)$row->loss_pct, 2) : null,
            ]);
        } elseif ($rangeMinutes > 1440) { // > 24h: use 5-min continuous aggregate
            $series = \DB::select("
                SELECT 
                    bucket,
                    min_ms,
                    avg_ms,
                    max_ms,
                    CASE WHEN sample_count > 0 
                        THEN (lost_count::float / sample_count * 100) 
                        ELSE 0 
                    END as loss_pct
                FROM ping_results_5min
                WHERE target_id = ? AND bucket BETWEEN ? AND ?
                ORDER BY bucket
            ", [$target->id, $from, $to]);
            
            $series = collect($series)->map(fn($row) => [
                'ts' => CarbonImmutable::parse($row->bucket)->format('Y-m-d\TH:i:s\Z'),
                'min_ms' => $row->min_ms ? round((float)$row->min_ms, 3) : null,
                'avg_ms' => $row->avg_ms ? round((float)$row->avg_ms, 3) : null,
                'max_ms' => $row->max_ms ? round((float)$row->max_ms, 3) : null,
                'loss_pct' => $row->loss_pct ? round((float)$row->loss_pct, 2) : null,
            ]);
        } elseif ($rangeMinutes > 360) { // > 6 hours: aggregate on-the-fly (1-min buckets)
            $series = \DB::select("
                SELECT 
                    time_bucket('1 minute', ts) as bucket,
                    MIN(rtt_ms) as min_ms,
                    AVG(rtt_ms) as avg_ms,
                    MAX(rtt_ms) as max_ms,
                    (SUM(CASE WHEN lost THEN 1 ELSE 0 END)::float / COUNT(*) * 100) as loss_pct
                FROM ping_results
                WHERE target_id = ? AND ts BETWEEN ? AND ?
                GROUP BY bucket
                ORDER BY bucket
            ", [$target->id, $from, $to]);
            
            $series = collect($series)->map(fn($row) => [
                'ts' => CarbonImmutable::parse($row->bucket)->format('Y-m-d\TH:i:s\Z'),
                'min_ms' => $row->min_ms ? round((float)$row->min_ms, 3) : null,
                'avg_ms' => $row->avg_ms ? round((float)$row->avg_ms, 3) : null,
                'max_ms' => $row->max_ms ? round((float)$row->max_ms, 3) : null,
                'loss_pct' => $row->loss_pct ? round((float)$row->loss_pct, 2) : null,
            ]);
        } else {
            // For short ranges, return individual RTT points for true smoke effect
            $series = PingResult::query()
                ->where('target_id', $target->id)
                ->whereBetween('ts', [$from, $to])
                ->orderBy('ts')
                ->orderBy('seq')
                ->get(['ts', 'rtt_ms', 'seq', 'lost']);
        }

        return response()->json([
            'target' => $target->only(['id', 'name', 'host', 'interval_seconds', 'enabled']),
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'points' => $series,
        ]);
    }
}
