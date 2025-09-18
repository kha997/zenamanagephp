# Domain Setup Checklist for zenamanage.com

## ✅ Pre-Setup Requirements
- [ ] Domain registered with a registrar
- [ ] Server IP address: 192.168.1.100
- [ ] Web server installed and configured
- [ ] SSL certificate generated (if using HTTPS)
- [ ] Laravel application deployed

## ✅ DNS Configuration
- [ ] A record: @ → 192.168.1.100
- [ ] CNAME record: www → zenamanage.com
- [ ] MX record: @ → mail.zenamanage.com (optional)
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
```
Type: A
Name: @
Value: 192.168.1.100
TTL: 3600

Type: CNAME
Name: www
Value: zenamanage.com
TTL: 3600
```

## 🔍 Testing Commands
```bash
# Check DNS resolution
nslookup zenamanage.com

# Check HTTP connection
curl -I http://zenamanage.com

# Check HTTPS connection
curl -I https://zenamanage.com

# Run domain tests
./scripts/check-dns-propagation.sh
./scripts/monitor-domain.sh
```

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
