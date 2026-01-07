<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserManage extends Command
{
    protected $signature = 'user:manage';
    protected $description = 'Manage users interactively';

    public function handle()
    {
        while (true) {
            $this->newLine();
            $this->info('=== User Management ===');
            $this->newLine();

            $choice = $this->choice('Select an action', [
                'list' => 'List all users',
                'create' => 'Create new user',
                'edit' => 'Edit user',
                'password' => 'Change password',
                'delete' => 'Delete user',
                'exit' => 'Exit',
            ], 'list');

            match ($choice) {
                'list' => $this->listUsers(),
                'create' => $this->createUser(),
                'edit' => $this->editUser(),
                'password' => $this->changePassword(),
                'delete' => $this->deleteUser(),
                'exit' => exit(0),
            };
        }
    }

    private function listUsers(): void
    {
        $users = User::all(['id', 'name', 'email', 'created_at']);

        if ($users->isEmpty()) {
            $this->warn('No users found.');
            return;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Created'],
            $users->map(fn($u) => [
                $u->id,
                $u->name,
                $u->email,
                $u->created_at->format('Y-m-d H:i'),
            ])
        );
    }

    private function createUser(): void
    {
        $name = $this->ask('Name');
        $email = $this->ask('Email');
        $password = $this->secret('Password');
        $confirm = $this->secret('Confirm password');

        if ($password !== $confirm) {
            $this->error('Passwords do not match.');
            return;
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("User '{$name}' created successfully.");
    }

    private function editUser(): void
    {
        $users = User::all();
        if ($users->isEmpty()) {
            $this->warn('No users to edit.');
            return;
        }

        $choices = $users->pluck('email', 'id')->toArray();
        $userId = $this->choice('Select user to edit', $choices);

        $user = User::find($userId);
        if (!$user) {
            $this->error('User not found.');
            return;
        }

        $name = $this->ask('Name', $user->name);
        $email = $this->ask('Email', $user->email);

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return;
        }

        $user->update([
            'name' => $name,
            'email' => $email,
        ]);

        $this->info("User updated successfully.");
    }

    private function changePassword(): void
    {
        $users = User::all();
        if ($users->isEmpty()) {
            $this->warn('No users found.');
            return;
        }

        $choices = $users->pluck('email', 'id')->toArray();
        $userId = $this->choice('Select user', $choices);

        $user = User::find($userId);
        if (!$user) {
            $this->error('User not found.');
            return;
        }

        $password = $this->secret('New password');
        $confirm = $this->secret('Confirm password');

        if ($password !== $confirm) {
            $this->error('Passwords do not match.');
            return;
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return;
        }

        $user->update([
            'password' => Hash::make($password),
        ]);

        $this->info("Password updated for {$user->email}.");
    }

    private function deleteUser(): void
    {
        $users = User::all();
        if ($users->isEmpty()) {
            $this->warn('No users to delete.');
            return;
        }

        $choices = $users->pluck('email', 'id')->toArray();
        $userId = $this->choice('Select user to delete', $choices);

        $user = User::find($userId);
        if (!$user) {
            $this->error('User not found.');
            return;
        }

        if (!$this->confirm("Delete user '{$user->email}'?", false)) {
            $this->info('Cancelled.');
            return;
        }

        $user->tokens()->delete();
        $user->delete();

        $this->info("User deleted.");
    }
}
