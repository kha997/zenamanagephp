#!/bin/bash

# Quick script to start Nginx dev proxy using Docker
# This uses the Docker-specific config that connects to host services via host.docker.internal

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
NGINX_CONFIG_DOCKER="$PROJECT_ROOT/docker/nginx/dev-proxy.docker.conf"

echo "Starting Nginx dev proxy with Docker..."
echo "Config: $NGINX_CONFIG_DOCKER"
echo ""

# Check if Docker is available
if ! command -v docker &> /dev/null; then
    echo "✗ Docker is not installed or not in PATH"
    echo "  Please install Docker Desktop: https://www.docker.com/products/docker-desktop"
    exit 1
fi

# Check if Docker daemon is running
if ! docker info &> /dev/null; then
    echo "✗ Docker daemon is not running"
    echo "  Please start Docker Desktop"
    exit 1
fi

# Check if config exists
if [ ! -f "$NGINX_CONFIG_DOCKER" ]; then
    echo "✗ Docker Nginx config not found: $NGINX_CONFIG_DOCKER"
    exit 1
fi

# Stop and remove existing container if it exists
if docker ps -a | grep -q zenamanage-dev-proxy; then
    echo "Stopping existing container..."
    docker stop zenamanage-dev-proxy > /dev/null 2>&1
    docker rm zenamanage-dev-proxy > /dev/null 2>&1
fi

# Start new container
echo "Starting Nginx container..."
docker run -d \
    --name zenamanage-dev-proxy \
    -p 80:80 \
    -v "$NGINX_CONFIG_DOCKER:/etc/nginx/conf.d/default.conf:ro" \
    --add-host=host.docker.internal:host-gateway \
    nginx:alpine

if [ $? -eq 0 ]; then
    echo "✓ Nginx dev proxy started in Docker"
    echo ""
    echo "Container: zenamanage-dev-proxy"
    echo "Port: 80"
    echo ""
    echo "Access:"
    echo "  - Admin (Blade): http://dev.zena.local/admin/users"
    echo "  - App (React): http://dev.zena.local/app/dashboard"
    echo "  - API: http://dev.zena.local/api/v1/..."
    echo ""
    echo "To view logs:"
    echo "  docker logs -f zenamanage-dev-proxy"
    echo ""
    echo "To stop:"
    echo "  docker stop zenamanage-dev-proxy && docker rm zenamanage-dev-proxy"
    echo ""
    echo "Or use: ./scripts/stop-dev-proxy.sh"
else
    echo "✗ Failed to start Nginx container"
    exit 1
fi

