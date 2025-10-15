#!/bin/bash

# ZenaManage System Startup Script
# This script starts all necessary services for the ZenaManage system

echo "🚀 Starting ZenaManage System..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to check if a port is in use
check_port() {
    if lsof -Pi :$1 -sTCP:LISTEN -t >/dev/null ; then
        echo -e "${YELLOW}⚠️  Port $1 is already in use${NC}"
        return 1
    else
        echo -e "${GREEN}✅ Port $1 is available${NC}"
        return 0
    fi
}

# Function to wait for service to be ready
wait_for_service() {
    local url=$1
    local service_name=$2
    local max_attempts=30
    local attempt=1
    
    echo -e "${BLUE}⏳ Waiting for $service_name to be ready...${NC}"
    
    while [ $attempt -le $max_attempts ]; do
        if curl -s -o /dev/null -w "%{http_code}" "$url" | grep -q "200\|302\|404"; then
            echo -e "${GREEN}✅ $service_name is ready!${NC}"
            return 0
        fi
        
        echo -e "${YELLOW}⏳ Attempt $attempt/$max_attempts - $service_name not ready yet...${NC}"
        sleep 2
        ((attempt++))
    done
    
    echo -e "${RED}❌ $service_name failed to start after $max_attempts attempts${NC}"
    return 1
}

# Change to project directory
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage

echo -e "${BLUE}📁 Changed to project directory: $(pwd)${NC}"

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo -e "${RED}❌ .env file not found! Please create it from .env.example${NC}"
    exit 1
fi

echo -e "${GREEN}✅ .env file found${NC}"

# Check PHP version
echo -e "${BLUE}🔍 Checking PHP version...${NC}"
php_version=$(php -v | head -n 1)
echo -e "${GREEN}✅ $php_version${NC}"

# Check Composer
echo -e "${BLUE}🔍 Checking Composer...${NC}"
composer_version=$(composer --version | head -n 1)
echo -e "${GREEN}✅ $composer_version${NC}"

# Check Node.js
echo -e "${BLUE}🔍 Checking Node.js...${NC}"
node_version=$(node --version)
echo -e "${GREEN}✅ Node.js $node_version${NC}"

# Install/Update dependencies
echo -e "${BLUE}📦 Installing/Updating dependencies...${NC}"

echo -e "${YELLOW}Installing PHP dependencies...${NC}"
composer install --no-dev --optimize-autoloader

echo -e "${YELLOW}Installing Node.js dependencies...${NC}"
npm install --legacy-peer-deps

# Generate application key if not set
echo -e "${BLUE}🔑 Generating application key...${NC}"
php artisan key:generate

# Clear and cache configuration
echo -e "${BLUE}🧹 Clearing caches...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo -e "${BLUE}⚡ Caching configuration...${NC}"
php artisan config:cache

# Check database connection
echo -e "${BLUE}🗄️  Checking database connection...${NC}"
if php artisan migrate:status > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Database connection successful${NC}"
else
    echo -e "${RED}❌ Database connection failed! Please check your database configuration${NC}"
    exit 1
fi

# Check ports
echo -e "${BLUE}🔍 Checking available ports...${NC}"
check_port 8000
check_port 3000

# Start Laravel development server
echo -e "${BLUE}🚀 Starting Laravel development server...${NC}"
php artisan serve --host=0.0.0.0 --port=8000 &
LARAVEL_PID=$!

# Wait for Laravel server to be ready
wait_for_service "http://localhost:8000" "Laravel Server"

# Start Vite development server
echo -e "${BLUE}🚀 Starting Vite development server...${NC}"
npm run dev &
VITE_PID=$!

# Wait for Vite server to be ready
wait_for_service "http://localhost:3000" "Vite Server"

# Display system status
echo ""
echo -e "${GREEN}🎉 ZenaManage System Started Successfully!${NC}"
echo ""
echo -e "${BLUE}📊 System Status:${NC}"
echo -e "  ${GREEN}✅ Laravel Server:${NC} http://localhost:8000"
echo -e "  ${GREEN}✅ Vite Dev Server:${NC} http://localhost:3000"
echo -e "  ${GREEN}✅ Application Dashboard:${NC} http://localhost:8000/app/dashboard"
echo ""
echo -e "${BLUE}🔧 Process IDs:${NC}"
echo -e "  Laravel Server PID: $LARAVEL_PID"
echo -e "  Vite Server PID: $VITE_PID"
echo ""
echo -e "${YELLOW}💡 To stop the system, run: ./stop-system.sh${NC}"
echo -e "${YELLOW}💡 Or press Ctrl+C to stop this script${NC}"

# Function to cleanup on exit
cleanup() {
    echo ""
    echo -e "${YELLOW}🛑 Stopping ZenaManage System...${NC}"
    
    if [ ! -z "$LARAVEL_PID" ]; then
        kill $LARAVEL_PID 2>/dev/null
        echo -e "${GREEN}✅ Laravel server stopped${NC}"
    fi
    
    if [ ! -z "$VITE_PID" ]; then
        kill $VITE_PID 2>/dev/null
        echo -e "${GREEN}✅ Vite server stopped${NC}"
    fi
    
    echo -e "${GREEN}🎉 System stopped successfully${NC}"
    exit 0
}

# Set up signal handlers
trap cleanup SIGINT SIGTERM

# Keep script running
echo -e "${BLUE}⏳ System is running... Press Ctrl+C to stop${NC}"
wait
