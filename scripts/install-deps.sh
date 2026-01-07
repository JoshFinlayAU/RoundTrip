#!/bin/bash
set -e

# Install deps for RoundTrip on Debian 12
# Run as root

if [ "$EUID" -ne 0 ]; then
    echo "Run this as root"
    exit 1
fi

echo "Installing dependencies..."

# Node 20
if ! command -v node &> /dev/null; then
    echo "Adding NodeSource repo..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
fi

# PostgreSQL official repo (for latest 15.x that works with TimescaleDB)
if [ ! -f /etc/apt/sources.list.d/pgdg.list ]; then
    echo "Adding PostgreSQL repo..."
    echo "deb http://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" > /etc/apt/sources.list.d/pgdg.list
    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /etc/apt/trusted.gpg.d/pgdg.gpg
fi

# TimescaleDB repo
if [ ! -f /etc/apt/sources.list.d/timescaledb.list ]; then
    echo "Adding TimescaleDB repo..."
    echo "deb https://packagecloud.io/timescale/timescaledb/debian/ bookworm main" > /etc/apt/sources.list.d/timescaledb.list
    curl -fsSL https://packagecloud.io/timescale/timescaledb/gpgkey | gpg --dearmor -o /etc/apt/trusted.gpg.d/timescaledb.gpg
fi

apt-get update

apt-get install -y \
    php8.2-fpm \
    php8.2-cli \
    php8.2-pgsql \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-curl \
    php8.2-zip \
    composer \
    nodejs \
    postgresql-15 \
    timescaledb-2-postgresql-15 \
    nginx \
    build-essential \
    autoconf \
    automake \
    curl \
    git

# TimescaleDB config
TSDB_CONF="/etc/postgresql/15/main/conf.d/timescaledb.conf"
if [ ! -f "$TSDB_CONF" ] || ! grep -q "timescaledb" "$TSDB_CONF" 2>/dev/null; then
    echo "shared_preload_libraries = 'timescaledb'" > "$TSDB_CONF"
    systemctl restart postgresql
fi

# fping 5.5 from source - need JSON output which older versions dont have
if ! /usr/local/sbin/fping --version 2>/dev/null | grep -q "5\.[5-9]"; then
    echo "Building fping 5.5..."
    FPING_VERSION="5.5"
    cd /tmp
    rm -rf "fping-${FPING_VERSION}"*
    curl -LO "https://fping.org/dist/fping-${FPING_VERSION}.tar.gz"
    tar xzf "fping-${FPING_VERSION}.tar.gz"
    cd "fping-${FPING_VERSION}"
    ./configure --prefix=/usr/local
    make -j$(nproc)
    make install
    setcap cap_net_raw+ep /usr/local/sbin/fping
fi

echo ""
echo "Done. Versions:"
echo "  fping: $(/usr/local/sbin/fping --version 2>&1 | head -1)"
echo "  node:  $(node -v)"
echo "  php:   $(php -v | head -1)"
echo ""
echo "Next: ./scripts/setup.sh"
