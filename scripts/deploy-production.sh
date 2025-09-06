#!/bin/bash

# Z.E.N.A Project Management - Production Deployment Script
# Author: Development Team
# Version: 1.0

set -e

echo "🚀 Starting Z.E.N.A Project Management deployment..."

# Configuration
APP_DIR="/var/www/zena"
BACKUP_DIR="/var/backups/zena"
DATE=$(date +"%Y%m%d_%H%M%S")

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Pre-deployment checks
log_info "Running pre-deployment checks..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    log_error "Docker is not running. Please start Docker first."
    exit 1
fi

# Check if required files exist
if [ ! -f "docker-compose.yml" ]; then
    log_error "docker-compose.yml not found in current directory."
    exit 1
fi

# Create backup
log_info "Creating backup..."
mkdir -p $BACKUP_DIR
if [ -d "$APP_DIR" ]; then
    tar -czf "$BACKUP_DIR/zena_backup_$DATE.tar.gz" -C "$APP_DIR" .
    log_info "Backup created: $BACKUP_DIR/zena_backup_$DATE.tar.gz"
fi

# Pull latest images
log_info "Pulling latest Docker images..."
docker-compose pull

# Stop existing containers
log_info "Stopping existing containers..."
docker-compose down

# Build and start containers
log_info "Building and starting containers..."
docker-compose up -d --build

# Wait for services to be ready
log_info "Waiting for services to be ready..."
sleep 30

# Run database migrations
log_info "Running database migrations..."
docker-compose exec -T app php artisan migrate --force

# Clear and cache config
log_info "Optimizing application..."
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app php artisan route:cache
docker-compose exec -T app php artisan view:cache

# Run health check
log_info "Running health check..."
if curl -f http://localhost:8080/health > /dev/null 2>&1; then
    log_info "✅ Deployment successful! Application is running."
else
    log_error "❌ Health check failed. Please check the logs."
    docker-compose logs app
    exit 1
fi

# Cleanup old backups (keep last 5)
log_info "Cleaning up old backups..."
find $BACKUP_DIR -name "zena_backup_*.tar.gz" -type f -mtime +30 -delete

log_info "🎉 Deployment completed successfully!"
log_info "Application URL: http://localhost:8080"
log_info "WebSocket URL: ws://localhost:8081"