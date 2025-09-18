#!/bin/bash

# Supervisor Setup Script for ZenaManage
# This script sets up supervisor for queue workers

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="zenamanage"
PROJECT_PATH=$(pwd)
SUPERVISOR_CONFIG_DIR="/etc/supervisor/conf.d"
SUPERVISOR_CONFIG_FILE="$SUPERVISOR_CONFIG_DIR/${PROJECT_NAME}-workers.conf"

# Functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
    exit 1
}

log "👷 Setting up Supervisor for Queue Workers"
log "=========================================="

# Check if supervisor is installed
if ! command -v supervisorctl &> /dev/null; then
    log "Installing supervisor..."
    sudo apt update
    sudo apt install -y supervisor
    success "Supervisor installed"
else
    log "Supervisor is already installed"
fi

# Create supervisor configuration
log "Creating supervisor configuration..."

sudo tee "$SUPERVISOR_CONFIG_FILE" > /dev/null << EOF
[group:${PROJECT_NAME}-workers]
programs=${PROJECT_NAME}-emails-high,${PROJECT_NAME}-emails-medium,${PROJECT_NAME}-emails-low,${PROJECT_NAME}-emails-welcome

[program:${PROJECT_NAME}-emails-high]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_PATH/artisan queue:work redis --queue=emails-high --sleep=3 --tries=3 --max-time=3600 --max-jobs=1000
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=$PROJECT_PATH/storage/logs/worker-emails-high.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600

[program:${PROJECT_NAME}-emails-medium]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_PATH/artisan queue:work redis --queue=emails-medium --sleep=3 --tries=3 --max-time=3600 --max-jobs=1000
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=$PROJECT_PATH/storage/logs/worker-emails-medium.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600

[program:${PROJECT_NAME}-emails-low]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_PATH/artisan queue:work redis --queue=emails-low --sleep=3 --tries=3 --max-time=3600 --max-jobs=1000
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_PATH/storage/logs/worker-emails-low.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600

[program:${PROJECT_NAME}-emails-welcome]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_PATH/artisan queue:work redis --queue=emails-welcome --sleep=3 --tries=3 --max-time=3600 --max-jobs=1000
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_PATH/storage/logs/worker-emails-welcome.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600
EOF

success "Supervisor configuration created: $SUPERVISOR_CONFIG_FILE"

# Create log directories
log "Creating log directories..."
sudo mkdir -p "$PROJECT_PATH/storage/logs"
sudo chown -R www-data:www-data "$PROJECT_PATH/storage/logs"
success "Log directories created"

# Reload supervisor configuration
log "Reloading supervisor configuration..."
sudo supervisorctl reread
sudo supervisorctl update
success "Supervisor configuration reloaded"

# Start workers
log "Starting queue workers..."
sudo supervisorctl start "${PROJECT_NAME}-workers:*"
success "Queue workers started"

# Check worker status
log "Checking worker status..."
sudo supervisorctl status "${PROJECT_NAME}-workers:*"

log "Supervisor Setup Summary:"
log "========================"
log "✅ Supervisor installed and configured"
log "✅ Queue workers configured and started"
log "✅ Log files created in: $PROJECT_PATH/storage/logs/"
log "✅ Configuration file: $SUPERVISOR_CONFIG_FILE"
log ""
log "Worker Management Commands:"
log "==========================="
log "Check status:    sudo supervisorctl status ${PROJECT_NAME}-workers:*"
log "Start workers:   sudo supervisorctl start ${PROJECT_NAME}-workers:*"
log "Stop workers:    sudo supervisorctl stop ${PROJECT_NAME}-workers:*"
log "Restart workers: sudo supervisorctl restart ${PROJECT_NAME}-workers:*"
log "Reload config:   sudo supervisorctl reread && sudo supervisorctl update"
log ""
log "Log Files:"
log "=========="
log "High Priority:   $PROJECT_PATH/storage/logs/worker-emails-high.log"
log "Medium Priority: $PROJECT_PATH/storage/logs/worker-emails-medium.log"
log "Low Priority:    $PROJECT_PATH/storage/logs/worker-emails-low.log"
log "Welcome Emails:  $PROJECT_PATH/storage/logs/worker-emails-welcome.log"

success "Supervisor setup completed successfully!"
