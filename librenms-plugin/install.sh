#!/bin/bash

# Install RoundTrip plugin for LibreNMS (symlink)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LIBRENMS_DIR="${LIBRENMS_DIR:-/opt/librenms}"

if [ ! -d "$LIBRENMS_DIR/app/Plugins" ]; then
    echo "LibreNMS not found at $LIBRENMS_DIR"
    echo "Set LIBRENMS_DIR if installed elsewhere"
    exit 1
fi

TARGET="$LIBRENMS_DIR/app/Plugins/RoundTrip"

# Remove existing (symlink or directory)
if [ -L "$TARGET" ]; then
    rm "$TARGET"
elif [ -d "$TARGET" ]; then
    echo "Existing directory found at $TARGET"
    echo "Remove it first if you want to symlink"
    exit 1
fi

echo "Symlinking RoundTrip plugin to $LIBRENMS_DIR..."

ln -s "$SCRIPT_DIR/RoundTrip" "$TARGET"

echo ""
echo "Plugin installed (symlinked)."
echo "Updates to RoundTrip will automatically include plugin updates."
echo ""
echo "Next steps:"
echo "  1. Generate API token: cd /opt/roundtrip && php artisan token:manage"
echo "  2. Enable plugin in LibreNMS: http://yourserver/plugin/settings"
echo "  3. Click Settings next to RoundTrip to configure API URL and token"
