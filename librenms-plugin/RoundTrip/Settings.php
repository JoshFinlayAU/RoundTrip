<?php

namespace App\Plugins\RoundTrip;

use App\Plugins\Hooks\SettingsHook;

class Settings extends SettingsHook
{
    public function data(array $settings = []): array
    {
        return [
            'settings' => $settings,
            'api_url' => $settings['api_url'] ?? 'http://localhost:8000',
            'api_token' => $settings['api_token'] ?? '',
        ];
    }
}
