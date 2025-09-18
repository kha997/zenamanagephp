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
