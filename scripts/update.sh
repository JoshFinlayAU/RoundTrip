#!/bin/bash
set -e

# Auto-update RoundTrip to the latest stable release (tagged version)
# Run as root - will switch to roundtrip user for app operations

PROJECT_DIR="/opt/roundtrip"
cd "$PROJECT_DIR"

if [ "$EUID" -ne 0 ]; then
    echo "Run this as root"
    exit 1
fi

echo "=== RoundTrip Auto-Update ==="
echo ""

# Fetch latest tags (force to handle any local tag conflicts)
echo "Fetching updates..."
sudo -u roundtrip git config --global --add safe.directory /opt/roundtrip
sudo -u roundtrip git fetch --tags --force --quiet

# Find latest tag (sorted by version number)
LATEST=$(sudo -u roundtrip git tag -l 'v*' --sort=-v:refname | head -1)

if [ -z "$LATEST" ]; then
    echo "No release tags found. Nothing to update."
    exit 0
fi

# Get commit hashes for comparison
CURRENT_COMMIT=$(sudo -u roundtrip git rev-parse HEAD)
LATEST_COMMIT=$(sudo -u roundtrip git rev-parse "$LATEST" 2>/dev/null)

echo "Current: $(sudo -u roundtrip git describe --tags --always 2>/dev/null) ($CURRENT_COMMIT)"
echo "Latest:  $LATEST ($LATEST_COMMIT)"

# Check if already on latest by comparing commits
if [ "$CURRENT_COMMIT" = "$LATEST_COMMIT" ]; then
    echo "Already on latest version. Nothing to do."
    exit 0
fi

echo ""
echo "Updating to $LATEST..."
echo ""

# Checkout the latest tag
sudo -u roundtrip git checkout "$LATEST" --quiet

# Fix ownership after checkout
chown -R roundtrip:roundtrip "$PROJECT_DIR"

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
