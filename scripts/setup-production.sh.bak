#!/bin/bash

# ZenaManage Production Setup Script
# This script sets up the production environment for ZenaManage

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="ZenaManage"
PROJECT_PATH=$(pwd)
BACKUP_PATH="/var/backups/zenamanage"
LOG_FILE="/var/log/zenamanage-setup.log"

# Functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}✅ $1${NC}" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}❌ $1${NC}" | tee -a "$LOG_FILE"
    exit 1
}

# Check if running as root
check_root() {
    if [[ $EUID -eq 0 ]]; then
        error "This script should not be run as root. Please run as a regular user with sudo privileges."
    fi
}

# Check system requirements
check_requirements() {
    log "Checking system requirements..."
    
    # Check PHP version
    if ! command -v php &> /dev/null; then
        error "PHP is not installed. Please install PHP 8.1+ first."
    fi
    
    PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
    if [[ $(echo "$PHP_VERSION < 8.1" | bc -l) -eq 1 ]]; then
        error "PHP version $PHP_VERSION is not supported. Please install PHP 8.1+ first."
    fi
    
    success "PHP $PHP_VERSION is installed"
    
    # Check Composer
    if ! command -v composer &> /dev/null; then
        error "Composer is not installed. Please install Composer first."
    fi
    
    success "Composer is installed"
    
    # Check Node.js
    if ! command -v node &> /dev/null; then
        error "Node.js is not installed. Please install Node.js first."
    fi
    
    success "Node.js $(node --version) is installed"
    
    # Check MySQL/PostgreSQL
    if ! command -v mysql &> /dev/null && ! command -v psql &> /dev/null; then
        error "Neither MySQL nor PostgreSQL is installed. Please install one of them first."
    fi
    
    success "Database system is available"
}

# Install system dependencies
install_dependencies() {
    log "Installing system dependencies..."
    
    # Update package list
    sudo apt update
    
    # Install required packages
    sudo apt install -y \
        nginx \
        redis-server \
        supervisor \
        certbot \
        python3-certbot-nginx \
        htop \
        iotop \
        nethogs \
        logwatch \
        ufw \
        bc
    
    success "System dependencies installed"
}

# Setup database
setup_database() {
    log "Setting up database..."
    
    read -p "Enter database name [zenamanage_production]: " DB_NAME
    DB_NAME=${DB_NAME:-zenamanage_production}
    
    read -p "Enter database username [zenamanage_user]: " DB_USER
    DB_USER=${DB_USER:-zenamanage_user}
    
    read -s -p "Enter database password: " DB_PASS
    echo
    
    # Create database and user
    mysql -u root -p << EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF
    
    success "Database created successfully"
}

# Setup environment
setup_environment() {
    log "Setting up environment configuration..."
    
    # Copy environment file
    if [[ ! -f .env ]]; then
        cp production.env.example .env
        success "Environment file created"
    else
        warning "Environment file already exists"
    fi
    
    # Generate application key
    php artisan key:generate --force
    
    success "Environment configured"
}

# Install application dependencies
install_app_dependencies() {
    log "Installing application dependencies..."
    
    # Install PHP dependencies
    composer install --optimize-autoloader --no-dev
    
    # Install Node.js dependencies
    npm install
    npm run build
    
    success "Application dependencies installed"
}

# Setup database migrations
setup_database_migrations() {
    log "Running database migrations..."
    
    php artisan migrate --force
    
    success "Database migrations completed"
}

# Setup Redis
setup_redis() {
    log "Setting up Redis..."
    
    # Start Redis service
    sudo systemctl start redis-server
    sudo systemctl enable redis-server
    
    # Test Redis connection
    redis-cli ping
    
    success "Redis is running"
}

# Setup queue workers
setup_queue_workers() {
    log "Setting up queue workers..."
    
    # Create supervisor configuration
    sudo tee /etc/supervisor/conf.d/zenamanage-worker.conf > /dev/null << EOF
[program:zenamanage-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_PATH/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=$PROJECT_PATH/storage/logs/worker.log
stopwaitsecs=3600
EOF
    
    # Reload supervisor
    sudo supervisorctl reread
    sudo supervisorctl update
    sudo supervisorctl start zenamanage-worker:*
    
    success "Queue workers configured"
}

# Setup Nginx
setup_nginx() {
    log "Setting up Nginx..."
    
    read -p "Enter domain name: " DOMAIN_NAME
    
    # Create Nginx configuration
    sudo tee /etc/nginx/sites-available/zenamanage > /dev/null << EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN_NAME;
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name $DOMAIN_NAME;

    root $PROJECT_PATH/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/$DOMAIN_NAME/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN_NAME/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
EOF
    
    # Enable site
    sudo ln -sf /etc/nginx/sites-available/zenamanage /etc/nginx/sites-enabled/
    sudo nginx -t
    sudo systemctl reload nginx
    
    success "Nginx configured"
}

# Setup SSL certificate
setup_ssl() {
    log "Setting up SSL certificate..."
    
    read -p "Enter domain name: " DOMAIN_NAME
    
    # Get SSL certificate
    sudo certbot --nginx -d "$DOMAIN_NAME" --non-interactive --agree-tos --email admin@"$DOMAIN_NAME"
    
    success "SSL certificate installed"
}

# Setup monitoring
setup_monitoring() {
    log "Setting up monitoring..."
    
    # Create monitoring cron job
    (crontab -l 2>/dev/null; echo "*/5 * * * * cd $PROJECT_PATH && php artisan email:monitor --send-alerts") | crontab -
    
    # Setup log rotation
    sudo tee /etc/logrotate.d/zenamanage > /dev/null << EOF
$PROJECT_PATH/storage/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
EOF
    
    success "Monitoring configured"
}

# Setup firewall
setup_firewall() {
    log "Setting up firewall..."
    
    sudo ufw allow 22
    sudo ufw allow 80
    sudo ufw allow 443
    sudo ufw --force enable
    
    success "Firewall configured"
}

# Set file permissions
set_permissions() {
    log "Setting file permissions..."
    
    sudo chown -R www-data:www-data "$PROJECT_PATH"
    sudo chmod -R 755 "$PROJECT_PATH"
    sudo chmod -R 775 "$PROJECT_PATH/storage"
    sudo chmod -R 775 "$PROJECT_PATH/bootstrap/cache"
    
    success "File permissions set"
}

# Optimize application
optimize_application() {
    log "Optimizing application..."
    
    # Cache configuration
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    
    # Warm up email cache
    php artisan email:warm-cache
    
    success "Application optimized"
}

# Create backup script
create_backup_script() {
    log "Creating backup script..."
    
    sudo mkdir -p "$BACKUP_PATH"
    
    sudo tee /usr/local/bin/zenamanage-backup > /dev/null << EOF
#!/bin/bash
DATE=\$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="$BACKUP_PATH"

# Database backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > \$BACKUP_DIR/db_\$DATE.sql

# File backup
tar -czf \$BACKUP_DIR/files_\$DATE.tar.gz -C $PROJECT_PATH .

# Keep only last 7 days of backups
find \$BACKUP_DIR -name "*.sql" -mtime +7 -delete
find \$BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete
EOF
    
    sudo chmod +x /usr/local/bin/zenamanage-backup
    
    # Add to crontab
    (crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/zenamanage-backup") | crontab -
    
    success "Backup script created"
}

# Main setup function
main() {
    log "Starting ZenaManage production setup..."
    
    check_root
    check_requirements
    install_dependencies
    setup_database
    setup_environment
    install_app_dependencies
    setup_database_migrations
    setup_redis
    setup_queue_workers
    setup_nginx
    setup_ssl
    setup_monitoring
    setup_firewall
    set_permissions
    optimize_application
    create_backup_script
    
    success "Production setup completed successfully!"
    
    log "Next steps:"
    log "1. Update .env file with your actual configuration"
    log "2. Test email functionality"
    log "3. Set up monitoring alerts"
    log "4. Configure backup strategy"
    log "5. Test the application"
    
    log "Setup log saved to: $LOG_FILE"
}

# Run main function
main "$@"
