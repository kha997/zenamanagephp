#!/bin/bash

# ZenaManage - Stop All Services Script
# This script stops all running services

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if a port is in use
check_port() {
    local port=$1
    if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null 2>&1; then
        return 0  # Port is in use
    else
        return 1  # Port is free
    fi
}

# Function to kill process by port
kill_by_port() {
    local port=$1
    local service_name=$2
    
    if check_port $port; then
        local pid=$(lsof -Pi :$port -sTCP:LISTEN -t)
        if [ ! -z "$pid" ]; then
            print_status "Stopping $service_name (PID: $pid) on port $port..."
            kill $pid
            sleep 2
            
            # Check if process is still running
            if kill -0 $pid 2>/dev/null; then
                print_warning "Force killing $service_name..."
                kill -9 $pid
            fi
            
            print_success "$service_name stopped"
        fi
    else
        print_warning "$service_name is not running on port $port"
    fi
}

# Main stop function
main() {
    echo "🛑 ZenaManage System Shutdown"
    echo "=============================="
    echo ""
    
    # Stop services by port
    print_status "Stopping services..."
    echo ""
    
    # Stop Laravel Backend (port 8000)
    kill_by_port 8000 "Laravel Backend"
    
    # Stop React Frontend (port 3000)
    kill_by_port 3000 "React Frontend"
    
    # Stop WebSocket Server (port 8080)
    kill_by_port 8080 "WebSocket Server"
    
    # Stop Redis (port 6379) - if running as daemon
    if check_port 6379; then
        print_status "Stopping Redis server..."
        redis-cli shutdown 2>/dev/null || {
            local pid=$(lsof -Pi :6379 -sTCP:LISTEN -t)
            if [ ! -z "$pid" ]; then
                kill $pid
                print_success "Redis server stopped"
            fi
        }
    else
        print_warning "Redis server is not running"
    fi
    
    # Clean up PID files
    print_status "Cleaning up PID files..."
    if [ -d "logs" ]; then
        rm -f logs/*.pid
        print_success "PID files cleaned up"
    fi
    
    echo ""
    print_success "🎉 All services stopped successfully!"
    echo ""
}

# Run main function
main "$@"
