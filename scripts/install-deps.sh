#!/bin/bash
set -e

# Install deps for RoundTrip on Debian/Ubuntu
# Supported: Debian 11 (bullseye), 12 (bookworm), Ubuntu 22.04 (jammy), 24.04 (noble)
# Run as root

if [ "$EUID" -ne 0 ]; then
    echo "Run this as root"
    exit 1
fi

# Detect OS and version
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS_ID="$ID"
    OS_VERSION="$VERSION_ID"
    OS_CODENAME="$VERSION_CODENAME"
else
    echo "Cannot detect OS. /etc/os-release not found."
    exit 1
fi

echo "Detected: $OS_ID $OS_VERSION ($OS_CODENAME)"

# Determine PHP version and TimescaleDB repo based on OS
case "$OS_ID" in
    debian)
        case "$OS_CODENAME" in
            bullseye)  # Debian 11
                PHP_VERSION="8.1"
                TSDB_DISTRO="debian"
                TSDB_CODENAME="bullseye"
                ;;
            bookworm)  # Debian 12
                PHP_VERSION="8.2"
                TSDB_DISTRO="debian"
                TSDB_CODENAME="bookworm"
                ;;
            trixie)    # Debian 13
                PHP_VERSION="8.4"
                TSDB_DISTRO="debian"
                TSDB_CODENAME="trixie"
                ;;
            *)
                echo "Unsupported Debian version: $OS_CODENAME"
                exit 1
                ;;
        esac
        PGDG_CODENAME="$OS_CODENAME-pgdg"
        ;;
    ubuntu)
        case "$OS_CODENAME" in
            focal)     # Ubuntu 20.04
                PHP_VERSION="8.1"
                TSDB_DISTRO="ubuntu"
                TSDB_CODENAME="focal"
                USE_PHP_PPA=true
                ;;
            jammy)     # Ubuntu 22.04
                PHP_VERSION="8.1"
                TSDB_DISTRO="ubuntu"
                TSDB_CODENAME="jammy"
                USE_PHP_PPA=true
                ;;
            noble)     # Ubuntu 24.04
                PHP_VERSION="8.3"
                TSDB_DISTRO="ubuntu"
                TSDB_CODENAME="noble"
                ;;
            *)
                echo "Unsupported Ubuntu version: $OS_CODENAME"
                exit 1
                ;;
        esac
        PGDG_CODENAME="$OS_CODENAME-pgdg"
        ;;
    *)
        echo "Unsupported OS: $OS_ID"
        exit 1
        ;;
esac

echo "Using PHP $PHP_VERSION"
echo "Installing dependencies..."

# PHP PPA for older Ubuntu versions that don't have PHP 8.1+ in default repos
if [ "$USE_PHP_PPA" = true ]; then
    if [ ! -f /etc/apt/sources.list.d/ondrej-ubuntu-php-*.list ]; then
        echo "Adding PHP PPA..."
        apt-get install -y software-properties-common
        add-apt-repository -y ppa:ondrej/php
    fi
fi

# Node 20
if ! command -v node &> /dev/null; then
    echo "Adding NodeSource repo..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
fi

# PostgreSQL official repo (for latest 15.x that works with TimescaleDB)
if [ ! -f /etc/apt/sources.list.d/pgdg.list ]; then
    echo "Adding PostgreSQL repo..."
    echo "deb http://apt.postgresql.org/pub/repos/apt $PGDG_CODENAME main" > /etc/apt/sources.list.d/pgdg.list
    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /etc/apt/trusted.gpg.d/pgdg.gpg
fi

# TimescaleDB repo
if [ ! -f /etc/apt/sources.list.d/timescaledb.list ]; then
    echo "Adding TimescaleDB repo..."
    echo "deb https://packagecloud.io/timescale/timescaledb/$TSDB_DISTRO/ $TSDB_CODENAME main" > /etc/apt/sources.list.d/timescaledb.list
    curl -fsSL https://packagecloud.io/timescale/timescaledb/gpgkey | gpg --dearmor -o /etc/apt/trusted.gpg.d/timescaledb.gpg
fi

apt-get update

apt-get install -y \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-pgsql \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-zip \
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
    mkdir -p /etc/postgresql/15/main/conf.d
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
echo "  OS:    $OS_ID $OS_VERSION ($OS_CODENAME)"
echo "  fping: $(/usr/local/sbin/fping --version 2>&1 | head -1)"
echo "  node:  $(node -v)"
echo "  php:   $(php -v | head -1)"
echo "  psql:  $(psql --version)"
echo ""
echo "Next: ./scripts/setup.sh"
