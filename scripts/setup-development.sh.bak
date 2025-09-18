#!/bin/bash

# Z.E.N.A Project Management - Development Setup Script
# Author: Development Team
# Version: 1.0

set -e

echo "🛠️ Setting up Z.E.N.A Project Management for development..."

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

# Install PHP dependencies
log_info "Installing PHP dependencies..."
if [ -f "composer.json" ]; then
    composer install
else
    log_warn "composer.json not found. Skipping PHP dependencies."
fi

# Install Node.js dependencies for frontend
log_info "Installing Node.js dependencies..."
if [ -d "frontend" ] && [ -f "frontend/package.json" ]; then
    cd frontend
    npm install
    cd ..
else
    log_warn "Frontend directory or package.json not found. Skipping Node.js dependencies."
fi

# Copy environment file
log_info "Setting up environment configuration..."
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
        log_info "Environment file created from .env.example"
    else
        log_warn ".env.example not found. Please create .env manually."
    fi
fi

# Generate application key
log_info "Generating application key..."
php artisan key:generate

# Create storage symlink
log_info "Creating storage symlink..."
php artisan storage:link

# Set permissions
log_info "Setting file permissions..."
chmod -R 775 storage bootstrap/cache

# Start Docker services for development
log_info "Starting Docker services..."
docker-compose -f docker-compose.dev.yml up -d mysql redis

# Wait for database
log_info "Waiting for database to be ready..."
sleep 10

# Run migrations and seeders
log_info "Running database migrations and seeders..."
php artisan migrate:fresh --seed

# Install Passport keys
log_info "Installing Passport keys..."
php artisan passport:install

log_info "🎉 Development setup completed!"
log_info "You can now start the development server with: php artisan serve"
log_info "Frontend development server: cd frontend && npm run dev"