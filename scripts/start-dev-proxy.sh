#!/bin/bash

# Script to start Nginx dev proxy for single-origin routing
# This script provides multiple options for running the dev proxy

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
NGINX_CONFIG="$PROJECT_ROOT/docker/nginx/dev-proxy.conf"

echo "Starting Nginx dev proxy for single-origin routing..."
echo "Config: $NGINX_CONFIG"
echo ""

# Check if config exists
if [ ! -f "$NGINX_CONFIG" ]; then
    echo "✗ Nginx config not found: $NGINX_CONFIG"
    exit 1
fi

# Option 1: Docker (recommended if Nginx not installed)
if command -v docker &> /dev/null; then
    echo "Option 1: Using Docker..."
    echo "Starting Nginx container..."
    
    # Use Docker-specific config
    NGINX_CONFIG_DOCKER="$PROJECT_ROOT/docker/nginx/dev-proxy.docker.conf"
    
    if [ ! -f "$NGINX_CONFIG_DOCKER" ]; then
        echo "✗ Docker Nginx config not found: $NGINX_CONFIG_DOCKER"
        echo "  Falling back to regular config (may not work in Docker)"
        NGINX_CONFIG_DOCKER="$NGINX_CONFIG"
    fi
    
    docker run -d \
        --name zenamanage-dev-proxy \
        -p 80:80 \
        -v "$NGINX_CONFIG_DOCKER:/etc/nginx/conf.d/default.conf:ro" \
        --add-host=host.docker.internal:host-gateway \
        nginx:alpine
    
    if [ $? -eq 0 ]; then
        echo "✓ Nginx dev proxy started in Docker"
        echo "  Container: zenamanage-dev-proxy"
        echo "  Access: http://dev.zena.local"
        echo ""
        echo "To stop: docker stop zenamanage-dev-proxy && docker rm zenamanage-dev-proxy"
        exit 0
    fi
fi

# Option 2: System Nginx
if command -v nginx &> /dev/null; then
    echo "Option 2: Using system Nginx..."
    echo "Testing config..."
    nginx -t -c "$NGINX_CONFIG"
    
    if [ $? -eq 0 ]; then
        echo "Starting Nginx with dev config..."
        sudo nginx -c "$NGINX_CONFIG"
        if [ $? -eq 0 ]; then
            echo "✓ Nginx dev proxy started"
            echo "  Access: http://dev.zena.local"
            echo ""
            echo "To stop: sudo nginx -s stop -c $NGINX_CONFIG"
            exit 0
        fi
    else
        echo "✗ Nginx config test failed"
        exit 1
    fi
fi

# Option 3: Homebrew Nginx
if [ -f "/opt/homebrew/bin/nginx" ] || [ -f "/usr/local/bin/nginx" ]; then
    NGINX_BIN="/opt/homebrew/bin/nginx"
    [ ! -f "$NGINX_BIN" ] && NGINX_BIN="/usr/local/bin/nginx"
    
    echo "Option 3: Using Homebrew Nginx..."
    echo "Testing config..."
    "$NGINX_BIN" -t -c "$NGINX_CONFIG"
    
    if [ $? -eq 0 ]; then
        echo "Starting Nginx with dev config..."
        sudo "$NGINX_BIN" -c "$NGINX_CONFIG"
        if [ $? -eq 0 ]; then
            echo "✓ Nginx dev proxy started"
            echo "  Access: http://dev.zena.local"
            exit 0
        fi
    fi
fi

echo "✗ Could not start Nginx dev proxy"
echo ""
echo "Please install Nginx or Docker:"
echo "  - macOS: brew install nginx"
echo "  - Or use Docker: docker run ... (see docker-compose.dev-proxy.yml)"
echo ""
echo "Or run services directly on ports:"
echo "  - Laravel: php artisan serve --port=8000"
echo "  - React: cd frontend && npm run dev"
echo "  - Access: http://localhost:8000/admin/users or http://localhost:5173/app/dashboard"

