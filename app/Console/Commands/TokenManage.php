<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class TokenManage extends Command
{
    protected $signature = 'token:manage';
    protected $description = 'Manage API tokens for service accounts';

    public function handle(): int
    {
        while (true) {
            $this->newLine();
            $this->info('=== API Token Management ===');

            $action = $this->choice('Select an action', [
                'list' => 'List tokens for a user',
                'create' => 'Create new token',
                'revoke' => 'Revoke a token',
                'exit' => 'Exit',
            ], 'list');

            match ($action) {
                'list' => $this->listTokens(),
                'create' => $this->createToken(),
                'revoke' => $this->revokeToken(),
                'exit' => exit(0),
            };
        }
    }

    private function listTokens(): void
    {
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->warn('No users found. Create a user first with: php artisan user:manage');
            return;
        }

        $userEmails = $users->pluck('email')->toArray();
        $email = $this->choice('Select user', $userEmails);
        $user = User::where('email', $email)->first();

        $tokens = $user->tokens;

        if ($tokens->isEmpty()) {
            $this->info('No tokens for this user.');
            return;
        }

        $rows = $tokens->map(fn($t) => [
            $t->id,
            $t->name,
            $t->created_at->format('Y-m-d H:i'),
            $t->last_used_at?->format('Y-m-d H:i') ?? 'Never',
        ])->toArray();

        $this->table(['ID', 'Name', 'Created', 'Last Used'], $rows);
    }

    private function createToken(): void
    {
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->warn('No users found. Create a user first with: php artisan user:manage');
            return;
        }

        $userEmails = $users->pluck('email')->toArray();
        $email = $this->choice('Select user', $userEmails);
        $user = User::where('email', $email)->first();

        $name = $this->ask('Token name (e.g. "librenms")', 'api-token');

        $token = $user->createToken($name);

        $this->newLine();
        $this->info('Token created successfully!');
        $this->newLine();
        $this->line('Save this token - it will not be shown again:');
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->warn('Store this securely. Use as: Authorization: Bearer <token>');
    }

    private function revokeToken(): void
    {
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->warn('No users found.');
            return;
        }

        $userEmails = $users->pluck('email')->toArray();
        $email = $this->choice('Select user', $userEmails);
        $user = User::where('email', $email)->first();

        $tokens = $user->tokens;

        if ($tokens->isEmpty()) {
            $this->info('No tokens for this user.');
            return;
        }

        $tokenNames = $tokens->pluck('name')->toArray();
        $tokenName = $this->choice('Select token to revoke', $tokenNames);
        $token = $tokens->firstWhere('name', $tokenName);

        if ($this->confirm("Revoke token '{$tokenName}'?", false)) {
            $token->delete();
            $this->info('Token revoked.');
        }
    }
}
