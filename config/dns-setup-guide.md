# DNS Configuration Guide for zenamanage.com

## Required DNS Records

### A Record
- **Type**: A
- **Name**: @
- **Value**: 192.168.1.100
- **TTL**: 3600

### CNAME Record
- **Type**: CNAME
- **Name**: www
- **Value**: zenamanage.com
- **TTL**: 3600

## Optional DNS Records

### MX Record (for email)
- **Type**: MX
- **Name**: @
- **Value**: mail.zenamanage.com
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
- Test with: nslookup zenamanage.com

## Testing Commands
```bash
# Test DNS resolution
nslookup zenamanage.com
dig zenamanage.com

# Test HTTP connection
curl -I http://zenamanage.com
```
