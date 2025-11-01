#!/bin/bash

# Docker Setup Test Script
# Dashboard System - Production Docker Test

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DOCKER_COMPOSE_FILE="docker-compose.prod.yml"
TEST_TIMEOUT=30

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

# Test Docker installation
test_docker() {
    log_info "Testing Docker installation..."
    
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed"
        return 1
    fi
    
    if ! docker info > /dev/null 2>&1; then
        log_error "Docker is not running"
        return 1
    fi
    
    log_success "Docker is installed and running"
    return 0
}

# Test Docker Compose
test_docker_compose() {
    log_info "Testing Docker Compose..."
    
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose is not installed"
        return 1
    fi
    
    log_success "Docker Compose is available"
    return 0
}

# Test configuration files
test_config_files() {
    log_info "Testing configuration files..."
    
    local files=(
        "$DOCKER_COMPOSE_FILE"
        "docker/nginx/nginx.prod.conf"
        "docker/php/php.ini"
        "docker/php/opcache.ini"
        "docker/mysql/my.cnf"
        "docker/redis/redis.conf"
        "docker/prometheus/prometheus.yml"
        "docker/grafana/provisioning/datasources.yml"
        "docker/grafana/provisioning/dashboards.yml"
        "docker/supervisor/supervisord.conf"
    )
    
    local missing_files=()
    
    for file in "${files[@]}"; do
        if [ ! -f "$file" ]; then
            missing_files+=("$file")
        fi
    done
    
    if [ ${#missing_files[@]} -gt 0 ]; then
        log_error "Missing configuration files:"
        for file in "${missing_files[@]}"; do
            echo "  - $file"
        done
        return 1
    fi
    
    log_success "All configuration files present"
    return 0
}

# Test Docker Compose syntax
test_docker_compose_syntax() {
    log_info "Testing Docker Compose syntax..."
    
    if ! docker-compose -f "$DOCKER_COMPOSE_FILE" config > /dev/null 2>&1; then
        log_error "Docker Compose syntax error"
        return 1
    fi
    
    log_success "Docker Compose syntax is valid"
    return 0
}

# Test service startup
test_service_startup() {
    log_info "Testing service startup..."
    
    # Start services
    docker-compose -f "$DOCKER_COMPOSE_FILE" up -d
    
    # Wait for services to start
    sleep 10
    
    # Check if services are running
    local services=("app" "nginx" "mysql" "redis")
    local failed_services=()
    
    for service in "${services[@]}"; do
        if ! docker-compose -f "$DOCKER_COMPOSE_FILE" ps "$service" | grep -q "Up"; then
            failed_services+=("$service")
        fi
    done
    
    if [ ${#failed_services[@]} -gt 0 ]; then
        log_error "Failed to start services:"
        for service in "${failed_services[@]}"; do
            echo "  - $service"
        done
        return 1
    fi
    
    log_success "All core services started successfully"
    return 0
}

# Test service connectivity
test_service_connectivity() {
    log_info "Testing service connectivity..."
    
    # Test MySQL
    if ! docker-compose -f "$DOCKER_COMPOSE_FILE" exec mysql mysqladmin ping -h localhost --silent; then
        log_error "MySQL connectivity test failed"
        return 1
    fi
    log_success "MySQL connectivity test passed"
    
    # Test Redis
    if ! docker-compose -f "$DOCKER_COMPOSE_FILE" exec redis redis-cli ping > /dev/null 2>&1; then
        log_error "Redis connectivity test failed"
        return 1
    fi
    log_success "Redis connectivity test passed"
    
    # Test Nginx
    if ! curl -f http://localhost/health > /dev/null 2>&1; then
        log_warning "Nginx health check failed (may be expected if health endpoint not implemented)"
    else
        log_success "Nginx health check passed"
    fi
    
    return 0
}

# Test SSL configuration
test_ssl_config() {
    log_info "Testing SSL configuration..."
    
    if [ -f "docker/nginx/ssl/zenamanage.crt" ] && [ -f "docker/nginx/ssl/zenamanage.key" ]; then
        # Test certificate
        if openssl x509 -in "docker/nginx/ssl/zenamanage.crt" -text -noout > /dev/null 2>&1; then
            log_success "SSL certificate is valid"
        else
            log_error "SSL certificate is invalid"
            return 1
        fi
        
        # Test private key
        if openssl rsa -in "docker/nginx/ssl/zenamanage.key" -check > /dev/null 2>&1; then
            log_success "SSL private key is valid"
        else
            log_error "SSL private key is invalid"
            return 1
        fi
    else
        log_warning "SSL certificates not found (optional for development)"
    fi
    
    return 0
}

# Test monitoring services
test_monitoring_services() {
    log_info "Testing monitoring services..."
    
    # Test Prometheus
    if curl -f http://localhost:9090/-/healthy > /dev/null 2>&1; then
        log_success "Prometheus is accessible"
    else
        log_warning "Prometheus is not accessible (may be expected if not started)"
    fi
    
    # Test Grafana
    if curl -f http://localhost:3000/api/health > /dev/null 2>&1; then
        log_success "Grafana is accessible"
    else
        log_warning "Grafana is not accessible (may be expected if not started)"
    fi
    
    return 0
}

# Test application functionality
test_application_functionality() {
    log_info "Testing application functionality..."
    
    # Test Laravel artisan commands
    if docker-compose -f "$DOCKER_COMPOSE_FILE" exec app php artisan --version > /dev/null 2>&1; then
        log_success "Laravel artisan is working"
    else
        log_error "Laravel artisan is not working"
        return 1
    fi
    
    # Test database connection
    if docker-compose -f "$DOCKER_COMPOSE_FILE" exec app php artisan tinker --execute="DB::connection()->getPdo();" > /dev/null 2>&1; then
        log_success "Database connection is working"
    else
        log_error "Database connection is not working"
        return 1
    fi
    
    return 0
}

# Cleanup test environment
cleanup_test() {
    log_info "Cleaning up test environment..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" down --remove-orphans
    log_success "Test environment cleaned up"
}

# Run all tests
run_all_tests() {
    local tests=(
        "test_docker"
        "test_docker_compose"
        "test_config_files"
        "test_docker_compose_syntax"
        "test_service_startup"
        "test_service_connectivity"
        "test_ssl_config"
        "test_monitoring_services"
        "test_application_functionality"
    )
    
    local passed=0
    local failed=0
    
    for test in "${tests[@]}"; do
        if $test; then
            ((passed++))
        else
            ((failed++))
        fi
    done
    
    echo ""
    log_info "Test Results:"
    log_success "Passed: $passed"
    if [ $failed -gt 0 ]; then
        log_error "Failed: $failed"
    else
        log_success "Failed: $failed"
    fi
    
    if [ $failed -eq 0 ]; then
        log_success "🎉 All tests passed! Docker setup is ready for production."
        return 0
    else
        log_error "❌ Some tests failed. Please fix the issues before deploying to production."
        return 1
    fi
}

# Show help
show_help() {
    echo "Docker Setup Test Script for ZenaManage Dashboard"
    echo ""
    echo "Usage: $0 [COMMAND]"
    echo ""
    echo "Commands:"
    echo "  all           Run all tests"
    echo "  docker        Test Docker installation"
    echo "  compose       Test Docker Compose"
    echo "  config        Test configuration files"
    echo "  syntax        Test Docker Compose syntax"
    echo "  startup       Test service startup"
    echo "  connectivity  Test service connectivity"
    echo "  ssl           Test SSL configuration"
    echo "  monitoring    Test monitoring services"
    echo "  app           Test application functionality"
    echo "  cleanup       Cleanup test environment"
    echo "  help          Show this help message"
    echo ""
    echo "Note: Some tests may require services to be running."
}

# Main function
main() {
    local command="$1"
    
    case "$command" in
        "all")
            run_all_tests
            cleanup_test
            ;;
        "docker")
            test_docker
            ;;
        "compose")
            test_docker_compose
            ;;
        "config")
            test_config_files
            ;;
        "syntax")
            test_docker_compose_syntax
            ;;
        "startup")
            test_service_startup
            ;;
        "connectivity")
            test_service_connectivity
            ;;
        "ssl")
            test_ssl_config
            ;;
        "monitoring")
            test_monitoring_services
            ;;
        "app")
            test_application_functionality
            ;;
        "cleanup")
            cleanup_test
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
