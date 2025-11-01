#!/bin/bash

# Production Monitoring Management Script
# Dashboard System - Monitoring and Alerting Management

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

# Check monitoring services status
check_monitoring_status() {
    log_info "Checking monitoring services status..."
    
    # Check Prometheus
    if curl -f http://localhost:9090/-/healthy > /dev/null 2>&1; then
        log_success "Prometheus is running"
    else
        log_error "Prometheus is not accessible"
    fi
    
    # Check Grafana
    if curl -f http://localhost:3000/api/health > /dev/null 2>&1; then
        log_success "Grafana is running"
    else
        log_error "Grafana is not accessible"
    fi
    
    # Check Alertmanager
    if curl -f http://localhost:9093/-/healthy > /dev/null 2>&1; then
        log_success "Alertmanager is running"
    else
        log_error "Alertmanager is not accessible"
    fi
    
    # Check Elasticsearch
    if curl -f http://localhost:9200/_cluster/health > /dev/null 2>&1; then
        log_success "Elasticsearch is running"
    else
        log_error "Elasticsearch is not accessible"
    fi
    
    # Check Kibana
    if curl -f http://localhost:5601/api/status > /dev/null 2>&1; then
        log_success "Kibana is running"
    else
        log_error "Kibana is not accessible"
    fi
}

# Test health check endpoints
test_health_endpoints() {
    log_info "Testing health check endpoints..."
    
    # Test basic health check
    if curl -f http://localhost/health > /dev/null 2>&1; then
        log_success "Basic health check endpoint is working"
    else
        log_error "Basic health check endpoint failed"
    fi
    
    # Test detailed health check
    if curl -f http://localhost/health/detailed > /dev/null 2>&1; then
        log_success "Detailed health check endpoint is working"
    else
        log_error "Detailed health check endpoint failed"
    fi
    
    # Test metrics endpoint
    if curl -f http://localhost/metrics > /dev/null 2>&1; then
        log_success "Metrics endpoint is working"
    else
        log_error "Metrics endpoint failed"
    fi
    
    # Test API health check
    if curl -f http://localhost/api/health > /dev/null 2>&1; then
        log_success "API health check endpoint is working"
    else
        log_error "API health check endpoint failed"
    fi
}

# View Prometheus targets
view_prometheus_targets() {
    log_info "Viewing Prometheus targets..."
    
    if command -v curl &> /dev/null; then
        curl -s http://localhost:9090/api/v1/targets | jq '.data.activeTargets[] | {job: .labels.job, instance: .labels.instance, health: .health, lastScrape: .lastScrape}'
    else
        log_error "curl is not installed"
    fi
}

# View Prometheus alerts
view_prometheus_alerts() {
    log_info "Viewing Prometheus alerts..."
    
    if command -v curl &> /dev/null; then
        curl -s http://localhost:9090/api/v1/alerts | jq '.data.alerts[] | {alertname: .labels.alertname, state: .state, severity: .labels.severity}'
    else
        log_error "curl is not installed"
    fi
}

# View Grafana dashboards
view_grafana_dashboards() {
    log_info "Viewing Grafana dashboards..."
    
    if command -v curl &> /dev/null; then
        curl -s -u admin:admin http://localhost:3000/api/search?type=dash-db | jq '.[] | {title: .title, uid: .uid, url: .url}'
    else
        log_error "curl is not installed"
    fi
}

# Test alerting
test_alerting() {
    log_info "Testing alerting system..."
    
    # Test Slack webhook (if configured)
    if [ -n "$SLACK_WEBHOOK_URL" ]; then
        curl -X POST -H 'Content-type: application/json' \
            --data '{"text":"Test alert from ZenaManage Dashboard monitoring system"}' \
            "$SLACK_WEBHOOK_URL"
        log_success "Slack test message sent"
    else
        log_warning "SLACK_WEBHOOK_URL not configured"
    fi
    
    # Test email alerting (if configured)
    if [ -n "$SMTP_HOST" ]; then
        log_info "Email alerting is configured"
    else
        log_warning "Email alerting not configured"
    fi
}

# View system metrics
view_system_metrics() {
    log_info "Viewing system metrics..."
    
    # Get basic system info
    echo "System Information:"
    echo "==================="
    echo "Hostname: $(hostname)"
    echo "Uptime: $(uptime)"
    echo "Load Average: $(cat /proc/loadavg 2>/dev/null || echo 'N/A')"
    echo "Memory Usage: $(free -h | grep '^Mem:' | awk '{print $3 "/" $2}')"
    echo "Disk Usage: $(df -h / | tail -1 | awk '{print $3 "/" $2 " (" $5 ")"}')"
    echo ""
    
    # Get Docker stats
    if command -v docker &> /dev/null; then
        echo "Docker Container Stats:"
        echo "======================"
        docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}\t{{.BlockIO}}"
        echo ""
    fi
}

# View application metrics
view_application_metrics() {
    log_info "Viewing application metrics..."
    
    # Get application health
    echo "Application Health:"
    echo "==================="
    curl -s http://localhost/health/detailed | jq '.'
    echo ""
    
    # Get metrics summary
    echo "Metrics Summary:"
    echo "================"
    curl -s http://localhost/api/metrics/summary | jq '.'
    echo ""
}

# View logs
view_logs() {
    local service="$1"
    local lines="${2:-100}"
    
    if [ -z "$service" ]; then
        log_info "Available services:"
        docker-compose -f "$DOCKER_COMPOSE_FILE" ps --services
        echo ""
        read -p "Enter service name: " service
    fi
    
    if [ -z "$service" ]; then
        log_error "Service name is required"
        return 1
    fi
    
    log_info "Viewing logs for $service (last $lines lines)..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" logs --tail="$lines" -f "$service"
}

# Setup monitoring
setup_monitoring() {
    log_info "Setting up monitoring system..."
    
    # Start monitoring services
    docker-compose -f "$DOCKER_COMPOSE_FILE" up -d prometheus grafana alertmanager elasticsearch kibana
    
    # Wait for services to start
    sleep 30
    
    # Check if services are running
    check_monitoring_status
    
    # Test health endpoints
    test_health_endpoints
    
    log_success "Monitoring system setup completed"
}

# Configure Grafana
configure_grafana() {
    log_info "Configuring Grafana..."
    
    # Wait for Grafana to start
    sleep 10
    
    # Create datasource
    curl -X POST -H "Content-Type: application/json" \
        -u admin:admin \
        -d '{
            "name": "Prometheus",
            "type": "prometheus",
            "url": "http://prometheus:9090",
            "access": "proxy",
            "isDefault": true
        }' \
        http://localhost:3000/api/datasources
    
    log_success "Grafana datasource configured"
}

# Configure Alertmanager
configure_alertmanager() {
    log_info "Configuring Alertmanager..."
    
    # Wait for Alertmanager to start
    sleep 10
    
    # Test Alertmanager configuration
    curl -X POST http://localhost:9093/api/v1/alerts \
        -H "Content-Type: application/json" \
        -d '[{
            "labels": {
                "alertname": "TestAlert",
                "severity": "warning"
            },
            "annotations": {
                "summary": "Test alert from monitoring system"
            }
        }]'
    
    log_success "Alertmanager test alert sent"
}

# Backup monitoring data
backup_monitoring_data() {
    local backup_dir="monitoring-backups/$(date +%Y%m%d_%H%M%S)"
    
    log_info "Creating monitoring data backup in $backup_dir..."
    
    mkdir -p "$backup_dir"
    
    # Backup Prometheus data
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec prometheus tar -czf /tmp/prometheus-data.tar.gz /prometheus
    docker cp "$(docker-compose -f "$DOCKER_COMPOSE_FILE" ps -q prometheus):/tmp/prometheus-data.tar.gz" "$backup_dir/"
    
    # Backup Grafana data
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec grafana tar -czf /tmp/grafana-data.tar.gz /var/lib/grafana
    docker cp "$(docker-compose -f "$DOCKER_COMPOSE_FILE" ps -q grafana):/tmp/grafana-data.tar.gz" "$backup_dir/"
    
    # Backup Elasticsearch data
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec elasticsearch tar -czf /tmp/elasticsearch-data.tar.gz /usr/share/elasticsearch/data
    docker cp "$(docker-compose -f "$DOCKER_COMPOSE_FILE" ps -q elasticsearch):/tmp/elasticsearch-data.tar.gz" "$backup_dir/"
    
    log_success "Monitoring data backup created in $backup_dir"
}

# Restore monitoring data
restore_monitoring_data() {
    local backup_dir="$1"
    
    if [ -z "$backup_dir" ]; then
        log_error "Please specify backup directory"
        echo "Available backups:"
        ls -la monitoring-backups/
        exit 1
    fi
    
    if [ ! -d "$backup_dir" ]; then
        log_error "Backup directory $backup_dir not found"
        exit 1
    fi
    
    log_warning "This will restore monitoring data from $backup_dir. Are you sure? (y/N)"
    read -r response
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        log_info "Restore cancelled"
        exit 0
    fi
    
    log_info "Restoring monitoring data from $backup_dir..."
    
    # Stop monitoring services
    docker-compose -f "$DOCKER_COMPOSE_FILE" stop prometheus grafana elasticsearch
    
    # Restore Prometheus data
    if [ -f "$backup_dir/prometheus-data.tar.gz" ]; then
        docker cp "$backup_dir/prometheus-data.tar.gz" "$(docker-compose -f "$DOCKER_COMPOSE_FILE" ps -q prometheus):/tmp/"
        docker-compose -f "$DOCKER_COMPOSE_FILE" exec prometheus tar -xzf /tmp/prometheus-data.tar.gz -C /
    fi
    
    # Restore Grafana data
    if [ -f "$backup_dir/grafana-data.tar.gz" ]; then
        docker cp "$backup_dir/grafana-data.tar.gz" "$(docker-compose -f "$DOCKER_COMPOSE_FILE" ps -q grafana):/tmp/"
        docker-compose -f "$DOCKER_COMPOSE_FILE" exec grafana tar -xzf /tmp/grafana-data.tar.gz -C /
    fi
    
    # Restore Elasticsearch data
    if [ -f "$backup_dir/elasticsearch-data.tar.gz" ]; then
        docker cp "$backup_dir/elasticsearch-data.tar.gz" "$(docker-compose -f "$DOCKER_COMPOSE_FILE" ps -q elasticsearch):/tmp/"
        docker-compose -f "$DOCKER_COMPOSE_FILE" exec elasticsearch tar -xzf /tmp/elasticsearch-data.tar.gz -C /
    fi
    
    # Restart monitoring services
    docker-compose -f "$DOCKER_COMPOSE_FILE" start prometheus grafana elasticsearch
    
    log_success "Monitoring data restored from $backup_dir"
}

# Show help
show_help() {
    echo "Production Monitoring Management Script for ZenaManage Dashboard"
    echo ""
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  status                    Check monitoring services status"
    echo "  test-health               Test health check endpoints"
    echo "  prometheus-targets        View Prometheus targets"
    echo "  prometheus-alerts         View Prometheus alerts"
    echo "  grafana-dashboards        View Grafana dashboards"
    echo "  test-alerts               Test alerting system"
    echo "  system-metrics            View system metrics"
    echo "  app-metrics               View application metrics"
    echo "  logs [service] [lines]    View service logs"
    echo "  setup                     Setup monitoring system"
    echo "  configure-grafana         Configure Grafana"
    echo "  configure-alertmanager    Configure Alertmanager"
    echo "  backup                    Backup monitoring data"
    echo "  restore [backup_dir]      Restore monitoring data"
    echo "  help                      Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 status"
    echo "  $0 test-health"
    echo "  $0 logs app 200"
    echo "  $0 backup"
    echo "  $0 restore monitoring-backups/20240101_120000"
    echo ""
    echo "Access URLs:"
    echo "  Prometheus: http://localhost:9090"
    echo "  Grafana: http://localhost:3000 (admin/admin)"
    echo "  Alertmanager: http://localhost:9093"
    echo "  Kibana: http://localhost:5601"
    echo "  Elasticsearch: http://localhost:9200"
}

# Main function
main() {
    local command="$1"
    local arg1="$2"
    local arg2="$3"
    
    case "$command" in
        "status")
            check_monitoring_status
            ;;
        "test-health")
            test_health_endpoints
            ;;
        "prometheus-targets")
            view_prometheus_targets
            ;;
        "prometheus-alerts")
            view_prometheus_alerts
            ;;
        "grafana-dashboards")
            view_grafana_dashboards
            ;;
        "test-alerts")
            test_alerting
            ;;
        "system-metrics")
            view_system_metrics
            ;;
        "app-metrics")
            view_application_metrics
            ;;
        "logs")
            view_logs "$arg1" "$arg2"
            ;;
        "setup")
            setup_monitoring
            ;;
        "configure-grafana")
            configure_grafana
            ;;
        "configure-alertmanager")
            configure_alertmanager
            ;;
        "backup")
            backup_monitoring_data
            ;;
        "restore")
            restore_monitoring_data "$arg1"
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
