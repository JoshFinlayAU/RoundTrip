#!/bin/bash
set -e

# RoundTrip dependency installation script for Debian 12 (bookworm)
# Run as root

echo "Installing RoundTrip dependencies..."

# Add NodeSource repo for Node 20
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -

# Add TimescaleDB repo
echo "deb https://packagecloud.io/timescale/timescaledb/debian/ bookworm main" > /etc/apt/sources.list.d/timescaledb.list
curl -fsSL https://packagecloud.io/timescale/timescaledb/gpgkey | gpg --dearmor -o /etc/apt/trusted.gpg.d/timescaledb.gpg

apt-get update

# Install system packages
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
    timescaledb-2-postgresql-15=2.23.1~debian12-1514 \
    nginx \
    build-essential \
    autoconf \
    automake \
    curl \
    git

# Enable TimescaleDB extension
echo "shared_preload_libraries = 'timescaledb'" >> /etc/postgresql/15/main/conf.d/timescaledb.conf
systemctl restart postgresql

# Build fping 5.5 from source (required for JSON output)
echo "Building fping 5.5..."
FPING_VERSION="5.5"
cd /tmp
curl -LO "https://fping.org/dist/fping-${FPING_VERSION}.tar.gz"
tar xzf "fping-${FPING_VERSION}.tar.gz"
cd "fping-${FPING_VERSION}"
./configure --prefix=/usr/local
make -j$(nproc)
make install

# Set capability so fping works without root
setcap cap_net_raw+ep /usr/local/sbin/fping

echo "Dependencies installed successfully."
echo "fping version: $(/usr/local/sbin/fping --version)"
echo "Node version: $(node -v)"
echo "PHP version: $(php -v | head -1)"
