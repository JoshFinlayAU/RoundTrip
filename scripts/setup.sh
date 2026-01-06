#!/bin/bash
set -e

# RoundTrip setup script
# Run after install-deps.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "Setting up RoundTrip..."

# Create roundtrip user if it doesn't exist
if ! id -u roundtrip &>/dev/null; then
    useradd -r -s /bin/bash -d /opt/roundtrip roundtrip
fi

# Create database and user
sudo -u postgres psql -c "CREATE USER roundtrip WITH PASSWORD 'roundtrip';" 2>/dev/null || true
sudo -u postgres psql -c "CREATE DATABASE roundtrip OWNER roundtrip;" 2>/dev/null || true
sudo -u postgres psql -d roundtrip -c "CREATE EXTENSION IF NOT EXISTS timescaledb;"

# Set ownership
chown -R roundtrip:roundtrip "$PROJECT_DIR"

# Backend setup
cd "$PROJECT_DIR"
sudo -u roundtrip cp .env.example .env
sudo -u roundtrip php artisan key:generate
sudo -u roundtrip php artisan migrate --force

# Frontend setup
cd "$PROJECT_DIR/frontend"
sudo -u roundtrip cp .env.example .env.local
sudo -u roundtrip npm install
sudo -u roundtrip npm run build

echo ""
echo "Setup complete!"
echo ""
echo "To start development servers:"
echo "  Backend:   cd $PROJECT_DIR && php artisan serve"
echo "  Reverb:    cd $PROJECT_DIR && php artisan reverb:start"
echo "  Poller:    cd $PROJECT_DIR && php artisan roundtrip:poll"
echo "  Frontend:  cd $PROJECT_DIR/frontend && npm run dev"
