#!/bin/bash

# ZenaManage Domain to Server Pointing Script

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
PROJECT_PATH=$(pwd)
LOG_FILE="$PROJECT_PATH/storage/logs/point-domain-$(date +%Y%m%d_%H%M%S).log"

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
log "🌐 Pointing Domain to Production Server"
log "======================================="

# 1. Display domain pointing guide
log "📋 Domain Pointing Guide:"
log "========================"
log ""
log "🌍 STEP 1: DNS Configuration"
log "1. Log into your domain registrar (GoDaddy, Namecheap, etc.)"
log "2. Navigate to DNS management"
log "3. Update A record to point to your server IP"
log "4. Update CNAME record for www subdomain"
log ""
log "🔧 STEP 2: Server Configuration"
log "1. Ensure web server is running"
log "2. Verify virtual host is configured"
log "3. Test HTTP/HTTPS connections"
log "4. Monitor server logs"
log ""
log "⏱️  STEP 3: DNS Propagation"
log "1. DNS changes can take 24-48 hours"
log "2. Use DNS checker tools to verify"
log "3. Test from different locations"
log "4. Monitor for any issues"
log ""

# 2. Get domain and server information
if [ -f "config/domain.php" ]; then
    DOMAIN=$(grep "'domain'" config/domain.php | cut -d "'" -f 4)
    SERVER_IP=$(grep "'server_ip'" config/domain.php | cut -d "'" -f 4)
    log "Found domain from config: $DOMAIN"
    log "Found server IP from config: $SERVER_IP"
else
    read -p "Enter your domain name (e.g., zenamanage.com): " DOMAIN
    if [ -z "$DOMAIN" ]; then
        error "Domain name cannot be empty"
    fi
    
    read -p "Enter your server IP address: " SERVER_IP
    if [ -z "$SERVER_IP" ]; then
        error "Server IP address cannot be empty"
    fi
fi

# 3. Create DNS configuration template
log "Creating DNS configuration template..."
cat > config/dns-records.txt << EOF
# DNS Records for $DOMAIN
# Server IP: $SERVER_IP

# A Record (Root Domain)
Type: A
Name: @
Value: $SERVER_IP
TTL: 3600

# CNAME Record (WWW Subdomain)
Type: CNAME
Name: www
Value: $DOMAIN
TTL: 3600

# Optional: MX Record (Email)
Type: MX
Name: @
Value: mail.$DOMAIN
Priority: 10
TTL: 3600

# Optional: TXT Record (SPF)
Type: TXT
Name: @
Value: "v=spf1 include:_spf.google.com ~all"
TTL: 3600

# Optional: TXT Record (DKIM)
Type: TXT
Name: default._domainkey
Value: "v=DKIM1; k=rsa; p=YOUR_DKIM_PUBLIC_KEY"
TTL: 3600
EOF
success "DNS configuration template created"

# 4. Create DNS propagation checker script
log "Creating DNS propagation checker script..."
cat > scripts/check-dns-propagation.sh << EOF
#!/bin/bash

# DNS Propagation Checker Script

DOMAIN="$DOMAIN"
SERVER_IP="$SERVER_IP"

echo "🌐 Checking DNS Propagation for $DOMAIN"
echo "======================================"

# Check DNS resolution
echo "Checking DNS resolution..."
RESOLVED_IP=\$(nslookup \$DOMAIN | grep "Address:" | tail -1 | awk '{print \$2}')
if [ "\$RESOLVED_IP" = "\$SERVER_IP" ]; then
    echo "✅ DNS resolution: CORRECT (\$RESOLVED_IP)"
else
    echo "❌ DNS resolution: INCORRECT (Expected: \$SERVER_IP, Got: \$RESOLVED_IP)"
fi

# Check www subdomain
echo "Checking www subdomain..."
WWW_RESOLVED_IP=\$(nslookup www.\$DOMAIN | grep "Address:" | tail -1 | awk '{print \$2}')
if [ "\$WWW_RESOLVED_IP" = "\$SERVER_IP" ]; then
    echo "✅ WWW subdomain: CORRECT (\$WWW_RESOLVED_IP)"
else
    echo "❌ WWW subdomain: INCORRECT (Expected: \$SERVER_IP, Got: \$WWW_RESOLVED_IP)"
fi

# Check HTTP connection
echo "Checking HTTP connection..."
if curl -s -I http://\$DOMAIN &> /dev/null; then
    echo "✅ HTTP connection: SUCCESS"
else
    echo "❌ HTTP connection: FAILED"
fi

# Check HTTPS connection
echo "Checking HTTPS connection..."
if curl -s -I https://\$DOMAIN &> /dev/null; then
    echo "✅ HTTPS connection: SUCCESS"
else
    echo "❌ HTTPS connection: FAILED"
fi

# Check Laravel application
echo "Checking Laravel application..."
if curl -s http://\$DOMAIN | grep -q "ZenaManage"; then
    echo "✅ Laravel application: SUCCESS"
else
    echo "❌ Laravel application: FAILED"
fi

echo "DNS propagation check completed!"
EOF

chmod +x scripts/check-dns-propagation.sh
success "DNS propagation checker script created"

# 5. Create domain monitoring script
log "Creating domain monitoring script..."
cat > scripts/monitor-domain.sh << EOF
#!/bin/bash

# Domain Monitoring Script

DOMAIN="$DOMAIN"
SERVER_IP="$SERVER_IP"
LOG_FILE="storage/logs/domain-monitor-\$(date +%Y%m%d_%H%M%S).log"

echo "🌐 Monitoring Domain: $DOMAIN"
echo "=============================="
echo "Log file: \$LOG_FILE"

# Function to log with timestamp
log() {
    echo -e "[\$(date +'%Y-%m-%d %H:%M:%S')] \$1" | tee -a "\$LOG_FILE"
}

# Monitor DNS resolution
log "Checking DNS resolution..."
RESOLVED_IP=\$(nslookup \$DOMAIN | grep "Address:" | tail -1 | awk '{print \$2}')
if [ "\$RESOLVED_IP" = "\$SERVER_IP" ]; then
    log "✅ DNS resolution: CORRECT (\$RESOLVED_IP)"
else
    log "❌ DNS resolution: INCORRECT (Expected: \$SERVER_IP, Got: \$RESOLVED_IP)"
fi

# Monitor HTTP connection
log "Checking HTTP connection..."
if curl -s -I http://\$DOMAIN &> /dev/null; then
    log "✅ HTTP connection: SUCCESS"
else
    log "❌ HTTP connection: FAILED"
fi

# Monitor HTTPS connection
log "Checking HTTPS connection..."
if curl -s -I https://\$DOMAIN &> /dev/null; then
    log "✅ HTTPS connection: SUCCESS"
else
    log "❌ HTTPS connection: FAILED"
fi

# Monitor Laravel application
log "Checking Laravel application..."
if curl -s http://\$DOMAIN | grep -q "ZenaManage"; then
    log "✅ Laravel application: SUCCESS"
else
    log "❌ Laravel application: FAILED"
fi

# Monitor web server status
log "Checking web server status..."
if systemctl is-active --quiet apache2; then
    log "✅ Apache: RUNNING"
elif systemctl is-active --quiet nginx; then
    log "✅ Nginx: RUNNING"
else
    log "❌ Web server: NOT RUNNING"
fi

log "Domain monitoring completed!"
EOF

chmod +x scripts/monitor-domain.sh
success "Domain monitoring script created"

# 6. Create domain setup checklist
log "Creating domain setup checklist..."
cat > config/domain-setup-checklist.md << EOF
# Domain Setup Checklist for $DOMAIN

## ✅ Pre-Setup Requirements
- [ ] Domain registered with a registrar
- [ ] Server IP address: $SERVER_IP
- [ ] Web server installed and configured
- [ ] SSL certificate generated (if using HTTPS)
- [ ] Laravel application deployed

## ✅ DNS Configuration
- [ ] A record: @ → $SERVER_IP
- [ ] CNAME record: www → $DOMAIN
- [ ] MX record: @ → mail.$DOMAIN (optional)
- [ ] TXT record: SPF record (optional)
- [ ] TXT record: DKIM record (optional)

## ✅ Server Configuration
- [ ] Web server virtual host configured
- [ ] SSL certificate installed (if using HTTPS)
- [ ] Firewall configured (ports 80, 443)
- [ ] Laravel application accessible
- [ ] File permissions set correctly

## ✅ Testing
- [ ] DNS resolution working
- [ ] HTTP connection successful
- [ ] HTTPS connection successful (if SSL enabled)
- [ ] Laravel application loading
- [ ] Email functionality working

## ✅ Monitoring
- [ ] DNS propagation checker script
- [ ] Domain monitoring script
- [ ] Web server monitoring
- [ ] SSL certificate expiration monitoring
- [ ] Application performance monitoring

## 📋 DNS Records Template
\`\`\`
Type: A
Name: @
Value: $SERVER_IP
TTL: 3600

Type: CNAME
Name: www
Value: $DOMAIN
TTL: 3600
\`\`\`

## 🔍 Testing Commands
\`\`\`bash
# Check DNS resolution
nslookup $DOMAIN

# Check HTTP connection
curl -I http://$DOMAIN

# Check HTTPS connection
curl -I https://$DOMAIN

# Run domain tests
./scripts/check-dns-propagation.sh
./scripts/monitor-domain.sh
\`\`\`

## ⏱️ Timeline
- DNS propagation: 24-48 hours
- Global propagation: Up to 72 hours
- Testing and verification: 1-2 hours
- Monitoring setup: 30 minutes

## 🚨 Troubleshooting
- DNS not resolving: Check A record configuration
- HTTP not working: Check web server configuration
- HTTPS not working: Check SSL certificate
- Application not loading: Check Laravel configuration
- Email not working: Check MX records and SMTP
EOF
success "Domain setup checklist created"

# 7. Create automated domain testing script
log "Creating automated domain testing script..."
cat > scripts/automated-domain-test.sh << EOF
#!/bin/bash

# Automated Domain Testing Script

DOMAIN="$DOMAIN"
SERVER_IP="$SERVER_IP"
TEST_COUNT=10
INTERVAL=60

echo "🤖 Automated Domain Testing"
echo "==========================="
echo "Domain: \$DOMAIN"
echo "Server IP: \$SERVER_IP"
echo "Test Count: \$TEST_COUNT"
echo "Interval: \$INTERVAL seconds"
echo ""

for i in \$(seq 1 \$TEST_COUNT); do
    echo "Test \$i/\$TEST_COUNT - \$(date)"
    echo "================================"
    
    # Check DNS resolution
    RESOLVED_IP=\$(nslookup \$DOMAIN | grep "Address:" | tail -1 | awk '{print \$2}')
    if [ "\$RESOLVED_IP" = "\$SERVER_IP" ]; then
        echo "✅ DNS: CORRECT (\$RESOLVED_IP)"
    else
        echo "❌ DNS: INCORRECT (Expected: \$SERVER_IP, Got: \$RESOLVED_IP)"
    fi
    
    # Check HTTP connection
    if curl -s -I http://\$DOMAIN &> /dev/null; then
        echo "✅ HTTP: SUCCESS"
    else
        echo "❌ HTTP: FAILED"
    fi
    
    # Check HTTPS connection
    if curl -s -I https://\$DOMAIN &> /dev/null; then
        echo "✅ HTTPS: SUCCESS"
    else
        echo "❌ HTTPS: FAILED"
    fi
    
    echo ""
    
    if [ \$i -lt \$TEST_COUNT ]; then
        echo "Waiting \$INTERVAL seconds..."
        sleep \$INTERVAL
    fi
done

echo "Automated testing completed!"
EOF

chmod +x scripts/automated-domain-test.sh
success "Automated domain testing script created"

# 8. Summary
log ""
log "🌐 Domain to Server Pointing Summary"
log "===================================="
log "✅ DNS configuration template created"
log "✅ DNS propagation checker created"
log "✅ Domain monitoring script created"
log "✅ Domain setup checklist created"
log "✅ Automated testing script created"
log ""
log "📊 Configuration Details:"
log "- Domain: $DOMAIN"
log "- Server IP: $SERVER_IP"
log "- DNS Template: config/dns-records.txt"
log "- Checklist: config/domain-setup-checklist.md"
log ""
log "🎯 Next Steps:"
log "1. Configure DNS records at your registrar"
log "2. Wait for DNS propagation (24-48 hours)"
log "3. Test domain resolution: ./scripts/check-dns-propagation.sh"
log "4. Monitor domain: ./scripts/monitor-domain.sh"
log "5. Run automated tests: ./scripts/automated-domain-test.sh"
log ""
log "📁 DNS Records to Configure:"
log "- A Record: @ → $SERVER_IP"
log "- CNAME Record: www → $DOMAIN"
log ""
log "📁 Testing Commands:"
log "- Check DNS: nslookup $DOMAIN"
log "- Test HTTP: curl -I http://$DOMAIN"
log "- Test HTTPS: curl -I https://$DOMAIN"
log "- Run tests: ./scripts/check-dns-propagation.sh"
log ""
log "📁 Configuration Files:"
log "- config/dns-records.txt - DNS records template"
log "- config/domain-setup-checklist.md - Setup checklist"
log "- scripts/check-dns-propagation.sh - DNS checker"
log "- scripts/monitor-domain.sh - Domain monitoring"
log "- scripts/automated-domain-test.sh - Automated testing"
log ""
log "Domain pointing setup completed at: \$(date)"
log "Log file: $LOG_FILE"
