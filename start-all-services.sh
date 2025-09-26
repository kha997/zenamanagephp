#!/bin/bash

# ZenaManage - Complete System Startup Script
# This script starts all services needed for development and testing

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

# Function to wait for a service to be ready
wait_for_service() {
    local url=$1
    local service_name=$2
    local max_attempts=30
    local attempt=1
    
    print_status "Waiting for $service_name to be ready..."
    
    while [ $attempt -le $max_attempts ]; do
        if curl -s "$url" > /dev/null 2>&1; then
            print_success "$service_name is ready!"
            return 0
        fi
        
        echo -n "."
        sleep 2
        attempt=$((attempt + 1))
    done
    
    print_error "$service_name failed to start after $max_attempts attempts"
    return 1
}

# Function to start service in background
start_service() {
    local service_name=$1
    local command=$2
    local port=$3
    local url=$4
    
    if check_port $port; then
        print_warning "$service_name is already running on port $port"
        return 0
    fi
    
    print_status "Starting $service_name..."
    
    # Start service in background
    eval "$command" > "logs/${service_name}.log" 2>&1 &
    local pid=$!
    echo $pid > "logs/${service_name}.pid"
    
    # Wait for service to be ready
    if wait_for_service "$url" "$service_name"; then
        print_success "$service_name started successfully (PID: $pid)"
        return 0
    else
        print_error "Failed to start $service_name"
        return 1
    fi
}

# Main startup function
main() {
    echo "🚀 ZenaManage System Startup"
    echo "=============================="
    echo ""
    
    # Create logs directory
    mkdir -p logs
    
    # Check if we're in the right directory
    if [ ! -f "artisan" ]; then
        print_error "Please run this script from the Laravel root directory"
        exit 1
    fi
    
    # Check required tools
    print_status "Checking required tools..."
    
    if ! command -v php &> /dev/null; then
        print_error "PHP is not installed or not in PATH"
        exit 1
    fi
    
    if ! command -v composer &> /dev/null; then
        print_error "Composer is not installed or not in PATH"
        exit 1
    fi
    
    if ! command -v node &> /dev/null; then
        print_error "Node.js is not installed or not in PATH"
        exit 1
    fi
    
    if ! command -v npm &> /dev/null; then
        print_error "npm is not installed or not in PATH"
        exit 1
    fi
    
    print_success "All required tools are available"
    echo ""
    
    # 1. Start Backend Laravel Server
    print_status "=== BACKEND SERVICES ==="
    
    # Install dependencies if needed
    if [ ! -d "vendor" ]; then
        print_status "Installing Laravel dependencies..."
        composer install --no-interaction
    fi
    
    # Clear Laravel caches
    print_status "Clearing Laravel caches..."
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear
    
    # Start Laravel development server
    start_service "Laravel-Backend" "php artisan serve --host=0.0.0.0 --port=8000" 8000 "http://localhost:8000"
    
    # 2. Start Frontend Development Server
    print_status "=== FRONTEND SERVICES ==="
    
    # Check if frontend directory exists
    if [ ! -d "frontend" ]; then
        print_error "Frontend directory not found"
        exit 1
    fi
    
    # Install frontend dependencies if needed
    if [ ! -d "frontend/node_modules" ]; then
        print_status "Installing frontend dependencies..."
        cd frontend
        npm install
        cd ..
    fi
    
    # Start frontend development server
    cd frontend
    start_service "React-Frontend" "npm run dev" 3000 "http://localhost:3000"
    cd ..
    
    # 3. Start WebSocket Server
    print_status "=== WEBSOCKET SERVICES ==="
    
    # Check if WebSocket server script exists
    if [ -f "websocket_server.php" ]; then
        start_service "WebSocket-Server" "php websocket_server.php" 8080 "http://localhost:8080"
    else
        print_warning "WebSocket server not found, skipping..."
    fi
    
    # 4. Start Additional Services (if available)
    print_status "=== ADDITIONAL SERVICES ==="
    
    # Check for Redis (if available)
    if command -v redis-server &> /dev/null; then
        if ! check_port 6379; then
            print_status "Starting Redis server..."
            redis-server --daemonize yes --port 6379
            print_success "Redis server started"
        else
            print_warning "Redis is already running on port 6379"
        fi
    else
        print_warning "Redis not available, skipping..."
    fi
    
    # 5. Display Service Status
    echo ""
    print_status "=== SERVICE STATUS ==="
    echo ""
    
    # Check Laravel Backend
    if check_port 8000; then
        print_success "✅ Laravel Backend: http://localhost:8000"
    else
        print_error "❌ Laravel Backend: Not running"
    fi
    
    # Check React Frontend
    if check_port 3000; then
        print_success "✅ React Frontend: http://localhost:3000"
    else
        print_error "❌ React Frontend: Not running"
    fi
    
    # Check WebSocket Server
    if check_port 8080; then
        print_success "✅ WebSocket Server: ws://localhost:8080"
    else
        print_warning "⚠️  WebSocket Server: Not running"
    fi
    
    # Check Redis
    if check_port 6379; then
        print_success "✅ Redis Server: localhost:6379"
    else
        print_warning "⚠️  Redis Server: Not running"
    fi
    
    echo ""
    print_success "🎉 System startup completed!"
    echo ""
    echo "📋 Quick Access Links:"
    echo "   • Main Dashboard: http://localhost:8000/dashboard"
    echo "   • Frontend App: http://localhost:3000"
    echo "   • API Health Check: http://localhost:8000/health"
    echo "   • Test Route: http://localhost:8000/test"
    echo ""
    echo "🔐 Demo Login Credentials:"
    echo "   • Super Admin: superadmin@zena.com / zena1234"
    echo "   • Project Manager: pm@zena.com / zena1234"
    echo "   • Designer: designer@zena.com / zena1234"
    echo "   • Site Engineer: site@zena.com / zena1234"
    echo "   • QC Engineer: qc@zena.com / zena1234"
    echo "   • Procurement: procurement@zena.com / zena1234"
    echo "   • Finance: finance@zena.com / zena1234"
    echo "   • Client: client@zena.com / zena1234"
    echo ""
    echo "📝 Logs are available in the 'logs/' directory"
    echo "🛑 To stop all services, run: ./stop-all-services.sh"
    echo ""
}

# Function to handle cleanup on exit
cleanup() {
    echo ""
    print_status "Cleaning up..."
    
    # Kill background processes
    for pidfile in logs/*.pid; do
        if [ -f "$pidfile" ]; then
            pid=$(cat "$pidfile")
            if kill -0 "$pid" 2>/dev/null; then
                kill "$pid"
                print_status "Stopped service with PID: $pid"
            fi
            rm "$pidfile"
        fi
    done
    
    print_success "Cleanup completed"
}

# Set up signal handlers
trap cleanup EXIT INT TERM

# Run main function
main "$@"
