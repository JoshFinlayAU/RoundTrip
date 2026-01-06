#!/bin/bash

# RoundTrip development server launcher
# Starts all required services in the background

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

trap 'kill $(jobs -p) 2>/dev/null' EXIT

echo "Starting RoundTrip development servers..."

# Laravel backend
cd "$PROJECT_DIR"
php artisan serve --host=0.0.0.0 --port=8000 &
BACKEND_PID=$!

# Poller
php artisan roundtrip:poll &
POLLER_PID=$!

# Frontend
cd "$PROJECT_DIR/frontend"
npm run dev &
FRONTEND_PID=$!

echo ""
echo "Services started:"
echo "  Backend:  http://localhost:8000 (PID: $BACKEND_PID)"
echo "  Poller:   running (PID: $POLLER_PID)"
echo "  Frontend: http://localhost:3000 (PID: $FRONTEND_PID)"
echo ""
echo "Press Ctrl+C to stop all services"

wait
