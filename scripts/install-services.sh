#!/bin/bash
set -e

# Install systemd services for RoundTrip
# Run after setup.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

if [ "$EUID" -ne 0 ]; then
    echo "Run this as root"
    exit 1
fi

echo "Installing services..."

cp "$PROJECT_DIR/deploy/roundtrip-api.service" /etc/systemd/system/
cp "$PROJECT_DIR/deploy/roundtrip-poller.service" /etc/systemd/system/
cp "$PROJECT_DIR/deploy/roundtrip-update.service" /etc/systemd/system/
cp "$PROJECT_DIR/deploy/roundtrip-update.timer" /etc/systemd/system/

systemctl daemon-reload
systemctl enable roundtrip-api roundtrip-poller roundtrip-update.timer
systemctl start roundtrip-api roundtrip-poller roundtrip-update.timer

echo ""
echo "Services running:"
systemctl is-active roundtrip-api roundtrip-poller || true
echo ""
echo "Add to your nginx server block:"
echo ""
echo "    include /opt/roundtrip/deploy/nginx.conf;"
echo ""
echo "Then: nginx -t && systemctl reload nginx"
echo ""
echo "Access at http://yourserver/roundtrip"
