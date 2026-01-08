#!/bin/bash
set -e

# Auto-update RoundTrip to the latest stable release (tagged version)
# Run as root or with sudo

PROJECT_DIR="/opt/roundtrip"
cd "$PROJECT_DIR"

echo "=== RoundTrip Auto-Update ==="
echo ""

# Get current version
CURRENT=$(git describe --tags --always 2>/dev/null || echo "unknown")
echo "Current version: $CURRENT"

# Fetch latest tags (force to handle any local tag conflicts)
echo "Fetching updates..."
git fetch --tags --force --quiet

# Find latest tag (sorted by version number)
LATEST=$(git tag -l 'v*' --sort=-v:refname | head -1)

if [ -z "$LATEST" ]; then
    echo "No release tags found. Nothing to update."
    exit 0
fi

echo "Latest release: $LATEST"

# Check if already on latest
if [ "$CURRENT" = "$LATEST" ]; then
    echo "Already on latest version. Nothing to do."
    exit 0
fi

echo ""
echo "Updating from $CURRENT to $LATEST..."
echo ""

# Checkout the latest tag
git checkout "$LATEST" --quiet

# Run composer install
echo "Installing PHP dependencies..."
sudo -u roundtrip composer install --no-dev --optimize-autoloader --quiet

# Run migrations
echo "Running database migrations..."
sudo -u roundtrip php artisan migrate --force

# Build frontend
echo "Building frontend..."
cd frontend
sudo -u roundtrip npm ci --silent
sudo -u roundtrip npm run build
cd ..

# Clear caches
echo "Clearing caches..."
sudo -u roundtrip php artisan config:clear
sudo -u roundtrip php artisan cache:clear

# Restart services
echo "Restarting services..."
systemctl restart roundtrip-api
systemctl restart roundtrip-poller

echo ""
echo "=== Update complete ==="
echo "Now running: $LATEST"
