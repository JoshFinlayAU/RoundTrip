<?php

namespace App\Console\Commands;

use App\Models\Group;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class GroupManage extends Command
{
    protected $signature = 'group:manage';
    protected $description = 'Manage groups interactively';

    public function handle()
    {
        while (true) {
            $this->newLine();
            $this->info('=== Group Management ===');
            $this->newLine();

            $choice = $this->choice('Select an action', [
                'list' => 'List all groups',
                'create' => 'Create new group',
                'edit' => 'Edit group',
                'reorder' => 'Change sort order',
                'delete' => 'Delete group',
                'exit' => 'Exit',
            ], 'list');

            match ($choice) {
                'list' => $this->listGroups(),
                'create' => $this->createGroup(),
                'edit' => $this->editGroup(),
                'reorder' => $this->reorderGroups(),
                'delete' => $this->deleteGroup(),
                'exit' => exit(0),
            };
        }
    }

    private function listGroups(): void
    {
        $groups = Group::withCount('targets')->orderBy('sort_order')->get();

        if ($groups->isEmpty()) {
            $this->warn('No groups found.');
            return;
        }

        $this->table(
            ['ID', 'Name', 'Description', 'Sort Order', 'Targets'],
            $groups->map(fn($g) => [
                $g->id,
                $g->name,
                $g->description ?? '-',
                $g->sort_order,
                $g->targets_count,
            ])
        );
    }

    private function createGroup(): void
    {
        $name = $this->ask('Group name');
        $description = $this->ask('Description (optional)', '');

        $maxOrder = Group::max('sort_order') ?? 0;
        $sortOrder = $this->ask('Sort order', $maxOrder + 1);

        $validator = Validator::make([
            'name' => $name,
            'sort_order' => $sortOrder,
        ], [
            'name' => 'required|string|max:255',
            'sort_order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return;
        }

        Group::create([
            'name' => $name,
            'description' => $description ?: null,
            'sort_order' => (int) $sortOrder,
        ]);

        $this->info("Group '{$name}' created successfully.");
    }

    private function editGroup(): void
    {
        $groups = Group::orderBy('sort_order')->get();
        if ($groups->isEmpty()) {
            $this->warn('No groups to edit.');
            return;
        }

        $choices = $groups->pluck('name', 'id')->toArray();
        $groupId = $this->choice('Select group to edit', $choices);

        $group = Group::find($groupId);
        if (!$group) {
            $this->error('Group not found.');
            return;
        }

        $name = $this->ask('Name', $group->name);
        $description = $this->ask('Description', $group->description ?? '');

        $validator = Validator::make([
            'name' => $name,
        ], [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return;
        }

        $group->update([
            'name' => $name,
            'description' => $description ?: null,
        ]);

        $this->info("Group updated successfully.");
    }

    private function reorderGroups(): void
    {
        $groups = Group::orderBy('sort_order')->get();
        if ($groups->isEmpty()) {
            $this->warn('No groups to reorder.');
            return;
        }

        $this->info('Current order:');
        foreach ($groups as $i => $group) {
            $this->line("  " . ($i + 1) . ". {$group->name} (order: {$group->sort_order})");
        }

        $choices = $groups->pluck('name', 'id')->toArray();
        $groupId = $this->choice('Select group to move', $choices);

        $group = Group::find($groupId);
        if (!$group) {
            $this->error('Group not found.');
            return;
        }

        $newOrder = $this->ask('New sort order', $group->sort_order);

        if (!is_numeric($newOrder) || $newOrder < 0) {
            $this->error('Invalid sort order.');
            return;
        }

        $group->update(['sort_order' => (int) $newOrder]);
        $this->info("Group '{$group->name}' moved to position {$newOrder}.");
    }

    private function deleteGroup(): void
    {
        $groups = Group::withCount('targets')->orderBy('sort_order')->get();
        if ($groups->isEmpty()) {
            $this->warn('No groups to delete.');
            return;
        }

        $choices = $groups->mapWithKeys(fn($g) => [
            $g->id => "{$g->name} ({$g->targets_count} targets)"
        ])->toArray();
        $groupId = $this->choice('Select group to delete', $choices);

        $group = Group::find($groupId);
        if (!$group) {
            $this->error('Group not found.');
            return;
        }

        $targetCount = $group->targets()->count();
        $warning = $targetCount > 0 
            ? "This group has {$targetCount} targets. They will be ungrouped."
            : '';

        if (!$this->confirm("Delete group '{$group->name}'? {$warning}", false)) {
            $this->info('Cancelled.');
            return;
        }

        // Ungroup targets
        $group->targets()->update(['group_id' => null]);
        $group->delete();

        $this->info("Group deleted.");
    }
}
