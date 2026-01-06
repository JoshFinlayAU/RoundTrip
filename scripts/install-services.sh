#!/bin/bash
set -e

# Install RoundTrip systemd services
# Run as root after setup.sh
# Designed to coexist with LibreNMS on the same server

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "Installing RoundTrip services..."

# Copy systemd service files
cp "$PROJECT_DIR/deploy/roundtrip-api.service" /etc/systemd/system/
cp "$PROJECT_DIR/deploy/roundtrip-poller.service" /etc/systemd/system/

# Reload systemd
systemctl daemon-reload

# Enable and start services
systemctl enable roundtrip-api roundtrip-poller
systemctl start roundtrip-api roundtrip-poller

# Build frontend for production
cd "$PROJECT_DIR/frontend"
sudo -u roundtrip cp .env.example .env.local
sudo -u roundtrip npm run build

echo ""
echo "Services installed and running:"
echo "  systemctl status roundtrip-api"
echo "  systemctl status roundtrip-poller"
echo ""
echo "To add RoundTrip to your nginx config (e.g. LibreNMS):"
echo "  Add this line inside your server block:"
echo ""
echo "    include /opt/roundtrip/deploy/nginx.conf;"
echo ""
echo "  Then reload nginx: systemctl reload nginx"
echo ""
echo "RoundTrip will be available at http://yourserver/roundtrip"
