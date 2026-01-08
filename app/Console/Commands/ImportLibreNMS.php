<?php

namespace App\Console\Commands;

use App\Models\Target;
use App\Models\Group;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportLibreNMS extends Command
{
    protected $signature = 'librenms:import 
                            {--url=http://localhost : LibreNMS API URL}
                            {--token= : LibreNMS API token}
                            {--dry-run : Show what would be imported without making changes}
                            {--interval=60 : Default ping interval in seconds}';
    
    protected $description = 'Import devices from LibreNMS into RoundTrip';

    private string $apiUrl;
    private string $apiToken;
    private bool $dryRun;
    private int $defaultInterval;

    public function handle(): int
    {
        $this->apiUrl = rtrim($this->option('url'), '/');
        $this->apiToken = $this->option('token');
        $this->dryRun = $this->option('dry-run');
        $this->defaultInterval = (int) $this->option('interval');

        if (empty($this->apiToken)) {
            $this->apiToken = $this->ask('Enter LibreNMS API token');
            if (empty($this->apiToken)) {
                $this->error('API token is required.');
                return 1;
            }
        }

        $this->info("Connecting to LibreNMS at {$this->apiUrl}...");
        
        if ($this->dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Fetch device groups from LibreNMS
        $this->info('Fetching device groups...');
        $libreGroups = $this->fetchDeviceGroups();
        if ($libreGroups === null) {
            return 1;
        }
        $this->info("Found " . count($libreGroups) . " device groups in LibreNMS");

        // Fetch all devices from LibreNMS
        $this->info('Fetching devices...');
        $devices = $this->fetchDevices();
        if ($devices === null) {
            return 1;
        }
        $this->info("Found " . count($devices) . " devices in LibreNMS");

        // Get existing targets by host (both hostname and any IPs)
        $existingHosts = Target::pluck('host')->map(fn($h) => strtolower($h))->toArray();
        $existingGroups = Group::pluck('id', 'name')->toArray();
        
        // Also track existing targets by name for matching
        $existingNames = Target::pluck('name')->map(fn($n) => strtolower($n))->toArray();

        // Track stats
        $stats = [
            'devices_found' => count($devices),
            'devices_skipped' => 0,
            'devices_imported' => 0,
            'groups_created' => 0,
        ];

        // Process each device
        $this->newLine();
        $this->info('Processing devices...');
        
        $progressBar = $this->output->createProgressBar(count($devices));
        $progressBar->start();

        foreach ($devices as $device) {
            $hostname = $device['hostname'] ?? null;
            $sysName = $device['sysName'] ?? $hostname;
            $deviceId = $device['device_id'] ?? null;

            if (!$hostname) {
                $progressBar->advance();
                continue;
            }

            // Check if already exists by hostname, IP, or name
            $ip = $device['ip'] ?? null;
            $checkValues = array_filter([
                strtolower($hostname),
                $ip ? strtolower($ip) : null,
                $sysName ? strtolower($sysName) : null,
            ]);
            
            $alreadyExists = false;
            foreach ($checkValues as $val) {
                if (in_array($val, $existingHosts) || in_array($val, $existingNames)) {
                    $alreadyExists = true;
                    break;
                }
            }
            
            if ($alreadyExists) {
                $stats['devices_skipped']++;
                $progressBar->advance();
                continue;
            }

            // Get device's group from LibreNMS
            $groupName = null;
            if ($deviceId) {
                $deviceGroups = $this->fetchDeviceGroupsForDevice($deviceId);
                if (!empty($deviceGroups)) {
                    // Pick the first group
                    $groupName = $deviceGroups[0]['name'] ?? null;
                }
            }

            // Create or find RoundTrip group
            $groupId = null;
            if ($groupName) {
                if (isset($existingGroups[$groupName])) {
                    $groupId = $existingGroups[$groupName];
                } else {
                    if (!$this->dryRun) {
                        $maxSortOrder = Group::max('sort_order') ?? 0;
                        $group = Group::create([
                            'name' => $groupName,
                            'sort_order' => $maxSortOrder + 1,
                        ]);
                        $groupId = $group->id;
                    }
                    // Track group as "created" for both dry-run and real run
                    $existingGroups[$groupName] = $groupId ?? -1;
                    $stats['groups_created']++;
                }
            }

            // Create target
            if (!$this->dryRun) {
                Target::create([
                    'name' => $sysName ?: $hostname,
                    'host' => $hostname,
                    'interval_seconds' => $this->defaultInterval,
                    'enabled' => true,
                    'group_id' => $groupId,
                ]);
                $existingHosts[] = strtolower($hostname);
            }
            $stats['devices_imported']++;

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('=== Import Summary ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Devices found in LibreNMS', $stats['devices_found']],
                ['Devices skipped (already exist)', $stats['devices_skipped']],
                ['Devices imported', $stats['devices_imported']],
                ['Groups created', $stats['groups_created']],
            ]
        );

        if ($this->dryRun) {
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to actually import.');
        }

        return 0;
    }

    private function fetchDeviceGroups(): ?array
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiToken,
            ])->get("{$this->apiUrl}/api/v0/devicegroups");

            if (!$response->successful()) {
                $this->error("Failed to fetch device groups: HTTP {$response->status()}");
                $this->error($response->body());
                return null;
            }

            $data = $response->json();
            return $data['groups'] ?? [];
        } catch (\Exception $e) {
            $this->error("Failed to connect to LibreNMS API: " . $e->getMessage());
            return null;
        }
    }

    private function fetchDevices(): ?array
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiToken,
            ])->get("{$this->apiUrl}/api/v0/devices");

            if (!$response->successful()) {
                $this->error("Failed to fetch devices: HTTP {$response->status()}");
                $this->error($response->body());
                return null;
            }

            $data = $response->json();
            return $data['devices'] ?? [];
        } catch (\Exception $e) {
            $this->error("Failed to connect to LibreNMS API: " . $e->getMessage());
            return null;
        }
    }

    private function fetchDeviceGroupsForDevice(int $deviceId): array
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiToken,
            ])->get("{$this->apiUrl}/api/v0/devices/{$deviceId}/groups");

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();
            return $data['groups'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
