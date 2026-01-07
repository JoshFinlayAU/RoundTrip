# RoundTrip Plugin for LibreNMS

Shows RoundTrip latency graphs on device overview pages.

## Features

- Smoke-style latency graph with interactive tooltips
- Real-time updates (refreshes every 5 seconds)
- Selectable time range (15m to 24h)
- Current/min/max latency and packet loss stats
- One-click "Add to RoundTrip" for devices not yet monitored
- Timestamps shown in browser's local timezone

## Install

The install script symlinks the plugin so updates to RoundTrip include plugin updates automatically.

```bash
./librenms-plugin/install.sh
```

Then generate an API token:

```bash
cd /opt/roundtrip
php artisan token:manage
# Choose "create", pick a user, name it "librenms" or similar
# Save the token - you need it next
```

## Configure

1. Go to LibreNMS Settings > System > Plugins
2. Enable "RoundTrip"
3. Click the gear icon to open settings
4. Set API URL to your RoundTrip instance (e.g., `/roundtrip` if on same server)
5. Paste the API token
6. Save

## Matching

The plugin matches devices to RoundTrip targets by checking:
- Device hostname
- Device IP
- Device sysName

If no match exists, you'll see an "Add to RoundTrip" button that creates a target using the device's sysName and IP.
