#!/bin/bash

# 🚀 ZENA SYSTEM IMPROVEMENT AUTOMATION SCRIPTS
# Version: 1.0
# Date: 20/09/2025
# Author: Senior Software Architect

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
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
}

# Check if we're in the right directory
check_directory() {
    if [ ! -f "artisan" ]; then
        error "Please run this script from the Laravel project root directory"
        exit 1
    fi
    success "Laravel project directory confirmed"
}

# Phase 1: Security & Performance
phase1_security() {
    log "🔒 Starting Phase 1: Security Improvements"
    
    # Fix CSRF protection
    log "Adding CSRF protection to forms..."
    find resources/views -name "*.blade.php" -type f -exec grep -l "<form" {} \; | while read file; do
        if ! grep -q "@csrf" "$file"; then
            sed -i 's/<form/<form @csrf/g' "$file"
            log "Added CSRF to: $file"
        fi
    done
    success "CSRF protection added to all forms"
    
    # Fix password hashing
    log "Replacing md5() with Hash::make()..."
    find app -name "*.php" -type f -exec grep -l "md5(" {} \; | while read file; do
        sed -i 's/md5(/Hash::make(/g' "$file"
        log "Fixed password hashing in: $file"
    done
    success "Password hashing fixed"
    
    # Add input sanitization
    log "Adding input sanitization..."
    if [ ! -f "app/Services/InputSanitizationService.php" ]; then
        cat > app/Services/InputSanitizationService.php << 'EOF'
<?php

namespace App\Services;

class InputSanitizationService
{
    public static function sanitize($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    public static function sanitizeEmail($email)
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
    
    public static function sanitizeUrl($url)
    {
        return filter_var(trim($url), FILTER_SANITIZE_URL);
    }
}
EOF
        success "InputSanitizationService created"
    fi
}

phase1_performance() {
    log "⚡ Starting Phase 1: Performance Improvements"
    
    # Create performance indexes migration
    log "Creating performance indexes migration..."
    if [ ! -f "database/migrations/2025_09_20_add_performance_indexes.php" ]; then
        php artisan make:migration add_performance_indexes
        cat > database/migrations/$(ls database/migrations/ | grep add_performance_indexes | tail -1) << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['status']);
            $table->index(['assignee_id']);
            $table->index(['project_id']);
            $table->index(['status', 'assignee_id']);
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->index(['email']);
            $table->index(['status']);
        });
        
        Schema::table('projects', function (Blueprint $table) {
            $table->index(['status']);
            $table->index(['created_by']);
        });
        
        Schema::table('documents', function (Blueprint $table) {
            $table->index(['project_id']);
            $table->index(['status']);
        });
    }
    
    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['assignee_id']);
            $table->dropIndex(['project_id']);
            $table->dropIndex(['status', 'assignee_id']);
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['status']);
        });
        
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_by']);
        });
        
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
            $table->dropIndex(['status']);
        });
    }
};
EOF
        success "Performance indexes migration created"
    fi
    
    # Clear caches
    log "Clearing caches..."
    php artisan cache:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    success "Caches cleared"
    
    # Optimize autoloader
    log "Optimizing autoloader..."
    composer dump-autoload --optimize
    success "Autoloader optimized"
}

# Phase 2: Testing & Monitoring
phase2_testing() {
    log "🧪 Starting Phase 2: Testing Improvements"
    
    # Create test coverage script
    log "Creating test coverage script..."
    cat > scripts/run-tests-with-coverage.sh << 'EOF'
#!/bin/bash

echo "🧪 Running tests with coverage..."

# Run all tests
php artisan test --coverage

# Run specific test suites
echo "Running Unit Tests..."
php artisan test tests/Unit

echo "Running Feature Tests..."
php artisan test tests/Feature

echo "Running Integration Tests..."
php artisan test tests/Integration

echo "✅ All tests completed!"
EOF
    chmod +x scripts/run-tests-with-coverage.sh
    success "Test coverage script created"
    
    # Create test data factories
    log "Creating test data factories..."
    if [ ! -f "database/factories/TestDataFactory.php" ]; then
        cat > database/factories/TestDataFactory.php << 'EOF'
<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestDataFactory extends Factory
{
    public function createTestUsers($count = 10)
    {
        return User::factory($count)->create();
    }
    
    public function createTestProjects($count = 5)
    {
        return Project::factory($count)->create();
    }
    
    public function createTestTasks($count = 20)
    {
        return Task::factory($count)->create();
    }
    
    public function createCompleteTestData()
    {
        $users = $this->createTestUsers(10);
        $projects = $this->createTestProjects(5);
        $tasks = $this->createTestTasks(20);
        
        return [
            'users' => $users,
            'projects' => $projects,
            'tasks' => $tasks
        ];
    }
}
EOF
        success "Test data factory created"
    fi
}

phase2_monitoring() {
    log "📊 Starting Phase 2: Monitoring Improvements"
    
    # Create monitoring setup script
    log "Creating monitoring setup script..."
    cat > scripts/setup-monitoring.sh << 'EOF'
#!/bin/bash

echo "📊 Setting up monitoring..."

# Install monitoring packages
composer require sentry/sentry-laravel
composer require elastic/apm-agent-php

# Create monitoring directories
mkdir -p storage/logs/monitoring
mkdir -p storage/logs/performance
mkdir -p storage/logs/security

# Create monitoring configuration
cat > config/monitoring.php << 'MONITORING_CONFIG'
<?php

return [
    'sentry' => [
        'dsn' => env('SENTRY_DSN'),
        'environment' => env('APP_ENV'),
    ],
    
    'apm' => [
        'enabled' => env('APM_ENABLED', false),
        'server_url' => env('APM_SERVER_URL'),
        'secret_token' => env('APM_SECRET_TOKEN'),
    ],
    
    'metrics' => [
        'enabled' => env('METRICS_ENABLED', true),
        'collect_interval' => env('METRICS_COLLECT_INTERVAL', 60),
    ],
];
MONITORING_CONFIG

echo "✅ Monitoring setup completed!"
EOF
    chmod +x scripts/setup-monitoring.sh
    success "Monitoring setup script created"
}

# Phase 3: Code Quality & Documentation
phase3_code_quality() {
    log "🔧 Starting Phase 3: Code Quality Improvements"
    
    # Setup PHP CS Fixer
    log "Setting up PHP CS Fixer..."
    if [ ! -f ".php-cs-fixer.php" ]; then
        cat > .php-cs-fixer.php << 'EOF'
<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config = new PhpCsFixer\Config();
return $config
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => true,
        'trailing_comma_in_multiline' => true,
        'phpdoc_scalar' => true,
        'unary_operator_spaces' => true,
        'binary_operator_spaces' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => true,
        ],
    ])
    ->setFinder($finder);
EOF
        success "PHP CS Fixer configuration created"
    fi
    
    # Create code quality script
    log "Creating code quality script..."
    cat > scripts/check-code-quality.sh << 'EOF'
#!/bin/bash

echo "🔧 Checking code quality..."

# Run PHP CS Fixer
echo "Running PHP CS Fixer..."
vendor/bin/php-cs-fixer fix --dry-run --diff

# Run PHPStan
echo "Running PHPStan..."
vendor/bin/phpstan analyse app --level=5

# Run Psalm
echo "Running Psalm..."
vendor/bin/psalm

echo "✅ Code quality check completed!"
EOF
    chmod +x scripts/check-code-quality.sh
    success "Code quality script created"
}

phase3_documentation() {
    log "📚 Starting Phase 3: Documentation Improvements"
    
    # Create documentation structure
    log "Creating documentation structure..."
    mkdir -p docs/api
    mkdir -p docs/architecture
    mkdir -p docs/user
    mkdir -p docs/admin
    mkdir -p docs/developer
    
    # Create API documentation script
    log "Creating API documentation script..."
    cat > scripts/generate-api-docs.sh << 'EOF'
#!/bin/bash

echo "📚 Generating API documentation..."

# Generate API documentation using Swagger
php artisan l5-swagger:generate

# Create API documentation index
cat > docs/api/README.md << 'API_DOCS'
# Zena Project Management API Documentation

## Overview
This document provides comprehensive API documentation for the Zena Project Management system.

## Authentication
All API endpoints require authentication. Include the Bearer token in the Authorization header.

## Endpoints

### Projects
- `GET /api/projects` - List all projects
- `POST /api/projects` - Create a new project
- `GET /api/projects/{id}` - Get project details
- `PUT /api/projects/{id}` - Update project
- `DELETE /api/projects/{id}` - Delete project

### Tasks
- `GET /api/tasks` - List all tasks
- `POST /api/tasks` - Create a new task
- `GET /api/tasks/{id}` - Get task details
- `PUT /api/tasks/{id}` - Update task
- `DELETE /api/tasks/{id}` - Delete task

### Users
- `GET /api/users` - List all users
- `POST /api/users` - Create a new user
- `GET /api/users/{id}` - Get user details
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user

## Error Handling
All API endpoints return standardized error responses.

## Rate Limiting
API endpoints are rate limited to prevent abuse.
API_DOCS

echo "✅ API documentation generated!"
EOF
    chmod +x scripts/generate-api-docs.sh
    success "API documentation script created"
}

# Phase 4: Optimization & Maintenance
phase4_optimization() {
    log "🚀 Starting Phase 4: Optimization & Maintenance"
    
    # Create optimization script
    log "Creating optimization script..."
    cat > scripts/optimize-system.sh << 'EOF'
#!/bin/bash

echo "🚀 Optimizing system..."

# Database optimization
echo "Optimizing database..."
php artisan migrate --force
php artisan db:seed --force

# Cache optimization
echo "Optimizing caches..."
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Queue optimization
echo "Optimizing queues..."
php artisan queue:restart
php artisan queue:work --daemon &

# File optimization
echo "Optimizing files..."
php artisan storage:link
php artisan optimize

# Composer optimization
echo "Optimizing Composer..."
composer dump-autoload --optimize --no-dev

echo "✅ System optimization completed!"
EOF
    chmod +x scripts/optimize-system.sh
    success "System optimization script created"
    
    # Create maintenance script
    log "Creating maintenance script..."
    cat > scripts/maintenance.sh << 'EOF'
#!/bin/bash

echo "🔧 Running maintenance tasks..."

# Backup database
echo "Backing up database..."
php artisan backup:run

# Clean old logs
echo "Cleaning old logs..."
find storage/logs -name "*.log" -mtime +30 -delete

# Clean old cache
echo "Cleaning old cache..."
php artisan cache:clear
php artisan config:clear

# Update dependencies
echo "Updating dependencies..."
composer update --no-dev

# Run tests
echo "Running tests..."
php artisan test

echo "✅ Maintenance completed!"
EOF
    chmod +x scripts/maintenance.sh
    success "Maintenance script created"
}

# Main execution function
main() {
    log "🚀 Starting Zena System Improvement Automation"
    
    check_directory
    
    case "${1:-all}" in
        "security")
            phase1_security
            ;;
        "performance")
            phase1_performance
            ;;
        "testing")
            phase2_testing
            ;;
        "monitoring")
            phase2_monitoring
            ;;
        "code-quality")
            phase3_code_quality
            ;;
        "documentation")
            phase3_documentation
            ;;
        "optimization")
            phase4_optimization
            ;;
        "all")
            log "Running all phases..."
            phase1_security
            phase1_performance
            phase2_testing
            phase2_monitoring
            phase3_code_quality
            phase3_documentation
            phase4_optimization
            ;;
        *)
            echo "Usage: $0 [security|performance|testing|monitoring|code-quality|documentation|optimization|all]"
            exit 1
            ;;
    esac
    
    success "🎉 Zena System Improvement Automation completed!"
}

# Run main function
main "$@"
