#!/bin/bash
set -e

# Setup RoundTrip - run after install-deps.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

if [ "$EUID" -ne 0 ]; then
    echo "Run this as root"
    exit 1
fi

echo "Setting up RoundTrip..."

# Create system user
if ! id -u roundtrip &>/dev/null; then
    echo "Creating roundtrip user..."
    useradd -r -s /bin/bash -d /opt/roundtrip roundtrip
fi

# Database
echo "Setting up database..."
sudo -u postgres psql -c "CREATE USER roundtrip WITH PASSWORD 'roundtrip';" 2>/dev/null || true
sudo -u postgres psql -c "CREATE DATABASE roundtrip OWNER roundtrip;" 2>/dev/null || true
sudo -u postgres psql -d roundtrip -c "CREATE EXTENSION IF NOT EXISTS timescaledb;" 2>/dev/null || true

chown -R roundtrip:roundtrip "$PROJECT_DIR"

# Laravel
echo "Configuring backend..."
cd "$PROJECT_DIR"
if [ ! -f .env ]; then
    sudo -u roundtrip cp .env.example .env
    sudo -u roundtrip php artisan key:generate
fi
sudo -u roundtrip composer install --no-dev --optimize-autoloader
sudo -u roundtrip php artisan migrate --force

# Frontend
echo "Building frontend..."
cd "$PROJECT_DIR/frontend"
if [ ! -f .env.local ]; then
    sudo -u roundtrip cp .env.example .env.local
fi
sudo -u roundtrip npm install
sudo -u roundtrip npm run build

echo ""
echo "Setup done."
echo ""
echo "Create an admin user:"
echo "  cd $PROJECT_DIR && php artisan user:manage"
echo ""
echo "For production, run: ./scripts/install-services.sh"
echo "For development:     ./scripts/dev.sh"
