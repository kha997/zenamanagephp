#!/bin/bash

# Start Queue Workers Script
# This script starts queue workers for production

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_PATH=$(pwd)
WORKERS_PER_QUEUE=2
TIMEOUT=60
TRIES=3
MAX_JOBS=1000
MAX_TIME=3600
SLEEP=3

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

log "🚀 Starting ZenaManage Queue Workers"
log "===================================="

# Check if Redis is running
log "Checking Redis connection..."
if ! redis-cli ping > /dev/null 2>&1; then
    error "Redis is not running. Please start Redis first."
fi
success "Redis is running"

# Check if we're in the right directory
if [[ ! -f "artisan" ]]; then
    error "Not in Laravel project directory. Please run from project root."
fi

# Create log directory
mkdir -p storage/logs

# Start workers for each queue
log "Starting workers for each queue..."

# High priority emails
log "Starting high priority email workers..."
for i in $(seq 1 $WORKERS_PER_QUEUE); do
    nohup php artisan queue:work redis --queue=emails-high --sleep=$SLEEP --tries=$TRIES --max-time=$MAX_TIME --max-jobs=$MAX_JOBS > storage/logs/worker-emails-high-$i.log 2>&1 &
    WORKER_PID=$!
    echo $WORKER_PID > storage/logs/worker-emails-high-$i.pid
    log "  Worker $i: PID $WORKER_PID"
done

# Medium priority emails
log "Starting medium priority email workers..."
for i in $(seq 1 $WORKERS_PER_QUEUE); do
    nohup php artisan queue:work redis --queue=emails-medium --sleep=$SLEEP --tries=$TRIES --max-time=$MAX_TIME --max-jobs=$MAX_JOBS > storage/logs/worker-emails-medium-$i.log 2>&1 &
    WORKER_PID=$!
    echo $WORKER_PID > storage/logs/worker-emails-medium-$i.pid
    log "  Worker $i: PID $WORKER_PID"
done

# Low priority emails
log "Starting low priority email workers..."
for i in $(seq 1 $WORKERS_PER_QUEUE); do
    nohup php artisan queue:work redis --queue=emails-low --sleep=$SLEEP --tries=$TRIES --max-time=$MAX_TIME --max-jobs=$MAX_JOBS > storage/logs/worker-emails-low-$i.log 2>&1 &
    WORKER_PID=$!
    echo $WORKER_PID > storage/logs/worker-emails-low-$i.pid
    log "  Worker $i: PID $WORKER_PID"
done

# Welcome emails
log "Starting welcome email workers..."
for i in $(seq 1 $WORKERS_PER_QUEUE); do
    nohup php artisan queue:work redis --queue=emails-welcome --sleep=$SLEEP --tries=$TRIES --max-time=$MAX_TIME --max-jobs=$MAX_JOBS > storage/logs/worker-emails-welcome-$i.log 2>&1 &
    WORKER_PID=$!
    echo $WORKER_PID > storage/logs/worker-emails-welcome-$i.pid
    log "  Worker $i: PID $WORKER_PID"
done

# Wait a moment for workers to start
sleep 2

# Check worker status
log "Checking worker status..."
php artisan workers:status

log "Queue Workers Started Successfully!"
log "=================================="
log "Total workers started: $((WORKERS_PER_QUEUE * 4))"
log "Workers per queue: $WORKERS_PER_QUEUE"
log "Log files: storage/logs/worker-*.log"
log "PID files: storage/logs/worker-*.pid"
log ""
log "Management Commands:"
log "==================="
log "Check status: php artisan workers:status"
log "Stop workers: ./scripts/stop-workers.sh"
log "View logs:    tail -f storage/logs/worker-*.log"
log ""
log "Worker Logs:"
log "============"
log "High Priority:   storage/logs/worker-emails-high-*.log"
log "Medium Priority: storage/logs/worker-emails-medium-*.log"
log "Low Priority:    storage/logs/worker-emails-low-*.log"
log "Welcome Emails:  storage/logs/worker-emails-welcome-*.log"

success "All queue workers started successfully!"
