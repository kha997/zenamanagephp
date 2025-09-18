#!/bin/bash

# Production Deployment Script
# Dashboard System - Production Deployment

set -e

echo "🚀 Starting Production Deployment for ZenaManage Dashboard..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="zenamanage-dashboard"
DOCKER_COMPOSE_FILE="docker-compose.prod.yml"
ENV_FILE="production.env"

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Docker is running
check_docker() {
    log_info "Checking Docker status..."
    if ! docker info > /dev/null 2>&1; then
        log_error "Docker is not running. Please start Docker and try again."
        exit 1
    fi
    log_success "Docker is running"
}

# Check if Docker Compose is available
check_docker_compose() {
    log_info "Checking Docker Compose..."
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose is not installed. Please install Docker Compose and try again."
        exit 1
    fi
    log_success "Docker Compose is available"
}

# Check environment file
check_env_file() {
    log_info "Checking environment file..."
    if [ ! -f "$ENV_FILE" ]; then
        log_warning "Environment file $ENV_FILE not found. Creating from example..."
        if [ -f "${ENV_FILE}.example" ]; then
            cp "${ENV_FILE}.example" "$ENV_FILE"
            log_warning "Please edit $ENV_FILE with your production settings before continuing."
            log_warning "Press Enter when ready to continue..."
            read -r
        else
            log_error "No environment file found. Please create $ENV_FILE with your production settings."
            exit 1
        fi
    fi
    log_success "Environment file found"
}

# Build and start services
deploy_services() {
    log_info "Building and starting production services..."
    
    # Stop existing containers
    log_info "Stopping existing containers..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" down --remove-orphans
    
    # Build images
    log_info "Building Docker images..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" build --no-cache
    
    # Start services
    log_info "Starting services..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" up -d
    
    log_success "Services started successfully"
}

# Wait for services to be ready
wait_for_services() {
    log_info "Waiting for services to be ready..."
    
    # Wait for MySQL
    log_info "Waiting for MySQL..."
    timeout=60
    while ! docker-compose -f "$DOCKER_COMPOSE_FILE" exec mysql mysqladmin ping -h localhost --silent; do
        sleep 2
        timeout=$((timeout - 2))
        if [ $timeout -le 0 ]; then
            log_error "MySQL failed to start within 60 seconds"
            exit 1
        fi
    done
    log_success "MySQL is ready"
    
    # Wait for Redis
    log_info "Waiting for Redis..."
    timeout=30
    while ! docker-compose -f "$DOCKER_COMPOSE_FILE" exec redis redis-cli ping > /dev/null 2>&1; do
        sleep 2
        timeout=$((timeout - 2))
        if [ $timeout -le 0 ]; then
            log_error "Redis failed to start within 30 seconds"
            exit 1
        fi
    done
    log_success "Redis is ready"
    
    # Wait for Application
    log_info "Waiting for Application..."
    timeout=60
    while ! curl -f http://localhost/health > /dev/null 2>&1; do
        sleep 2
        timeout=$((timeout - 2))
        if [ $timeout -le 0 ]; then
            log_error "Application failed to start within 60 seconds"
            exit 1
        fi
    done
    log_success "Application is ready"
}

# Run database migrations
run_migrations() {
    log_info "Running database migrations..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec app php artisan migrate --force
    log_success "Database migrations completed"
}

# Clear and cache configuration
optimize_application() {
    log_info "Optimizing application..."
    
    # Clear caches
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec app php artisan config:clear
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec app php artisan cache:clear
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec app php artisan route:clear
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec app php artisan view:clear
    
    # Cache configuration
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec app php artisan config:cache
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec app php artisan route:cache
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec app php artisan view:cache
    
    log_success "Application optimized"
}

# Create storage link
create_storage_link() {
    log_info "Creating storage link..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec app php artisan storage:link
    log_success "Storage link created"
}

# Set permissions
set_permissions() {
    log_info "Setting proper permissions..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec app chown -R www-data:www-data storage bootstrap/cache
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec app chmod -R 775 storage bootstrap/cache
    log_success "Permissions set"
}

# Health check
health_check() {
    log_info "Performing health check..."
    
    # Check application health
    if curl -f http://localhost/health > /dev/null 2>&1; then
        log_success "Application health check passed"
    else
        log_error "Application health check failed"
        exit 1
    fi
    
    # Check API health
    if curl -f http://localhost/api/health > /dev/null 2>&1; then
        log_success "API health check passed"
    else
        log_warning "API health check failed (may be expected if API is not implemented)"
    fi
    
    # Check WebSocket health
    if curl -f http://localhost:6001/health > /dev/null 2>&1; then
        log_success "WebSocket health check passed"
    else
        log_warning "WebSocket health check failed (may be expected if WebSocket is not implemented)"
    fi
}

# Show deployment summary
show_summary() {
    log_success "🎉 Deployment completed successfully!"
    echo ""
    echo "📊 Service Status:"
    docker-compose -f "$DOCKER_COMPOSE_FILE" ps
    echo ""
    echo "🌐 Access URLs:"
    echo "  Dashboard: https://dashboard.zenamanage.com"
    echo "  API: https://api.zenamanage.com"
    echo "  WebSocket: wss://ws.zenamanage.com"
    echo "  Grafana: http://localhost:3000"
    echo "  Prometheus: http://localhost:9090"
    echo "  Kibana: http://localhost:5601"
    echo ""
    echo "📝 Next Steps:"
    echo "  1. Configure your DNS to point to this server"
    echo "  2. Set up SSL certificates in docker/nginx/ssl/"
    echo "  3. Configure monitoring alerts in Grafana"
    echo "  4. Set up log aggregation in Kibana"
    echo "  5. Configure backup schedules"
    echo ""
    echo "🔧 Management Commands:"
    echo "  View logs: docker-compose -f $DOCKER_COMPOSE_FILE logs -f [service]"
    echo "  Restart service: docker-compose -f $DOCKER_COMPOSE_FILE restart [service]"
    echo "  Scale service: docker-compose -f $DOCKER_COMPOSE_FILE up -d --scale [service]=[count]"
    echo "  Stop all: docker-compose -f $DOCKER_COMPOSE_FILE down"
}

# Main deployment process
main() {
    log_info "Starting production deployment process..."
    
    check_docker
    check_docker_compose
    check_env_file
    deploy_services
    wait_for_services
    run_migrations
    optimize_application
    create_storage_link
    set_permissions
    health_check
    show_summary
}

# Run main function
main "$@"