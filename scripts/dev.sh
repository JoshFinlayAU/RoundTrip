#!/bin/bash

# Dev server - runs backend, poller, and frontend together

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

trap 'kill $(jobs -p) 2>/dev/null' EXIT

cd "$PROJECT_DIR"
php artisan serve --host=0.0.0.0 --port=8000 &
php artisan roundtrip:poll &

cd "$PROJECT_DIR/frontend"
npm run dev &

echo ""
echo "Running:"
echo "  API:      http://localhost:8000"
echo "  Frontend: http://localhost:3000/roundtrip"
echo ""
echo "Ctrl+C to stop"

wait
