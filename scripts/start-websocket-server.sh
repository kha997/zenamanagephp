#!/bin/bash

# WebSocket Server Startup Script
# This script starts the WebSocket server for Dashboard real-time updates

set -e

echo "🚀 Starting ZENA Dashboard WebSocket Server..."

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed or not in PATH"
    exit 1
fi

# Check if Laravel is available
if [ ! -f "artisan" ]; then
    echo "❌ Laravel artisan file not found. Please run this script from the Laravel root directory."
    exit 1
fi

# Set environment variables
export WEBSOCKET_HOST=${WEBSOCKET_HOST:-"0.0.0.0"}
export WEBSOCKET_PORT=${WEBSOCKET_PORT:-8080}
export WEBSOCKET_WORKERS=${WEBSOCKET_WORKERS:-1}

echo "📋 Configuration:"
echo "   Host: $WEBSOCKET_HOST"
echo "   Port: $WEBSOCKET_PORT"
echo "   Workers: $WEBSOCKET_WORKERS"
echo ""

# Check if WebSocket dependencies are installed
echo "🔍 Checking WebSocket dependencies..."

if ! php -m | grep -q "sockets"; then
    echo "⚠️  PHP sockets extension not found. Installing..."
    # This would typically be done via package manager
    echo "   Please install php-sockets extension"
fi

# Install Composer dependencies if needed
if [ ! -d "vendor" ]; then
    echo "📦 Installing Composer dependencies..."
    composer install
fi

# Clear Laravel caches
echo "🧹 Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Check database connection
echo "🗄️  Checking database connection..."
php artisan migrate:status > /dev/null 2>&1 || {
    echo "❌ Database connection failed. Please check your database configuration."
    exit 1
}

# Create logs directory if it doesn't exist
mkdir -p storage/logs

# Start WebSocket server
echo "🌐 Starting WebSocket server..."
echo "   Press Ctrl+C to stop the server"
echo ""

# Run the WebSocket server command
php artisan websocket:serve \
    --host="$WEBSOCKET_HOST" \
    --port="$WEBSOCKET_PORT" \
    --workers="$WEBSOCKET_WORKERS"

echo ""
echo "👋 WebSocket server stopped."
