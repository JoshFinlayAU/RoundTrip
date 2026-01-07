<?php

namespace App\Console\Commands;

use App\Models\Target;
use App\Models\Group;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class TargetManage extends Command
{
    protected $signature = 'target:manage';
    protected $description = 'Manage targets interactively';

    public function handle()
    {
        while (true) {
            $this->newLine();
            $this->info('=== Target Management ===');
            $this->newLine();

            $choice = $this->choice('Select an action', [
                'list' => 'List all targets',
                'create' => 'Create new target',
                'edit' => 'Edit target',
                'toggle' => 'Enable/Disable target',
                'delete' => 'Delete target',
                'exit' => 'Exit',
            ], 'list');

            match ($choice) {
                'list' => $this->listTargets(),
                'create' => $this->createTarget(),
                'edit' => $this->editTarget(),
                'toggle' => $this->toggleTarget(),
                'delete' => $this->deleteTarget(),
                'exit' => exit(0),
            };
        }
    }

    private function listTargets(): void
    {
        $targets = Target::with('group')->get();

        if ($targets->isEmpty()) {
            $this->warn('No targets found.');
            return;
        }

        $this->table(
            ['ID', 'Name', 'Host', 'Interval', 'Enabled', 'Group'],
            $targets->map(fn($t) => [
                $t->id,
                $t->name,
                $t->host,
                $t->interval_seconds . 's',
                $t->enabled ? 'Yes' : 'No',
                $t->group?->name ?? '-',
            ])
        );
    }

    private function createTarget(): void
    {
        $name = $this->ask('Target name');
        $host = $this->ask('Host (IP or hostname)');
        $interval = $this->ask('Ping interval (seconds)', 60);

        $groups = Group::orderBy('sort_order')->get();
        $groupId = null;
        if ($groups->isNotEmpty()) {
            $groupChoices = ['none' => 'No group'] + $groups->pluck('name', 'id')->toArray();
            $groupSelection = $this->choice('Assign to group', $groupChoices, 'none');
            $groupId = $groupSelection === 'none' ? null : $groupSelection;
        }

        $validator = Validator::make([
            'name' => $name,
            'host' => $host,
            'interval_seconds' => $interval,
        ], [
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'interval_seconds' => 'required|integer|min:5|max:3600',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return;
        }

        Target::create([
            'name' => $name,
            'host' => $host,
            'interval_seconds' => (int) $interval,
            'enabled' => true,
            'group_id' => $groupId,
        ]);

        $this->info("Target '{$name}' created successfully.");
    }

    private function editTarget(): void
    {
        $targets = Target::all();
        if ($targets->isEmpty()) {
            $this->warn('No targets to edit.');
            return;
        }

        $choices = $targets->mapWithKeys(fn($t) => [$t->id => "{$t->name} ({$t->host})"])->toArray();
        $targetId = $this->choice('Select target to edit', $choices);

        $target = Target::find($targetId);
        if (!$target) {
            $this->error('Target not found.');
            return;
        }

        $name = $this->ask('Name', $target->name);
        $host = $this->ask('Host', $target->host);
        $interval = $this->ask('Interval (seconds)', $target->interval_seconds);

        $groups = Group::orderBy('sort_order')->get();
        $groupId = $target->group_id;
        if ($groups->isNotEmpty()) {
            $groupChoices = ['none' => 'No group'] + $groups->pluck('name', 'id')->toArray();
            $currentGroup = $target->group_id ?? 'none';
            $groupSelection = $this->choice('Assign to group', $groupChoices, $currentGroup);
            $groupId = $groupSelection === 'none' ? null : $groupSelection;
        }

        $validator = Validator::make([
            'name' => $name,
            'host' => $host,
            'interval_seconds' => $interval,
        ], [
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'interval_seconds' => 'required|integer|min:5|max:3600',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return;
        }

        $target->update([
            'name' => $name,
            'host' => $host,
            'interval_seconds' => (int) $interval,
            'group_id' => $groupId,
        ]);

        $this->info("Target updated successfully.");
    }

    private function toggleTarget(): void
    {
        $targets = Target::all();
        if ($targets->isEmpty()) {
            $this->warn('No targets found.');
            return;
        }

        $choices = $targets->mapWithKeys(fn($t) => [
            $t->id => "{$t->name} - " . ($t->enabled ? 'Enabled' : 'Disabled')
        ])->toArray();
        $targetId = $this->choice('Select target to toggle', $choices);

        $target = Target::find($targetId);
        if (!$target) {
            $this->error('Target not found.');
            return;
        }

        $target->update(['enabled' => !$target->enabled]);
        $status = $target->enabled ? 'enabled' : 'disabled';
        $this->info("Target '{$target->name}' is now {$status}.");
    }

    private function deleteTarget(): void
    {
        $targets = Target::all();
        if ($targets->isEmpty()) {
            $this->warn('No targets to delete.');
            return;
        }

        $choices = $targets->mapWithKeys(fn($t) => [$t->id => "{$t->name} ({$t->host})"])->toArray();
        $targetId = $this->choice('Select target to delete', $choices);

        $target = Target::find($targetId);
        if (!$target) {
            $this->error('Target not found.');
            return;
        }

        if (!$this->confirm("Delete target '{$target->name}'? This will also delete all ping history.", false)) {
            $this->info('Cancelled.');
            return;
        }

        $target->pingResults()->delete();
        $target->delete();

        $this->info("Target deleted.");
    }
}
