#!/bin/bash

# SSL Certificate Setup Script
# Dashboard System - SSL Certificate Management

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SSL_DIR="docker/nginx/ssl"
DOMAIN="zenamanage.com"
SUBJECT="/C=VN/ST=Ho_Chi_Minh/L=Ho_Chi_Minh/O=ZenaManage/OU=IT/CN=$DOMAIN"

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Create SSL directory
create_ssl_directory() {
    log_info "Creating SSL directory..."
    mkdir -p "$SSL_DIR"
    log_success "SSL directory created"
}

# Generate self-signed certificate
generate_self_signed() {
    log_info "Generating self-signed certificate..."
    
    # Generate private key
    openssl genrsa -out "$SSL_DIR/zenamanage.key" 2048
    
    # Generate certificate signing request
    openssl req -new -key "$SSL_DIR/zenamanage.key" -out "$SSL_DIR/zenamanage.csr" -subj "$SUBJECT"
    
    # Generate self-signed certificate
    openssl x509 -req -days 365 -in "$SSL_DIR/zenamanage.csr" -signkey "$SSL_DIR/zenamanage.key" -out "$SSL_DIR/zenamanage.crt"
    
    # Set permissions
    chmod 600 "$SSL_DIR/zenamanage.key"
    chmod 644 "$SSL_DIR/zenamanage.crt"
    
    log_success "Self-signed certificate generated"
}

# Generate Let's Encrypt certificate
generate_letsencrypt() {
    log_info "Generating Let's Encrypt certificate..."
    
    # Check if certbot is installed
    if ! command -v certbot &> /dev/null; then
        log_error "Certbot is not installed. Please install certbot first."
        echo "Installation instructions:"
        echo "  Ubuntu/Debian: sudo apt-get install certbot"
        echo "  CentOS/RHEL: sudo yum install certbot"
        echo "  macOS: brew install certbot"
        exit 1
    fi
    
    # Generate certificate
    certbot certonly --standalone -d "$DOMAIN" -d "dashboard.$DOMAIN" -d "api.$DOMAIN" -d "ws.$DOMAIN" --agree-tos --no-eff-email --email "admin@$DOMAIN"
    
    # Copy certificates to SSL directory
    cp "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" "$SSL_DIR/zenamanage.crt"
    cp "/etc/letsencrypt/live/$DOMAIN/privkey.pem" "$SSL_DIR/zenamanage.key"
    
    # Set permissions
    chmod 644 "$SSL_DIR/zenamanage.crt"
    chmod 600 "$SSL_DIR/zenamanage.key"
    
    log_success "Let's Encrypt certificate generated"
}

# Setup certificate renewal
setup_renewal() {
    log_info "Setting up certificate renewal..."
    
    # Create renewal script
    cat > "$SSL_DIR/renew.sh" << 'EOF'
#!/bin/bash

# Certificate renewal script
SSL_DIR="docker/nginx/ssl"
DOMAIN="zenamanage.com"

# Renew certificate
certbot renew --quiet

# Copy renewed certificates
cp "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" "$SSL_DIR/zenamanage.crt"
cp "/etc/letsencrypt/live/$DOMAIN/privkey.pem" "$SSL_DIR/zenamanage.key"

# Reload nginx
docker-compose -f docker-compose.prod.yml exec nginx nginx -s reload

echo "Certificate renewed successfully"
EOF
    
    chmod +x "$SSL_DIR/renew.sh"
    
    # Add to crontab
    (crontab -l 2>/dev/null; echo "0 2 * * * $(pwd)/$SSL_DIR/renew.sh") | crontab -
    
    log_success "Certificate renewal setup completed"
}

# Generate DH parameters
generate_dh_params() {
    log_info "Generating DH parameters..."
    openssl dhparam -out "$SSL_DIR/dhparam.pem" 2048
    chmod 644 "$SSL_DIR/dhparam.pem"
    log_success "DH parameters generated"
}

# Show certificate info
show_certificate_info() {
    if [ -f "$SSL_DIR/zenamanage.crt" ]; then
        log_info "Certificate Information:"
        openssl x509 -in "$SSL_DIR/zenamanage.crt" -text -noout | grep -E "(Subject:|Issuer:|Not Before:|Not After:|DNS:)"
    else
        log_error "Certificate file not found"
    fi
}

# Test SSL configuration
test_ssl() {
    log_info "Testing SSL configuration..."
    
    if [ -f "$SSL_DIR/zenamanage.crt" ] && [ -f "$SSL_DIR/zenamanage.key" ]; then
        # Test certificate
        openssl x509 -in "$SSL_DIR/zenamanage.crt" -text -noout > /dev/null
        log_success "Certificate is valid"
        
        # Test private key
        openssl rsa -in "$SSL_DIR/zenamanage.key" -check > /dev/null
        log_success "Private key is valid"
        
        # Test certificate and key match
        cert_md5=$(openssl x509 -noout -modulus -in "$SSL_DIR/zenamanage.crt" | openssl md5)
        key_md5=$(openssl rsa -noout -modulus -in "$SSL_DIR/zenamanage.key" | openssl md5)
        
        if [ "$cert_md5" = "$key_md5" ]; then
            log_success "Certificate and private key match"
        else
            log_error "Certificate and private key do not match"
        fi
    else
        log_error "Certificate or private key file not found"
    fi
}

# Show help
show_help() {
    echo "SSL Certificate Setup Script for ZenaManage Dashboard"
    echo ""
    echo "Usage: $0 [COMMAND]"
    echo ""
    echo "Commands:"
    echo "  self-signed    Generate self-signed certificate"
    echo "  letsencrypt    Generate Let's Encrypt certificate"
    echo "  renew          Setup certificate renewal"
    echo "  dhparams       Generate DH parameters"
    echo "  info           Show certificate information"
    echo "  test           Test SSL configuration"
    echo "  help           Show this help message"
    echo ""
    echo "Note: Make sure to update the DOMAIN variable in this script"
    echo "      to match your actual domain name."
}

# Main function
main() {
    local command="$1"
    
    case "$command" in
        "self-signed")
            create_ssl_directory
            generate_self_signed
            generate_dh_params
            test_ssl
            show_certificate_info
            ;;
        "letsencrypt")
            create_ssl_directory
            generate_letsencrypt
            generate_dh_params
            setup_renewal
            test_ssl
            show_certificate_info
            ;;
        "renew")
            setup_renewal
            ;;
        "dhparams")
            create_ssl_directory
            generate_dh_params
            ;;
        "info")
            show_certificate_info
            ;;
        "test")
            test_ssl
            ;;
        "help"|"--help"|"-h"|"")
            show_help
            ;;
        *)
            log_error "Unknown command: $command"
            show_help
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
