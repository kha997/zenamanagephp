#!/bin/bash

# ZenaManage Real SSL Certificate Generation Script

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
PROJECT_PATH=$(pwd)
LOG_FILE="$PROJECT_PATH/storage/logs/generate-ssl-$(date +%Y%m%d_%H%M%S).log"

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
log "🔒 Generating Real SSL Certificate"
log "================================="

# 1. Display SSL certificate options
log "📋 SSL Certificate Options:"
log "============================"
log ""
log "🔐 OPTION 1: Let's Encrypt (Free, Automated)"
log "✅ Free SSL certificates"
log "✅ Automatic renewal"
log "✅ Trusted by all browsers"
log "❌ Requires domain to be live"
log "❌ Requires web server access"
log ""
log "🔐 OPTION 2: Self-Signed Certificate (Development)"
log "✅ Works immediately"
log "✅ No external dependencies"
log "❌ Browser security warnings"
log "❌ Not trusted by browsers"
log ""
log "🔐 OPTION 3: Commercial Certificate (Production)"
log "✅ Trusted by all browsers"
log "✅ Professional support"
log "❌ Requires payment"
log "❌ Manual renewal process"
log ""

# 2. Prompt for certificate type
log "🔐 Select SSL Certificate Type:"
log "=============================="

echo "1) Let's Encrypt (Free, Production)"
echo "2) Self-Signed (Development)"
echo "3) Commercial Certificate (Production)"
echo "4) Skip SSL setup"
echo ""

read -p "Enter your choice (1-4): " SSL_CHOICE

case $SSL_CHOICE in
    1)
        SSL_TYPE="letsencrypt"
        log "Selected: Let's Encrypt SSL Certificate"
        ;;
    2)
        SSL_TYPE="selfsigned"
        log "Selected: Self-Signed SSL Certificate"
        ;;
    3)
        SSL_TYPE="commercial"
        log "Selected: Commercial SSL Certificate"
        ;;
    4)
        log "Skipping SSL setup"
        exit 0
        ;;
    *)
        error "Invalid choice. Please select 1-4."
        ;;
esac

# 3. Get domain information
if [ -f "config/domain.php" ]; then
    DOMAIN=$(grep "'domain'" config/domain.php | cut -d "'" -f 4)
    log "Found domain from config: $DOMAIN"
else
    read -p "Enter your domain name (e.g., zenamanage.com): " DOMAIN
    if [ -z "$DOMAIN" ]; then
        error "Domain name cannot be empty"
    fi
fi

# 4. Create SSL directory
log "Creating SSL directory..."
mkdir -p storage/ssl
success "SSL directory created"

# 5. Generate certificate based on type
case $SSL_TYPE in
    "letsencrypt")
        log "🔐 Setting up Let's Encrypt SSL Certificate"
        log "==========================================="
        
        # Check if certbot is installed
        if ! command -v certbot &> /dev/null; then
            log "Installing certbot..."
            if command -v apt &> /dev/null; then
                sudo apt update
                sudo apt install -y certbot python3-certbot-apache
            elif command -v yum &> /dev/null; then
                sudo yum install -y certbot python3-certbot-apache
            else
                error "Please install certbot manually: https://certbot.eff.org/"
            fi
        fi
        
        # Generate Let's Encrypt certificate
        log "Generating Let's Encrypt certificate for $DOMAIN..."
        sudo certbot certonly --webroot -w "$PROJECT_PATH/public" -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos --email "admin@$DOMAIN"
        
        # Copy certificates to project directory
        sudo cp "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" "storage/ssl/server.crt"
        sudo cp "/etc/letsencrypt/live/$DOMAIN/privkey.pem" "storage/ssl/server.key"
        sudo chown $(whoami):$(whoami) "storage/ssl/server.crt" "storage/ssl/server.key"
        
        success "Let's Encrypt certificate generated"
        ;;
        
    "selfsigned")
        log "🔐 Generating Self-Signed SSL Certificate"
        log "========================================="
        
        # Generate private key
        log "Generating private key..."
        openssl genrsa -out storage/ssl/server.key 2048
        success "Private key generated"
        
        # Generate certificate signing request
        log "Generating certificate signing request..."
        openssl req -new -key storage/ssl/server.key -out storage/ssl/server.csr -subj "/C=US/ST=State/L=City/O=ZenaManage/CN=$DOMAIN"
        success "Certificate signing request generated"
        
        # Generate self-signed certificate
        log "Generating self-signed certificate..."
        openssl x509 -req -days 365 -in storage/ssl/server.csr -signkey storage/ssl/server.key -out storage/ssl/server.crt
        success "Self-signed certificate generated"
        
        # Clean up CSR file
        rm storage/ssl/server.csr
        ;;
        
    "commercial")
        log "🔐 Commercial SSL Certificate Setup"
        log "=================================="
        
        # Generate private key
        log "Generating private key..."
        openssl genrsa -out storage/ssl/server.key 2048
        success "Private key generated"
        
        # Generate certificate signing request
        log "Generating certificate signing request..."
        openssl req -new -key storage/ssl/server.key -out storage/ssl/server.csr -subj "/C=US/ST=State/L=City/O=ZenaManage/CN=$DOMAIN"
        success "Certificate signing request generated"
        
        log "📋 Commercial Certificate Instructions:"
        log "======================================"
        log "1. Submit the CSR file to your certificate provider"
        log "2. CSR file location: storage/ssl/server.csr"
        log "3. Download the certificate from your provider"
        log "4. Save it as: storage/ssl/server.crt"
        log "5. Run this script again to complete setup"
        ;;
esac

# 6. Set proper permissions
log "Setting SSL certificate permissions..."
chmod 600 storage/ssl/server.key
chmod 644 storage/ssl/server.crt
success "SSL certificate permissions set"

# 7. Update .env for HTTPS
log "Updating .env for HTTPS..."
sed -i.bak "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env
sed -i.bak 's/SESSION_SECURE_COOKIE=.*/SESSION_SECURE_COOKIE=true/' .env
sed -i.bak 's/COOKIE_SECURE=.*/COOKIE_SECURE=true/' .env
success "Updated .env for HTTPS"

# 8. Update domain configuration
log "Updating domain configuration..."
sed -i.bak "s/'ssl_enabled' => false/'ssl_enabled' => true/" config/domain.php
sed -i.bak "s/'force_https' => false/'force_https' => true/" config/domain.php
success "Updated domain configuration"

# 9. Create SSL-enabled web server configurations
log "Creating SSL-enabled web server configurations..."

# Update Apache configuration
if [ -f "config/apache-virtual-host.conf" ]; then
    log "Updating Apache configuration for SSL..."
    sed -i.bak 's/# <VirtualHost \*:443>/<VirtualHost *:443>/' config/apache-virtual-host.conf
    sed -i.bak 's/#     ServerName/    ServerName/' config/apache-virtual-host.conf
    sed -i.bak 's/#     ServerAlias/    ServerAlias/' config/apache-virtual-host.conf
    sed -i.bak 's/#     DocumentRoot/    DocumentRoot/' config/apache-virtual-host.conf
    sed -i.bak 's/#     SSLEngine on/    SSLEngine on/' config/apache-virtual-host.conf
    sed -i.bak 's/#     SSLCertificateFile/    SSLCertificateFile/' config/apache-virtual-host.conf
    sed -i.bak 's/#     SSLCertificateKeyFile/    SSLCertificateKeyFile/' config/apache-virtual-host.conf
    sed -i.bak 's/#     <Directory/    <Directory/' config/apache-virtual-host.conf
    sed -i.bak 's/#         AllowOverride All/        AllowOverride All/' config/apache-virtual-host.conf
    sed -i.bak 's/#         Require all granted/        Require all granted/' config/apache-virtual-host.conf
    sed -i.bak 's/#     <\/Directory>/    <\/Directory>/' config/apache-virtual-host.conf
    sed -i.bak 's/#     ErrorLog/    ErrorLog/' config/apache-virtual-host.conf
    sed -i.bak 's/#     CustomLog/    CustomLog/' config/apache-virtual-host.conf
    sed -i.bak 's/# <\/VirtualHost>/<\/VirtualHost>/' config/apache-virtual-host.conf
    success "Updated Apache configuration for SSL"
fi

# Update Nginx configuration
if [ -f "config/nginx-virtual-host.conf" ]; then
    log "Updating Nginx configuration for SSL..."
    sed -i.bak 's/# server {/server {/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     listen 443 ssl http2;/    listen 443 ssl http2;/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     server_name/    server_name/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     root/    root/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     index/    index/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     ssl_certificate/    ssl_certificate/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     ssl_certificate_key/    ssl_certificate_key/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     ssl_protocols/    ssl_protocols/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     ssl_ciphers/    ssl_ciphers/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     ssl_prefer_server_ciphers/    ssl_prefer_server_ciphers/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     add_header Strict-Transport-Security/    add_header Strict-Transport-Security/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     add_header X-Content-Type-Options/    add_header X-Content-Type-Options/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     add_header X-Frame-Options/    add_header X-Frame-Options/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     add_header X-XSS-Protection/    add_header X-XSS-Protection/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     add_header Referrer-Policy/    add_header Referrer-Policy/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     location ~ \\.php$ {/    location ~ \\.php$ {/' config/nginx-virtual-host.conf
    sed -i.bak 's/#         fastcgi_pass/        fastcgi_pass/' config/nginx-virtual-host.conf
    sed -i.bak 's/#         fastcgi_index/        fastcgi_index/' config/nginx-virtual-host.conf
    sed -i.bak 's/#         fastcgi_param/        fastcgi_param/' config/nginx-virtual-host.conf
    sed -i.bak 's/#         include/        include/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     }/    }/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     location ~\\* \\.(js|css|png|jpg|jpeg|gif|ico|svg)$ {/    location ~\\* \\.(js|css|png|jpg|jpeg|gif|ico|svg)$ {/' config/nginx-virtual-host.conf
    sed -i.bak 's/#         expires/        expires/' config/nginx-virtual-host.conf
    sed -i.bak 's/#         add_header Cache-Control/        add_header Cache-Control/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     }/    }/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     location \/ {/    location \/ {/' config/nginx-virtual-host.conf
    sed -i.bak 's/#         try_files/        try_files/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     }/    }/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     access_log/    access_log/' config/nginx-virtual-host.conf
    sed -i.bak 's/#     error_log/    error_log/' config/nginx-virtual-host.conf
    sed -i.bak 's/# }/}/' config/nginx-virtual-host.conf
    success "Updated Nginx configuration for SSL"
fi

# 10. Create SSL testing script
log "Creating SSL testing script..."
cat > scripts/test-ssl.sh << EOF
#!/bin/bash

# SSL Testing Script

DOMAIN="$DOMAIN"

echo "🔒 Testing SSL Certificate"
echo "========================"

# Test SSL certificate
echo "Testing SSL certificate..."
if openssl x509 -in storage/ssl/server.crt -text -noout &> /dev/null; then
    echo "✅ SSL certificate: VALID"
else
    echo "❌ SSL certificate: INVALID"
fi

# Test private key
echo "Testing private key..."
if openssl rsa -in storage/ssl/server.key -check &> /dev/null; then
    echo "✅ Private key: VALID"
else
    echo "❌ Private key: INVALID"
fi

# Test certificate and key match
echo "Testing certificate and key match..."
CERT_MODULUS=\$(openssl x509 -noout -modulus -in storage/ssl/server.crt | openssl md5)
KEY_MODULUS=\$(openssl rsa -noout -modulus -in storage/ssl/server.key | openssl md5)

if [ "\$CERT_MODULUS" = "\$KEY_MODULUS" ]; then
    echo "✅ Certificate and key: MATCH"
else
    echo "❌ Certificate and key: DO NOT MATCH"
fi

# Test HTTPS connection
echo "Testing HTTPS connection..."
if curl -s -I https://\$DOMAIN &> /dev/null; then
    echo "✅ HTTPS connection: SUCCESS"
else
    echo "❌ HTTPS connection: FAILED"
fi

echo "SSL testing completed!"
EOF

chmod +x scripts/test-ssl.sh
success "Created SSL testing script"

# 11. Clear configuration cache
log "Clearing configuration cache..."
php artisan config:clear
php artisan cache:clear
success "Configuration cache cleared"

# 12. Summary
log ""
log "🔒 SSL Certificate Generation Summary"
log "====================================="
log "✅ SSL certificate generated ($SSL_TYPE)"
log "✅ Private key generated"
log "✅ APP_URL updated to HTTPS"
log "✅ Secure cookies enabled"
log "✅ Web server configurations updated"
log "✅ SSL testing script created"
log ""
log "📊 Certificate Details:"
log "- Type: $SSL_TYPE"
log "- Domain: $DOMAIN"
log "- Certificate: storage/ssl/server.crt"
log "- Private Key: storage/ssl/server.key"
log "- APP_URL: https://$DOMAIN"
log ""
log "🎯 Next Steps:"
log "1. Configure web server with SSL virtual host"
log "2. Test SSL certificate: ./scripts/test-ssl.sh"
log "3. Verify HTTPS connection"
log "4. Test HTTPS redirects"
log "5. Monitor SSL certificate expiration"
log ""
log "📁 Configuration Files:"
log "- storage/ssl/server.crt - SSL certificate"
log "- storage/ssl/server.key - Private key"
log "- config/apache-virtual-host.conf - Apache SSL config"
log "- config/nginx-virtual-host.conf - Nginx SSL config"
log "- scripts/test-ssl.sh - SSL testing"
log ""
log "SSL certificate generation completed at: \$(date)"
log "Log file: $LOG_FILE"
