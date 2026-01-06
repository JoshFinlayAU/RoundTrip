# RoundTrip

A modern, clean alternative to SmokePing for network latency monitoring.

## Features

- Real-time latency monitoring with fping
- TimescaleDB for efficient time-series storage
- WebSocket streaming for live updates
- D3.js smoke-style visualization
- Simple target management via API

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

# Start development servers
./scripts/dev.sh
```

Then open http://localhost:3000

## Manual Setup

### Database

```bash
sudo -u postgres psql -c "CREATE USER roundtrip WITH PASSWORD 'roundtrip';"
sudo -u postgres psql -c "CREATE DATABASE roundtrip OWNER roundtrip;"
sudo -u postgres psql -d roundtrip -c "CREATE EXTENSION IF NOT EXISTS timescaledb;"
```

### Backend

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### Frontend

```bash
cd frontend
cp .env.example .env.local
npm install
npm run build
```

## Running

Start each service in a seperate terminal:

```bash
# API server
php artisan serve

# WebSocket server
php artisan reverb:start

# Poller (collects ping data)
php artisan roundtrip:poll

# Frontend dev server
cd frontend && npm run dev
```

## API Endpoints

- `GET /api/targets` - List all targets
- `GET /api/targets/{id}/series` - Get ping data for a target

## Adding Targets

```bash
php artisan tinker
>>> App\Models\Target::create(['name' => 'Google DNS', 'host' => '8.8.8.8']);
```

## Architecture

```
Laravel Backend (PHP)
  - REST API for targets and series data
  - Artisan poller command using fping JSON output
  - Reverb WebSocket server for real-time updates

TimescaleDB
  - Hypertable for ping_results (optimized time-series queries)

Next.js Frontend
  - D3.js smoke chart visualization
  - Laravel Echo for WebSocket subscriptions
```

## License

MIT
