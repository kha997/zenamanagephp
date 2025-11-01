#!/bin/bash

# Production Queue Workers Startup Script
# This script starts queue workers for production environment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_PATH=$(pwd)
WORKERS_PER_QUEUE=3
TIMEOUT=60
TRIES=3
MAX_JOBS=1000
MAX_TIME=3600
SLEEP=3
LOG_DIR="storage/logs"

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

log "🚀 Starting Production Queue Workers"
log "===================================="

# Check if we're in the right directory
if [[ ! -f "artisan" ]]; then
    error "Not in Laravel project directory. Please run from project root."
fi

# Check Redis connection
log "Checking Redis connection..."
if ! redis-cli ping > /dev/null 2>&1; then
    error "Redis is not running. Please start Redis first."
fi
success "Redis is running"

# Create log directory
mkdir -p "$LOG_DIR"

# Check if workers are already running
if pgrep -f "queue:work" > /dev/null; then
    warning "Queue workers are already running. Stopping existing workers..."
    pkill -f "queue:work" || true
    sleep 2
fi

# Start workers for each queue
log "Starting production queue workers..."

# High priority emails (invitations)
log "Starting high priority email workers..."
for i in $(seq 1 $WORKERS_PER_QUEUE); do
    nohup php artisan queue:work database --queue=emails-high --sleep=$SLEEP --tries=$TRIES --max-time=$MAX_TIME --max-jobs=$MAX_JOBS > "$LOG_DIR/worker-emails-high-$i.log" 2>&1 &
    WORKER_PID=$!
    echo $WORKER_PID > "$LOG_DIR/worker-emails-high-$i.pid"
    log "  Worker $i: PID $WORKER_PID"
done

# Medium priority emails
log "Starting medium priority email workers..."
for i in $(seq 1 $WORKERS_PER_QUEUE); do
    nohup php artisan queue:work database --queue=emails-medium --sleep=$SLEEP --tries=$TRIES --max-time=$MAX_TIME --max-jobs=$MAX_JOBS > "$LOG_DIR/worker-emails-medium-$i.log" 2>&1 &
    WORKER_PID=$!
    echo $WORKER_PID > "$LOG_DIR/worker-emails-medium-$i.pid"
    log "  Worker $i: PID $WORKER_PID"
done

# Low priority emails
log "Starting low priority email workers..."
for i in $(seq 1 $WORKERS_PER_QUEUE); do
    nohup php artisan queue:work database --queue=emails-low --sleep=$SLEEP --tries=$TRIES --max-time=$MAX_TIME --max-jobs=$MAX_JOBS > "$LOG_DIR/worker-emails-low-$i.log" 2>&1 &
    WORKER_PID=$!
    echo $WORKER_PID > "$LOG_DIR/worker-emails-low-$i.pid"
    log "  Worker $i: PID $WORKER_PID"
done

# Welcome emails
log "Starting welcome email workers..."
for i in $(seq 1 $WORKERS_PER_QUEUE); do
    nohup php artisan queue:work database --queue=emails-welcome --sleep=$SLEEP --tries=$TRIES --max-time=$MAX_TIME --max-jobs=$MAX_JOBS > "$LOG_DIR/worker-emails-welcome-$i.log" 2>&1 &
    WORKER_PID=$!
    echo $WORKER_PID > "$LOG_DIR/worker-emails-welcome-$i.pid"
    log "  Worker $i: PID $WORKER_PID"
done

# Wait for workers to start
sleep 3

# Check worker status
log "Checking worker status..."
php artisan workers:status

# Create worker management script
log "Creating worker management script..."
cat > scripts/manage-workers.sh << 'EOF'
#!/bin/bash

# Worker Management Script
# Usage: ./scripts/manage-workers.sh [start|stop|restart|status]

ACTION=${1:-status}
PROJECT_PATH=$(pwd)
LOG_DIR="storage/logs"

case $ACTION in
    start)
        echo "Starting queue workers..."
        ./scripts/start-production-workers.sh
        ;;
    stop)
        echo "Stopping queue workers..."
        pkill -f "queue:work" || true
        echo "Workers stopped"
        ;;
    restart)
        echo "Restarting queue workers..."
        pkill -f "queue:work" || true
        sleep 2
        ./scripts/start-production-workers.sh
        ;;
    status)
        echo "Queue Worker Status:"
        php artisan workers:status
        echo ""
        echo "Running Processes:"
        pgrep -f "queue:work" | wc -l | xargs echo "Active Workers:"
        ;;
    *)
        echo "Usage: $0 [start|stop|restart|status]"
        exit 1
        ;;
esac
EOF

chmod +x scripts/manage-workers.sh
success "Worker management script created"

# Create supervisor configuration
log "Creating supervisor configuration..."
cat > scripts/supervisor-config.conf << EOF
[group:zenamanage-workers]
programs=zenamanage-emails-high,zenamanage-emails-medium,zenamanage-emails-low,zenamanage-emails-welcome

[program:zenamanage-emails-high]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_PATH/artisan queue:work database --queue=emails-high --sleep=$SLEEP --tries=$TRIES --max-time=$MAX_TIME --max-jobs=$MAX_JOBS
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=$WORKERS_PER_QUEUE
redirect_stderr=true
stdout_logfile=$PROJECT_PATH/$LOG_DIR/worker-emails-high.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600

[program:zenamanage-emails-medium]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_PATH/artisan queue:work database --queue=emails-medium --sleep=$SLEEP --tries=$TRIES --max-time=$MAX_TIME --max-jobs=$MAX_JOBS
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=$WORKERS_PER_QUEUE
redirect_stderr=true
stdout_logfile=$PROJECT_PATH/$LOG_DIR/worker-emails-medium.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600

[program:zenamanage-emails-low]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_PATH/artisan queue:work database --queue=emails-low --sleep=$SLEEP --tries=$TRIES --max-time=$MAX_TIME --max-jobs=$MAX_JOBS
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=$WORKERS_PER_QUEUE
redirect_stderr=true
stdout_logfile=$PROJECT_PATH/$LOG_DIR/worker-emails-low.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600

[program:zenamanage-emails-welcome]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_PATH/artisan queue:work database --queue=emails-welcome --sleep=$SLEEP --tries=$TRIES --max-time=$MAX_TIME --max-jobs=$MAX_JOBS
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=$WORKERS_PER_QUEUE
redirect_stderr=true
stdout_logfile=$PROJECT_PATH/$LOG_DIR/worker-emails-welcome.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600
EOF

success "Supervisor configuration created"

log "Production Queue Workers Started Successfully!"
log "=============================================="
log "Total workers started: $((WORKERS_PER_QUEUE * 4))"
log "Workers per queue: $WORKERS_PER_QUEUE"
log "Log files: $LOG_DIR/worker-*.log"
log "PID files: $LOG_DIR/worker-*.pid"
log ""
log "Management Commands:"
log "==================="
log "Start workers:   ./scripts/manage-workers.sh start"
log "Stop workers:    ./scripts/manage-workers.sh stop"
log "Restart workers: ./scripts/manage-workers.sh restart"
log "Check status:    ./scripts/manage-workers.sh status"
log ""
log "Supervisor Setup:"
log "================="
log "1. Install supervisor: sudo apt install supervisor"
log "2. Copy config: sudo cp scripts/supervisor-config.conf /etc/supervisor/conf.d/"
log "3. Reload config: sudo supervisorctl reread && sudo supervisorctl update"
log "4. Start workers: sudo supervisorctl start zenamanage-workers:*"
log ""
log "Worker Logs:"
log "============"
log "High Priority:   $LOG_DIR/worker-emails-high-*.log"
log "Medium Priority: $LOG_DIR/worker-emails-medium-*.log"
log "Low Priority:    $LOG_DIR/worker-emails-low-*.log"
log "Welcome Emails:  $LOG_DIR/worker-emails-welcome-*.log"

success "All production queue workers started successfully!"
