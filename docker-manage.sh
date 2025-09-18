#!/bin/bash

# Docker Management Script
# Dashboard System - Container Management

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="zenamanage-dashboard"
DOCKER_COMPOSE_FILE="docker-compose.prod.yml"

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

# Show help
show_help() {
    echo "Docker Management Script for ZenaManage Dashboard"
    echo ""
    echo "Usage: $0 [COMMAND]"
    echo ""
    echo "Commands:"
    echo "  start       Start all services"
    echo "  stop        Stop all services"
    echo "  restart     Restart all services"
    echo "  status      Show status of all services"
    echo "  logs        Show logs for all services"
    echo "  logs [svc]  Show logs for specific service"
    echo "  shell [svc] Open shell in specific service"
    echo "  build       Build all images"
    echo "  rebuild     Rebuild and restart all services"
    echo "  clean       Clean up unused containers and images"
    echo "  backup      Create backup of database and files"
    echo "  restore     Restore from backup"
    echo "  update      Update and restart services"
    echo "  health      Check health of all services"
    echo "  scale [svc] [count] Scale specific service"
    echo "  help        Show this help message"
    echo ""
    echo "Services:"
    echo "  app, nginx, mysql, redis, queue, scheduler, websocket, prometheus, grafana, elasticsearch, kibana, backup"
}

# Start services
start_services() {
    log_info "Starting all services..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" up -d
    log_success "All services started"
}

# Stop services
stop_services() {
    log_info "Stopping all services..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" down
    log_success "All services stopped"
}

# Restart services
restart_services() {
    log_info "Restarting all services..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" restart
    log_success "All services restarted"
}

# Show status
show_status() {
    log_info "Service Status:"
    docker-compose -f "$DOCKER_COMPOSE_FILE" ps
}

# Show logs
show_logs() {
    local service="$1"
    if [ -n "$service" ]; then
        log_info "Showing logs for $service..."
        docker-compose -f "$DOCKER_COMPOSE_FILE" logs -f "$service"
    else
        log_info "Showing logs for all services..."
        docker-compose -f "$DOCKER_COMPOSE_FILE" logs -f
    fi
}

# Open shell
open_shell() {
    local service="$1"
    if [ -z "$service" ]; then
        log_error "Please specify a service name"
        echo "Available services: app, nginx, mysql, redis, queue, scheduler, websocket"
        exit 1
    fi
    
    log_info "Opening shell in $service..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec "$service" /bin/sh
}

# Build images
build_images() {
    log_info "Building all images..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" build --no-cache
    log_success "All images built"
}

# Rebuild and restart
rebuild_services() {
    log_info "Rebuilding and restarting all services..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" down
    docker-compose -f "$DOCKER_COMPOSE_FILE" build --no-cache
    docker-compose -f "$DOCKER_COMPOSE_FILE" up -d
    log_success "All services rebuilt and restarted"
}

# Clean up
clean_up() {
    log_info "Cleaning up unused containers and images..."
    
    # Remove stopped containers
    docker container prune -f
    
    # Remove unused images
    docker image prune -f
    
    # Remove unused volumes
    docker volume prune -f
    
    # Remove unused networks
    docker network prune -f
    
    log_success "Cleanup completed"
}

# Create backup
create_backup() {
    local backup_dir="backups/$(date +%Y%m%d_%H%M%S)"
    log_info "Creating backup in $backup_dir..."
    
    mkdir -p "$backup_dir"
    
    # Backup database
    log_info "Backing up database..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec mysql mysqldump -u root -p"${MYSQL_ROOT_PASSWORD:-root_password}" --all-databases > "$backup_dir/database.sql"
    
    # Backup application files
    log_info "Backing up application files..."
    tar -czf "$backup_dir/storage.tar.gz" storage/
    tar -czf "$backup_dir/public.tar.gz" public/
    
    # Backup configuration
    log_info "Backing up configuration..."
    cp production.env "$backup_dir/"
    cp "$DOCKER_COMPOSE_FILE" "$backup_dir/"
    
    log_success "Backup created in $backup_dir"
}

# Restore from backup
restore_backup() {
    local backup_dir="$1"
    if [ -z "$backup_dir" ]; then
        log_error "Please specify backup directory"
        echo "Available backups:"
        ls -la backups/
        exit 1
    fi
    
    if [ ! -d "$backup_dir" ]; then
        log_error "Backup directory $backup_dir not found"
        exit 1
    fi
    
    log_warning "This will restore from backup $backup_dir. Are you sure? (y/N)"
    read -r response
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        log_info "Restore cancelled"
        exit 0
    fi
    
    log_info "Restoring from backup $backup_dir..."
    
    # Stop services
    docker-compose -f "$DOCKER_COMPOSE_FILE" down
    
    # Restore database
    if [ -f "$backup_dir/database.sql" ]; then
        log_info "Restoring database..."
        docker-compose -f "$DOCKER_COMPOSE_FILE" up -d mysql
        sleep 10
        docker-compose -f "$DOCKER_COMPOSE_FILE" exec mysql mysql -u root -p"${MYSQL_ROOT_PASSWORD:-root_password}" < "$backup_dir/database.sql"
    fi
    
    # Restore files
    if [ -f "$backup_dir/storage.tar.gz" ]; then
        log_info "Restoring storage files..."
        tar -xzf "$backup_dir/storage.tar.gz"
    fi
    
    if [ -f "$backup_dir/public.tar.gz" ]; then
        log_info "Restoring public files..."
        tar -xzf "$backup_dir/public.tar.gz"
    fi
    
    # Restart services
    docker-compose -f "$DOCKER_COMPOSE_FILE" up -d
    
    log_success "Restore completed"
}

# Update services
update_services() {
    log_info "Updating services..."
    
    # Pull latest images
    docker-compose -f "$DOCKER_COMPOSE_FILE" pull
    
    # Rebuild and restart
    rebuild_services
    
    log_success "Services updated"
}

# Health check
health_check() {
    log_info "Checking health of all services..."
    
    # Check each service
    services=("app" "nginx" "mysql" "redis" "queue" "scheduler" "websocket" "prometheus" "grafana" "elasticsearch" "kibana")
    
    for service in "${services[@]}"; do
        if docker-compose -f "$DOCKER_COMPOSE_FILE" ps "$service" | grep -q "Up"; then
            log_success "$service: Healthy"
        else
            log_error "$service: Unhealthy"
        fi
    done
}

# Scale service
scale_service() {
    local service="$1"
    local count="$2"
    
    if [ -z "$service" ] || [ -z "$count" ]; then
        log_error "Please specify service name and count"
        echo "Usage: $0 scale [service] [count]"
        exit 1
    fi
    
    log_info "Scaling $service to $count instances..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" up -d --scale "$service=$count"
    log_success "$service scaled to $count instances"
}

# Main function
main() {
    local command="$1"
    local arg1="$2"
    local arg2="$3"
    
    case "$command" in
        "start")
            start_services
            ;;
        "stop")
            stop_services
            ;;
        "restart")
            restart_services
            ;;
        "status")
            show_status
            ;;
        "logs")
            show_logs "$arg1"
            ;;
        "shell")
            open_shell "$arg1"
            ;;
        "build")
            build_images
            ;;
        "rebuild")
            rebuild_services
            ;;
        "clean")
            clean_up
            ;;
        "backup")
            create_backup
            ;;
        "restore")
            restore_backup "$arg1"
            ;;
        "update")
            update_services
            ;;
        "health")
            health_check
            ;;
        "scale")
            scale_service "$arg1" "$arg2"
            ;;
        "help"|"--help"|"-h"|"")
            show_help
            ;;
        *)
            log_error "Unknown command: $command"
            show_help
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
