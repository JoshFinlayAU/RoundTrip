<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = Group::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with(['targets' => fn($q) => $q->orderBy('name')])
            ->get();

        return response()->json($groups);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $group = Group::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json($group, 201);
    }

    public function show(Group $group): JsonResponse
    {
        $group->load(['targets' => fn($q) => $q->orderBy('name')]);
        return response()->json($group);
    }

    public function update(Request $request, Group $group): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $group->update($validated);

        return response()->json($group);
    }

    public function destroy(Group $group): JsonResponse
    {
        $group->delete();

        return response()->json(null, 204);
    }
}
