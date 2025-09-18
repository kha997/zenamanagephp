#!/bin/bash

# ZenaManage Domain Name Setup Script

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
PROJECT_PATH=$(pwd)
LOG_FILE="$PROJECT_PATH/storage/logs/setup-domain-$(date +%Y%m%d_%H%M%S).log"

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
log "🌐 Setting up Domain Name for Production"
log "======================================="

# 1. Display domain setup guide
log "📋 Domain Name Setup Guide:"
log "==========================="
log ""
log "🌍 STEP 1: Choose a Domain Name"
log "1. Select a domain name (e.g., zenamanage.com)"
log "2. Check availability at: https://whois.net/"
log "3. Register domain with a registrar (GoDaddy, Namecheap, etc.)"
log ""
log "🔧 STEP 2: DNS Configuration"
log "1. Point A record to your server IP"
log "2. Point CNAME www to your domain"
log "3. Configure MX records for email (optional)"
log ""
log "⚙️  STEP 3: Server Configuration"
log "1. Update APP_URL in .env"
log "2. Configure web server virtual host"
log "3. Set up SSL certificate"
log "4. Test domain resolution"
log ""

# 2. Prompt for domain information
log "🔐 Enter Your Domain Information:"
log "================================"

read -p "Enter your domain name (e.g., zenamanage.com): " DOMAIN_NAME
if [ -z "$DOMAIN_NAME" ]; then
    error "Domain name cannot be empty"
fi

# Validate domain format
if [[ ! "$DOMAIN_NAME" =~ ^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}$ ]]; then
    error "Please enter a valid domain name (e.g., zenamanage.com)"
fi

read -p "Enter your server IP address (e.g., 192.168.1.100): " SERVER_IP
if [ -z "$SERVER_IP" ]; then
    error "Server IP address cannot be empty"
fi

# Validate IP format
if [[ ! "$SERVER_IP" =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; then
    error "Please enter a valid IP address (e.g., 192.168.1.100)"
fi

read -p "Enter your server port (default: 80): " SERVER_PORT
SERVER_PORT=${SERVER_PORT:-80}

# 3. Backup current configuration
log "Creating backup of current configuration..."
cp .env .env.backup.domain.$(date +%Y%m%d_%H%M%S)
success "Backup created: .env.backup.domain.$(date +%Y%m%d_%H%M%S)"

# 4. Update .env with domain configuration
log "Updating .env with domain configuration..."

# Update APP_URL
if [[ "$SERVER_PORT" == "80" ]]; then
    APP_URL="http://$DOMAIN_NAME"
else
    APP_URL="http://$DOMAIN_NAME:$SERVER_PORT"
fi

sed -i.bak "s|APP_URL=.*|APP_URL=$APP_URL|" .env
success "Updated APP_URL to: $APP_URL"

# 5. Create domain configuration file
log "Creating domain configuration file..."
cat > config/domain.php << EOF
<?php

return [
    'domain' => '$DOMAIN_NAME',
    'server_ip' => '$SERVER_IP',
    'server_port' => '$SERVER_PORT',
    'app_url' => '$APP_URL',
    'ssl_enabled' => false, // Will be enabled after SSL setup
    'force_https' => false, // Will be enabled after SSL setup
];
EOF
success "Created domain configuration file"

# 6. Create Apache virtual host configuration
log "Creating Apache virtual host configuration..."
cat > config/apache-virtual-host.conf << EOF
# Apache Virtual Host Configuration for $DOMAIN_NAME

<VirtualHost *:80>
    ServerName $DOMAIN_NAME
    ServerAlias www.$DOMAIN_NAME
    DocumentRoot $PROJECT_PATH/public
    
    <Directory $PROJECT_PATH/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/$DOMAIN_NAME-error.log
    CustomLog \${APACHE_LOG_DIR}/$DOMAIN_NAME-access.log combined
    
    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</VirtualHost>

# HTTPS Virtual Host (will be enabled after SSL setup)
# <VirtualHost *:443>
#     ServerName $DOMAIN_NAME
#     ServerAlias www.$DOMAIN_NAME
#     DocumentRoot $PROJECT_PATH/public
#     
#     SSLEngine on
#     SSLCertificateFile $PROJECT_PATH/storage/ssl/server.crt
#     SSLCertificateKeyFile $PROJECT_PATH/storage/ssl/server.key
#     
#     <Directory $PROJECT_PATH/public>
#         AllowOverride All
#         Require all granted
#     </Directory>
#     
#     ErrorLog \${APACHE_LOG_DIR}/$DOMAIN_NAME-ssl-error.log
#     CustomLog \${APACHE_LOG_DIR}/$DOMAIN_NAME-ssl-access.log combined
# </VirtualHost>
EOF
success "Created Apache virtual host configuration"

# 7. Create Nginx configuration
log "Creating Nginx configuration..."
cat > config/nginx-virtual-host.conf << EOF
# Nginx Configuration for $DOMAIN_NAME

server {
    listen 80;
    server_name $DOMAIN_NAME www.$DOMAIN_NAME;
    root $PROJECT_PATH/public;
    index index.php;
    
    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
    
    # Handle PHP files
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Handle static files
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Handle Laravel routes
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    # Security
    location ~ /\. {
        deny all;
    }
    
    # Logs
    access_log /var/log/nginx/$DOMAIN_NAME-access.log;
    error_log /var/log/nginx/$DOMAIN_NAME-error.log;
}

# HTTPS Server (will be enabled after SSL setup)
# server {
#     listen 443 ssl http2;
#     server_name $DOMAIN_NAME www.$DOMAIN_NAME;
#     root $PROJECT_PATH/public;
#     index index.php;
#     
#     ssl_certificate $PROJECT_PATH/storage/ssl/server.crt;
#     ssl_certificate_key $PROJECT_PATH/storage/ssl/server.key;
#     
#     # SSL configuration
#     ssl_protocols TLSv1.2 TLSv1.3;
#     ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
#     ssl_prefer_server_ciphers off;
#     
#     # Security headers
#     add_header Strict-Transport-Security "max-age=63072000" always;
#     add_header X-Content-Type-Options nosniff;
#     add_header X-Frame-Options DENY;
#     add_header X-XSS-Protection "1; mode=block";
#     add_header Referrer-Policy "strict-origin-when-cross-origin";
#     
#     # Handle PHP files
#     location ~ \.php$ {
#         fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
#         fastcgi_index index.php;
#         fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
#         include fastcgi_params;
#     }
#     
#     # Handle static files
#     location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
#         expires 1y;
#         add_header Cache-Control "public, immutable";
#     }
#     
#     # Handle Laravel routes
#     location / {
#         try_files \$uri \$uri/ /index.php?\$query_string;
#     }
#     
#     # Logs
#     access_log /var/log/nginx/$DOMAIN_NAME-ssl-access.log;
#     error_log /var/log/nginx/$DOMAIN_NAME-ssl-error.log;
# }
EOF
success "Created Nginx configuration"

# 8. Create DNS configuration guide
log "Creating DNS configuration guide..."
cat > config/dns-setup-guide.md << EOF
# DNS Configuration Guide for $DOMAIN_NAME

## Required DNS Records

### A Record
- **Type**: A
- **Name**: @
- **Value**: $SERVER_IP
- **TTL**: 3600

### CNAME Record
- **Type**: CNAME
- **Name**: www
- **Value**: $DOMAIN_NAME
- **TTL**: 3600

## Optional DNS Records

### MX Record (for email)
- **Type**: MX
- **Name**: @
- **Value**: mail.$DOMAIN_NAME
- **Priority**: 10
- **TTL**: 3600

### TXT Record (for SPF)
- **Type**: TXT
- **Name**: @
- **Value**: "v=spf1 include:_spf.google.com ~all"
- **TTL**: 3600

## DNS Propagation
- DNS changes can take 24-48 hours to propagate globally
- Use tools like https://dnschecker.org/ to verify propagation
- Test with: nslookup $DOMAIN_NAME

## Testing Commands
\`\`\`bash
# Test DNS resolution
nslookup $DOMAIN_NAME
dig $DOMAIN_NAME

# Test HTTP connection
curl -I http://$DOMAIN_NAME
\`\`\`
EOF
success "Created DNS configuration guide"

# 9. Create domain testing script
log "Creating domain testing script..."
cat > scripts/test-domain.sh << EOF
#!/bin/bash

# Domain Testing Script

DOMAIN="$DOMAIN_NAME"
IP="$SERVER_IP"

echo "🌐 Testing Domain Configuration"
echo "==============================="

# Test DNS resolution
echo "Testing DNS resolution..."
if nslookup \$DOMAIN &> /dev/null; then
    echo "✅ DNS resolution: SUCCESS"
else
    echo "❌ DNS resolution: FAILED"
fi

# Test HTTP connection
echo "Testing HTTP connection..."
if curl -s -I http://\$DOMAIN &> /dev/null; then
    echo "✅ HTTP connection: SUCCESS"
else
    echo "❌ HTTP connection: FAILED"
fi

# Test Laravel application
echo "Testing Laravel application..."
if curl -s http://\$DOMAIN | grep -q "ZenaManage"; then
    echo "✅ Laravel application: SUCCESS"
else
    echo "❌ Laravel application: FAILED"
fi

echo "Domain testing completed!"
EOF

chmod +x scripts/test-domain.sh
success "Created domain testing script"

# 10. Clear configuration cache
log "Clearing configuration cache..."
php artisan config:clear
php artisan cache:clear
success "Configuration cache cleared"

# 11. Summary
log ""
log "🌐 Domain Name Setup Summary"
log "============================"
log "✅ Domain configuration created"
log "✅ APP_URL updated to: $APP_URL"
log "✅ Apache virtual host configured"
log "✅ Nginx configuration created"
log "✅ DNS setup guide created"
log "✅ Domain testing script created"
log ""
log "📊 Configuration Details:"
log "- Domain: $DOMAIN_NAME"
log "- Server IP: $SERVER_IP"
log "- Server Port: $SERVER_PORT"
log "- APP_URL: $APP_URL"
log ""
log "🎯 Next Steps:"
log "1. Register domain name with a registrar"
log "2. Configure DNS records (A and CNAME)"
log "3. Wait for DNS propagation (24-48 hours)"
log "4. Test domain resolution: ./scripts/test-domain.sh"
log "5. Configure web server with virtual host"
log "6. Generate SSL certificate"
log ""
log "📁 Configuration Files:"
log "- config/domain.php - Domain configuration"
log "- config/apache-virtual-host.conf - Apache setup"
log "- config/nginx-virtual-host.conf - Nginx setup"
log "- config/dns-setup-guide.md - DNS configuration guide"
log "- scripts/test-domain.sh - Domain testing"
log ""
log "Domain name setup completed at: \$(date)"
log "Log file: $LOG_FILE"
