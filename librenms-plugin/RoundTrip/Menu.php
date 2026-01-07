<?php

namespace App\Plugins\RoundTrip;

use App\Plugins\Hooks\MenuEntryHook;

class Menu extends MenuEntryHook
{
    public function data(array $settings = []): array
    {
        return [
            'title' => 'RoundTrip',
            'url' => '/roundtrip',
            'external' => true,
        ];
    }
}
