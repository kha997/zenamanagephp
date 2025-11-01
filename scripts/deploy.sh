#!/bin/bash

# ZenaManage Dashboard Deployment Script
# This script handles the deployment of the dashboard system

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="zenamanage-dashboard"
DOCKER_COMPOSE_FILE="docker-compose.yml"
ENV_FILE=".env"
BACKUP_DIR="backups"
LOG_FILE="deployment.log"

# Functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a $LOG_FILE
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a $LOG_FILE
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a $LOG_FILE
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a $LOG_FILE
    exit 1
}

# Check if required tools are installed
check_requirements() {
    log "Checking requirements..."
    
    if ! command -v docker &> /dev/null; then
        error "Docker is not installed"
    fi
    
    if ! command -v docker-compose &> /dev/null; then
        error "Docker Compose is not installed"
    fi
    
    if ! command -v git &> /dev/null; then
        error "Git is not installed"
    fi
    
    success "All requirements are met"
}

# Create backup
create_backup() {
    log "Creating backup..."
    
    if [ ! -d "$BACKUP_DIR" ]; then
        mkdir -p "$BACKUP_DIR"
    fi
    
    BACKUP_NAME="backup-$(date +'%Y%m%d-%H%M%S')"
    BACKUP_PATH="$BACKUP_DIR/$BACKUP_NAME"
    
    mkdir -p "$BACKUP_PATH"
    
    # Backup database
    if docker-compose ps db | grep -q "Up"; then
        log "Backing up database..."
        docker-compose exec -T db mysqldump -u root -p${DB_ROOT_PASSWORD:-root_password} --all-databases > "$BACKUP_PATH/database.sql"
        success "Database backup created"
    fi
    
    # Backup application files
    log "Backing up application files..."
    tar -czf "$BACKUP_PATH/application.tar.gz" --exclude=node_modules --exclude=vendor --exclude=.git .
    success "Application backup created"
    
    # Keep only last 5 backups
    ls -t "$BACKUP_DIR" | tail -n +6 | xargs -I {} rm -rf "$BACKUP_DIR/{}"
    
    success "Backup completed: $BACKUP_PATH"
}

# Pull latest changes
pull_changes() {
    log "Pulling latest changes from repository..."
    
    if [ -d ".git" ]; then
        git fetch origin
        git reset --hard origin/main
        success "Repository updated"
    else
        warning "Not a git repository, skipping git pull"
    fi
}

# Install dependencies
install_dependencies() {
    log "Installing dependencies..."
    
    # Install PHP dependencies
    if [ -f "composer.json" ]; then
        log "Installing PHP dependencies..."
        docker-compose run --rm app composer install --no-dev --optimize-autoloader
        success "PHP dependencies installed"
    fi
    
    # Install Node.js dependencies
    if [ -f "frontend/package.json" ]; then
        log "Installing Node.js dependencies..."
        docker-compose run --rm frontend npm install
        success "Node.js dependencies installed"
    fi
}

# Build application
build_application() {
    log "Building application..."
    
    # Build frontend
    if [ -f "frontend/package.json" ]; then
        log "Building frontend..."
        docker-compose run --rm frontend npm run build
        success "Frontend built"
    fi
    
    # Build Docker images
    log "Building Docker images..."
    docker-compose build --no-cache
    success "Docker images built"
}

# Run database migrations
run_migrations() {
    log "Running database migrations..."
    
    docker-compose run --rm app php artisan migrate --force
    success "Database migrations completed"
}

# Clear caches
clear_caches() {
    log "Clearing caches..."
    
    docker-compose run --rm app php artisan cache:clear
    docker-compose run --rm app php artisan config:clear
    docker-compose run --rm app php artisan route:clear
    docker-compose run --rm app php artisan view:clear
    
    success "Caches cleared"
}

# Optimize application
optimize_application() {
    log "Optimizing application..."
    
    docker-compose run --rm app php artisan config:cache
    docker-compose run --rm app php artisan route:cache
    docker-compose run --rm app php artisan view:cache
    
    success "Application optimized"
}

# Start services
start_services() {
    log "Starting services..."
    
    docker-compose up -d
    success "Services started"
}

# Health check
health_check() {
    log "Performing health check..."
    
    # Wait for services to be ready
    sleep 30
    
    # Check if web server is responding
    if curl -f http://localhost/health > /dev/null 2>&1; then
        success "Web server is healthy"
    else
        error "Web server health check failed"
    fi
    
    # Check if database is accessible
    if docker-compose exec -T db mysqladmin ping -h localhost --silent; then
        success "Database is healthy"
    else
        error "Database health check failed"
    fi
    
    # Check if Redis is accessible
    if docker-compose exec -T redis redis-cli ping | grep -q "PONG"; then
        success "Redis is healthy"
    else
        error "Redis health check failed"
    fi
}

# Main deployment function
deploy() {
    log "Starting deployment of $PROJECT_NAME..."
    
    # Check if .env file exists
    if [ ! -f "$ENV_FILE" ]; then
        error ".env file not found. Please copy env.example to .env and configure it."
    fi
    
    # Load environment variables
    source "$ENV_FILE"
    
    # Run deployment steps
    check_requirements
    create_backup
    pull_changes
    install_dependencies
    build_application
    run_migrations
    clear_caches
    optimize_application
    start_services
    health_check
    
    success "Deployment completed successfully!"
    log "Dashboard is available at: http://localhost"
}

# Rollback function
rollback() {
    log "Starting rollback..."
    
    if [ ! -d "$BACKUP_DIR" ]; then
        error "No backups found"
    fi
    
    # Get latest backup
    LATEST_BACKUP=$(ls -t "$BACKUP_DIR" | head -n 1)
    BACKUP_PATH="$BACKUP_DIR/$LATEST_BACKUP"
    
    if [ ! -d "$BACKUP_PATH" ]; then
        error "Latest backup not found"
    fi
    
    log "Rolling back to: $LATEST_BACKUP"
    
    # Stop services
    docker-compose down
    
    # Restore application files
    if [ -f "$BACKUP_PATH/application.tar.gz" ]; then
        log "Restoring application files..."
        tar -xzf "$BACKUP_PATH/application.tar.gz"
        success "Application files restored"
    fi
    
    # Restore database
    if [ -f "$BACKUP_PATH/database.sql" ]; then
        log "Restoring database..."
        docker-compose up -d db
        sleep 10
        docker-compose exec -T db mysql -u root -p${DB_ROOT_PASSWORD:-root_password} < "$BACKUP_PATH/database.sql"
        success "Database restored"
    fi
    
    # Start services
    docker-compose up -d
    
    success "Rollback completed successfully!"
}

# Show usage
usage() {
    echo "Usage: $0 [deploy|rollback|status|logs]"
    echo ""
    echo "Commands:"
    echo "  deploy   - Deploy the application"
    echo "  rollback - Rollback to previous version"
    echo "  status   - Show service status"
    echo "  logs     - Show service logs"
    echo ""
}

# Show status
show_status() {
    log "Service Status:"
    docker-compose ps
}

# Show logs
show_logs() {
    log "Service Logs:"
    docker-compose logs --tail=50
}

# Main script logic
case "${1:-deploy}" in
    deploy)
        deploy
        ;;
    rollback)
        rollback
        ;;
    status)
        show_status
        ;;
    logs)
        show_logs
        ;;
    *)
        usage
        exit 1
        ;;
esac
