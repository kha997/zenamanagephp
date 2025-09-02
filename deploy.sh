#!/bin/bash

# Z.E.N.A Production Deployment Script
# Sử dụng: ./deploy.sh [environment]

set -e

ENVIRONMENT=${1:-production}
APP_DIR="/var/www/zenamanage"
BACKUP_DIR="/var/backups/zenamanage"
DATE=$(date +"%Y%m%d_%H%M%S")

echo "🚀 Starting Z.E.N.A deployment for $ENVIRONMENT environment..."

# Function to create backup
create_backup() {
    echo "📦 Creating backup..."
    mkdir -p $BACKUP_DIR
    
    # Backup database
    docker exec zena_mysql mysqldump -u root -p$DB_PASSWORD $DB_DATABASE > $BACKUP_DIR/db_backup_$DATE.sql
    
    # Backup application files
    tar -czf $BACKUP_DIR/app_backup_$DATE.tar.gz -C $APP_DIR .
    
    echo "✅ Backup created: $BACKUP_DIR/backup_$DATE"
}

# Function to deploy application
deploy_app() {
    echo "🔄 Deploying application..."
    
    # Pull latest code
    git pull origin main
    
    # Copy environment file
    cp .env.$ENVIRONMENT .env
    
    # Build and start containers
    docker-compose down
    docker-compose build --no-cache
    docker-compose up -d
    
    # Wait for services to be ready
    echo "⏳ Waiting for services to start..."
    sleep 30
    
    # Run migrations
    docker exec zena_app php artisan migrate --force
    
    # Clear caches
    docker exec zena_app php artisan config:cache
    docker exec zena_app php artisan route:cache
    docker exec zena_app php artisan view:cache
    
    # Optimize autoloader
    docker exec zena_app composer dump-autoload --optimize
    
    echo "✅ Application deployed successfully!"
}

# Function to run health checks
health_check() {
    echo "🏥 Running health checks..."
    
    # Check if containers are running
    if ! docker-compose ps | grep -q "Up"; then
        echo "❌ Some containers are not running!"
        exit 1
    fi
    
    # Check application response
    if ! curl -f http://localhost/api/v1/health > /dev/null 2>&1; then
        echo "❌ Application health check failed!"
        exit 1
    fi
    
    echo "✅ All health checks passed!"
}

# Main deployment process
main() {
    # Load environment variables
    if [ -f ".env.$ENVIRONMENT" ]; then
        source .env.$ENVIRONMENT
    else
        echo "❌ Environment file .env.$ENVIRONMENT not found!"
        exit 1
    fi
    
    # Create backup
    create_backup
    
    # Deploy application
    deploy_app
    
    # Run health checks
    health_check
    
    echo "🎉 Deployment completed successfully!"
    echo "📊 Application is running at: $APP_URL"
}

# Run main function
main