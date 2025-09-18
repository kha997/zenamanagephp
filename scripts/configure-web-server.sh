#!/bin/bash

# ZenaManage Web Server Configuration Script

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
PROJECT_PATH=$(pwd)
LOG_FILE="$PROJECT_PATH/storage/logs/configure-web-server-$(date +%Y%m%d_%H%M%S).log"

# --- Functions ---
log() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

success() {
    log "✅ $1"
}

error() {
    log "❌ $1"
    exit 1
}

warning() {
    log "⚠️  $1"
}

# --- Main Script ---
log "🌐 Configuring Web Server for Production"
log "========================================"

# 1. Detect web server
log "Detecting web server..."
if command -v apache2 &> /dev/null || command -v httpd &> /dev/null; then
    WEB_SERVER="apache"
    log "Detected: Apache web server"
elif command -v nginx &> /dev/null; then
    WEB_SERVER="nginx"
    log "Detected: Nginx web server"
else
    error "No web server detected. Please install Apache or Nginx."
fi

# 2. Get domain information
if [ -f "config/domain.php" ]; then
    DOMAIN=$(grep "'domain'" config/domain.php | cut -d "'" -f 4)
    log "Found domain from config: $DOMAIN"
else
    read -p "Enter your domain name (e.g., zenamanage.com): " DOMAIN
    if [ -z "$DOMAIN" ]; then
        error "Domain name cannot be empty"
    fi
fi

# 3. Check if SSL certificate exists
SSL_ENABLED=false
if [ -f "storage/ssl/server.crt" ] && [ -f "storage/ssl/server.key" ]; then
    SSL_ENABLED=true
    log "SSL certificate found - HTTPS will be enabled"
else
    log "No SSL certificate found - HTTP only"
fi

# 4. Configure Apache
if [ "$WEB_SERVER" = "apache" ]; then
    log "🔧 Configuring Apache Web Server"
    log "==============================="
    
    # Check if virtual host config exists
    if [ ! -f "config/apache-virtual-host.conf" ]; then
        error "Apache virtual host configuration not found. Please run setup-domain-name.sh first."
    fi
    
    # Copy virtual host configuration
    log "Installing Apache virtual host configuration..."
    sudo cp config/apache-virtual-host.conf "/etc/apache2/sites-available/$DOMAIN.conf"
    success "Apache virtual host configuration installed"
    
    # Enable site
    log "Enabling Apache site..."
    sudo a2ensite "$DOMAIN.conf"
    success "Apache site enabled"
    
    # Enable required modules
    log "Enabling Apache modules..."
    sudo a2enmod rewrite
    sudo a2enmod headers
    if [ "$SSL_ENABLED" = true ]; then
        sudo a2enmod ssl
        log "SSL module enabled"
    fi
    success "Apache modules enabled"
    
    # Test configuration
    log "Testing Apache configuration..."
    if sudo apache2ctl configtest; then
        success "Apache configuration is valid"
    else
        error "Apache configuration test failed"
    fi
    
    # Restart Apache
    log "Restarting Apache..."
    sudo systemctl restart apache2
    success "Apache restarted"
    
    log "Apache configuration completed!"
fi

# 5. Configure Nginx
if [ "$WEB_SERVER" = "nginx" ]; then
    log "🔧 Configuring Nginx Web Server"
    log "==============================="
    
    # Check if virtual host config exists
    if [ ! -f "config/nginx-virtual-host.conf" ]; then
        error "Nginx virtual host configuration not found. Please run setup-domain-name.sh first."
    fi
    
    # Copy virtual host configuration
    log "Installing Nginx virtual host configuration..."
    sudo cp config/nginx-virtual-host.conf "/etc/nginx/sites-available/$DOMAIN"
    success "Nginx virtual host configuration installed"
    
    # Create symbolic link
    log "Creating symbolic link..."
    sudo ln -sf "/etc/nginx/sites-available/$DOMAIN" "/etc/nginx/sites-enabled/$DOMAIN"
    success "Symbolic link created"
    
    # Remove default site
    if [ -f "/etc/nginx/sites-enabled/default" ]; then
        log "Removing default site..."
        sudo rm "/etc/nginx/sites-enabled/default"
        success "Default site removed"
    fi
    
    # Test configuration
    log "Testing Nginx configuration..."
    if sudo nginx -t; then
        success "Nginx configuration is valid"
    else
        error "Nginx configuration test failed"
    fi
    
    # Restart Nginx
    log "Restarting Nginx..."
    sudo systemctl restart nginx
    success "Nginx restarted"
    
    log "Nginx configuration completed!"
fi

# 6. Configure PHP-FPM (if using Nginx)
if [ "$WEB_SERVER" = "nginx" ]; then
    log "🔧 Configuring PHP-FPM"
    log "======================"
    
    # Check PHP-FPM status
    if systemctl is-active --quiet php8.2-fpm; then
        log "PHP-FPM is running"
    else
        log "Starting PHP-FPM..."
        sudo systemctl start php8.2-fpm
        success "PHP-FPM started"
    fi
    
    # Enable PHP-FPM
    sudo systemctl enable php8.2-fpm
    success "PHP-FPM enabled"
fi

# 7. Set proper permissions
log "Setting proper permissions..."
sudo chown -R www-data:www-data "$PROJECT_PATH"
sudo chmod -R 755 "$PROJECT_PATH"
sudo chmod -R 775 "$PROJECT_PATH/storage"
sudo chmod -R 775 "$PROJECT_PATH/bootstrap/cache"
success "Permissions set"

# 8. Create web server testing script
log "Creating web server testing script..."
cat > scripts/test-web-server.sh << EOF
#!/bin/bash

# Web Server Testing Script

DOMAIN="$DOMAIN"
WEB_SERVER="$WEB_SERVER"
SSL_ENABLED="$SSL_ENABLED"

echo "🌐 Testing Web Server Configuration"
echo "=================================="

# Test HTTP connection
echo "Testing HTTP connection..."
if curl -s -I http://\$DOMAIN &> /dev/null; then
    echo "✅ HTTP connection: SUCCESS"
else
    echo "❌ HTTP connection: FAILED"
fi

# Test HTTPS connection (if SSL enabled)
if [ "\$SSL_ENABLED" = "true" ]; then
    echo "Testing HTTPS connection..."
    if curl -s -I https://\$DOMAIN &> /dev/null; then
        echo "✅ HTTPS connection: SUCCESS"
    else
        echo "❌ HTTPS connection: FAILED"
    fi
fi

# Test Laravel application
echo "Testing Laravel application..."
if curl -s http://\$DOMAIN | grep -q "ZenaManage"; then
    echo "✅ Laravel application: SUCCESS"
else
    echo "❌ Laravel application: FAILED"
fi

# Test web server status
echo "Testing web server status..."
if [ "\$WEB_SERVER" = "apache" ]; then
    if systemctl is-active --quiet apache2; then
        echo "✅ Apache: RUNNING"
    else
        echo "❌ Apache: NOT RUNNING"
    fi
elif [ "\$WEB_SERVER" = "nginx" ]; then
    if systemctl is-active --quiet nginx; then
        echo "✅ Nginx: RUNNING"
    else
        echo "❌ Nginx: NOT RUNNING"
    fi
    
    if systemctl is-active --quiet php8.2-fpm; then
        echo "✅ PHP-FPM: RUNNING"
    else
        echo "❌ PHP-FPM: NOT RUNNING"
    fi
fi

echo "Web server testing completed!"
EOF

chmod +x scripts/test-web-server.sh
success "Created web server testing script"

# 9. Create web server management script
log "Creating web server management script..."
cat > scripts/manage-web-server.sh << EOF
#!/bin/bash

# Web Server Management Script

WEB_SERVER="$WEB_SERVER"

case "\$1" in
    start)
        echo "Starting web server..."
        if [ "\$WEB_SERVER" = "apache" ]; then
            sudo systemctl start apache2
        elif [ "\$WEB_SERVER" = "nginx" ]; then
            sudo systemctl start nginx
            sudo systemctl start php8.2-fpm
        fi
        echo "Web server started"
        ;;
    stop)
        echo "Stopping web server..."
        if [ "\$WEB_SERVER" = "apache" ]; then
            sudo systemctl stop apache2
        elif [ "\$WEB_SERVER" = "nginx" ]; then
            sudo systemctl stop nginx
            sudo systemctl stop php8.2-fpm
        fi
        echo "Web server stopped"
        ;;
    restart)
        echo "Restarting web server..."
        if [ "\$WEB_SERVER" = "apache" ]; then
            sudo systemctl restart apache2
        elif [ "\$WEB_SERVER" = "nginx" ]; then
            sudo systemctl restart nginx
            sudo systemctl restart php8.2-fpm
        fi
        echo "Web server restarted"
        ;;
    status)
        echo "Web server status:"
        if [ "\$WEB_SERVER" = "apache" ]; then
            sudo systemctl status apache2
        elif [ "\$WEB_SERVER" = "nginx" ]; then
            sudo systemctl status nginx
            sudo systemctl status php8.2-fpm
        fi
        ;;
    reload)
        echo "Reloading web server configuration..."
        if [ "\$WEB_SERVER" = "apache" ]; then
            sudo systemctl reload apache2
        elif [ "\$WEB_SERVER" = "nginx" ]; then
            sudo systemctl reload nginx
        fi
        echo "Web server configuration reloaded"
        ;;
    *)
        echo "Usage: \$0 {start|stop|restart|status|reload}"
        exit 1
        ;;
esac
EOF

chmod +x scripts/manage-web-server.sh
success "Created web server management script"

# 10. Summary
log ""
log "🌐 Web Server Configuration Summary"
log "==================================="
log "✅ Web server configured ($WEB_SERVER)"
log "✅ Virtual host installed"
log "✅ Required modules enabled"
log "✅ Configuration tested"
log "✅ Web server restarted"
log "✅ Permissions set"
log "✅ Testing scripts created"
log ""
log "📊 Configuration Details:"
log "- Web Server: $WEB_SERVER"
log "- Domain: $DOMAIN"
log "- SSL Enabled: $SSL_ENABLED"
log "- Virtual Host: /etc/$WEB_SERVER/sites-available/$DOMAIN"
log ""
log "🎯 Next Steps:"
log "1. Test web server: ./scripts/test-web-server.sh"
log "2. Verify domain resolution"
log "3. Test HTTP/HTTPS connections"
log "4. Monitor web server logs"
log "5. Set up monitoring"
log ""
log "📁 Management Commands:"
log "- Start: ./scripts/manage-web-server.sh start"
log "- Stop: ./scripts/manage-web-server.sh stop"
log "- Restart: ./scripts/manage-web-server.sh restart"
log "- Status: ./scripts/manage-web-server.sh status"
log "- Reload: ./scripts/manage-web-server.sh reload"
log ""
log "📁 Configuration Files:"
log "- /etc/$WEB_SERVER/sites-available/$DOMAIN - Virtual host config"
log "- scripts/test-web-server.sh - Web server testing"
log "- scripts/manage-web-server.sh - Web server management"
log ""
log "Web server configuration completed at: \$(date)"
log "Log file: $LOG_FILE"
