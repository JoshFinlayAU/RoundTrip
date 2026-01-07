<?php

namespace App\Plugins\RoundTrip;

use App\Models\Device;
use App\Plugins\Hooks\DeviceOverviewHook;
use Illuminate\Contracts\Auth\Authenticatable;

class DeviceOverview extends DeviceOverviewHook
{
    public function authorize(?Authenticatable $user, Device $device): bool
    {
        return true;
    }

    public function data(Device $device, array $settings = []): array
    {
        // Pass device info and settings to the view - all API calls happen client-side
        return [
            'title' => 'RoundTrip Latency',
            'device_id' => $device->device_id,
            'hostname' => $device->hostname,
            'sysName' => $device->sysName,
            'ip' => $device->ip,
            'api_url' => $settings['api_url'] ?? 'http://localhost:8000',
            'api_token' => $settings['api_token'] ?? '',
        ];
    }
}
