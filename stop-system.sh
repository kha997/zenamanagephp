#!/bin/bash

# ZenaManage System Stop Script
# This script stops all ZenaManage services

echo "🛑 Stopping ZenaManage System..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to kill process by port
kill_by_port() {
    local port=$1
    local service_name=$2
    
    echo -e "${BLUE}🔍 Looking for processes on port $port...${NC}"
    
    local pids=$(lsof -ti :$port)
    if [ ! -z "$pids" ]; then
        echo -e "${YELLOW}⚠️  Found processes on port $port: $pids${NC}"
        kill $pids 2>/dev/null
        sleep 2
        
        # Check if processes are still running
        local remaining_pids=$(lsof -ti :$port)
        if [ ! -z "$remaining_pids" ]; then
            echo -e "${RED}❌ Force killing remaining processes on port $port...${NC}"
            kill -9 $remaining_pids 2>/dev/null
        fi
        
        echo -e "${GREEN}✅ $service_name stopped${NC}"
    else
        echo -e "${YELLOW}ℹ️  No processes found on port $port${NC}"
    fi
}

# Function to kill processes by name pattern
kill_by_pattern() {
    local pattern=$1
    local service_name=$2
    
    echo -e "${BLUE}🔍 Looking for processes matching '$pattern'...${NC}"
    
    local pids=$(pgrep -f "$pattern")
    if [ ! -z "$pids" ]; then
        echo -e "${YELLOW}⚠️  Found processes matching '$pattern': $pids${NC}"
        kill $pids 2>/dev/null
        sleep 2
        
        # Check if processes are still running
        local remaining_pids=$(pgrep -f "$pattern")
        if [ ! -z "$remaining_pids" ]; then
            echo -e "${RED}❌ Force killing remaining processes...${NC}"
            kill -9 $remaining_pids 2>/dev/null
        fi
        
        echo -e "${GREEN}✅ $service_name stopped${NC}"
    else
        echo -e "${YELLOW}ℹ️  No processes found matching '$pattern'${NC}"
    fi
}

# Stop Laravel development server (port 8000)
kill_by_port 8000 "Laravel Server"

# Stop Vite development server (port 3000)
kill_by_port 3000 "Vite Server"

# Stop any remaining PHP artisan processes
kill_by_pattern "php artisan serve" "PHP Artisan Serve"

# Stop any remaining Node.js processes related to the project
kill_by_pattern "node.*vite" "Vite Dev Server"
kill_by_pattern "node.*zenamanage" "Node.js Processes"

# Stop any remaining npm processes
kill_by_pattern "npm run dev" "NPM Dev Process"

echo ""
echo -e "${GREEN}🎉 ZenaManage System Stopped Successfully!${NC}"
echo ""
echo -e "${BLUE}📊 Final Status Check:${NC}"

# Check if ports are free
if ! lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null ; then
    echo -e "  ${GREEN}✅ Port 8000 is free${NC}"
else
    echo -e "  ${RED}❌ Port 8000 is still in use${NC}"
fi

if ! lsof -Pi :3000 -sTCP:LISTEN -t >/dev/null ; then
    echo -e "  ${GREEN}✅ Port 3000 is free${NC}"
else
    echo -e "  ${RED}❌ Port 3000 is still in use${NC}"
fi

echo ""
echo -e "${YELLOW}💡 To start the system again, run: ./start-system.sh${NC}"
