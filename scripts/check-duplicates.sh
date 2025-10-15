#!/bin/bash

# Local duplicate detection script
# This script can be run locally to check for duplicate patterns

echo "🔍 Running local duplicate detection..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check for duplicate patterns
echo "📊 Checking for duplicate patterns..."

# Check for duplicate header components
HEADER_FILES=$(find resources/views -name "*header*" -type f | wc -l)
if [ $HEADER_FILES -gt 2 ]; then
    print_warning "Found $HEADER_FILES header files - consider consolidating"
else
    print_status "Header files count: $HEADER_FILES"
fi

# Check for duplicate layout files
LAYOUT_FILES=$(find resources/views/layouts -name "app*.blade.php" -type f | wc -l)
if [ $LAYOUT_FILES -gt 1 ]; then
    print_warning "Found $LAYOUT_FILES layout files - consider consolidating"
else
    print_status "Layout files count: $LAYOUT_FILES"
fi

# Check for duplicate dashboard files
DASHBOARD_FILES=$(find resources/views/app -name "*dashboard*" -type f | wc -l)
if [ $DASHBOARD_FILES -gt 1 ]; then
    print_warning "Found $DASHBOARD_FILES dashboard files - consider consolidating"
else
    print_status "Dashboard files count: $DASHBOARD_FILES"
fi

# Check for duplicate project files
PROJECT_FILES=$(find resources/views/app -name "*project*" -type f | wc -l)
if [ $PROJECT_FILES -gt 2 ]; then
    print_warning "Found $PROJECT_FILES project files - consider consolidating"
else
    print_status "Project files count: $PROJECT_FILES"
fi

# Check for duplicate middleware files
MIDDLEWARE_FILES=$(find app/Http/Middleware -name "*.php" -type f | wc -l)
if [ $MIDDLEWARE_FILES -gt 15 ]; then
    print_warning "Found $MIDDLEWARE_FILES middleware files - consider consolidating"
else
    print_status "Middleware files count: $MIDDLEWARE_FILES"
fi

# Check for duplicate controller files
CONTROLLER_FILES=$(find app/Http/Controllers -name "*.php" -type f | wc -l)
if [ $CONTROLLER_FILES -gt 20 ]; then
    print_warning "Found $CONTROLLER_FILES controller files - consider consolidating"
else
    print_status "Controller files count: $CONTROLLER_FILES"
fi

# Check for duplicate service files
SERVICE_FILES=$(find app/Services -name "*.php" -type f | wc -l)
if [ $SERVICE_FILES -gt 10 ]; then
    print_warning "Found $SERVICE_FILES service files - consider consolidating"
else
    print_status "Service files count: $SERVICE_FILES"
fi

# Check for duplicate request files
REQUEST_FILES=$(find app/Http/Requests -name "*.php" -type f | wc -l)
if [ $REQUEST_FILES -gt 15 ]; then
    print_warning "Found $REQUEST_FILES request files - consider consolidating"
else
    print_status "Request files count: $REQUEST_FILES"
fi

# Check for duplicate React components
REACT_FILES=$(find resources/js -name "*.tsx" -o -name "*.jsx" | wc -l)
if [ $REACT_FILES -gt 20 ]; then
    print_warning "Found $REACT_FILES React components - consider consolidating"
else
    print_status "React components count: $REACT_FILES"
fi

# Check for duplicate Blade components
BLADE_FILES=$(find resources/views/components -name "*.blade.php" -type f | wc -l)
if [ $BLADE_FILES -gt 30 ]; then
    print_warning "Found $BLADE_FILES Blade components - consider consolidating"
else
    print_status "Blade components count: $BLADE_FILES"
fi

echo ""
print_status "Local duplicate detection completed"
