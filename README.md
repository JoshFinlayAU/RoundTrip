# RoundTrip

A modern, clean alternative to SmokePing for network latency monitoring.

## Features

- Latency monitoring with fping (JSON output)
- TimescaleDB for efficient time-series storage
- D3.js smoke-style visualization
- Runs alongside LibreNMS on the same server

## Requirements

- Debian 12 (bookworm)
- PostgreSQL 15 with TimescaleDB
- PHP 8.2+
- Node.js 20+
- fping 5.5+ (built from source for JSON support)

## Quick Start

```bash
# Install dependencies (run as root)
./scripts/install-deps.sh

# Setup application
./scripts/setup.sh

# Install systemd services
./scripts/install-services.sh
```

Then add to your nginx config and access at `http://yourserver/roundtrip`

## Production Install (with LibreNMS)

RoundTrip is designed to run alongside LibreNMS on the same server.

After running the install scripts, add this to your LibreNMS nginx server block:

```nginx
include /opt/roundtrip/deploy/nginx.conf;
```

Then reload nginx:

```bash
nginx -t && systemctl reload nginx
```

RoundTrip will be available at `http://yourserver/roundtrip`

## Development

For local development:

```bash
./scripts/dev.sh
```

Then open http://localhost:3000/roundtrip

## Adding Targets

```bash
php artisan tinker
>>> App\Models\Target::create(['name' => 'Google DNS', 'host' => '8.8.8.8']);
```

## API Endpoints

- `GET /roundtrip/api/targets` - List all targets
- `GET /roundtrip/api/targets/{id}/series` - Get ping data for a target

## Services

RoundTrip runs two systemd services:

- `roundtrip-api` - Laravel API server
- `roundtrip-poller` - Polls targets every 5 seconds

Check status:

```bash
systemctl status roundtrip-api roundtrip-poller
```

## Architecture

```
Laravel Backend (PHP 8.2)
  - REST API for targets and series data
  - Poller using fping 5.5 JSON output

TimescaleDB (PostgreSQL 15)
  - Hypertable for ping_results

Next.js Frontend
  - D3.js smoke chart
  - Polls API every 5 seconds
```

## License

MIT
