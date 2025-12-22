#!/bin/bash

# ZenaManage Automated Security Audit Script
# Runs daily security audits and reports findings

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOG_FILE="$PROJECT_ROOT/storage/logs/security-audit.log"
ALERT_EMAIL="security@zenamanage.com"
SLACK_WEBHOOK="${SLACK_SECURITY_WEBHOOK:-}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_FILE")"

# Start audit
log_info "Starting automated security audit - $(date)"

cd "$PROJECT_ROOT"

# Initialize counters
TOTAL_ISSUES=0
CRITICAL_ISSUES=0
HIGH_ISSUES=0
MEDIUM_ISSUES=0
LOW_ISSUES=0

# Function to count vulnerabilities
count_vulnerabilities() {
    local output="$1"
    local type="$2"
    
    if [[ "$output" == *"found 0 vulnerabilities"* ]]; then
        log_success "$type audit: No vulnerabilities found"
        return 0
    elif [[ "$output" == *"No security vulnerability advisories found"* ]]; then
        log_success "$type audit: No vulnerabilities found"
        return 0
    else
        # Count vulnerabilities by severity
        local critical=$(echo "$output" | grep -c "critical" || echo "0")
        local high=$(echo "$output" | grep -c "high" || echo "0")
        local moderate=$(echo "$output" | grep -c "moderate" || echo "0")
        local low=$(echo "$output" | grep -c "low" || echo "0")
        
        CRITICAL_ISSUES=$((CRITICAL_ISSUES + critical))
        HIGH_ISSUES=$((HIGH_ISSUES + high))
        MEDIUM_ISSUES=$((MEDIUM_ISSUES + moderate))
        LOW_ISSUES=$((LOW_ISSUES + low))
        
        TOTAL_ISSUES=$((TOTAL_ISSUES + critical + high + moderate + low))
        
        log_warning "$type audit: Found $((critical + high + moderate + low)) vulnerabilities"
        log_warning "  Critical: $critical, High: $high, Medium: $moderate, Low: $low"
        
        return $((critical + high + moderate + low))
    fi
}

# 1. NPM Security Audit
log_info "Running npm security audit..."
NPM_OUTPUT=$(npm audit --audit-level=moderate 2>&1)
NPM_EXIT_CODE=$?

if [ $NPM_EXIT_CODE -eq 0 ]; then
    log_success "NPM audit: No vulnerabilities found"
else
    count_vulnerabilities "$NPM_OUTPUT" "NPM"
fi

# 2. Composer Security Audit
log_info "Running composer security audit..."
COMPOSER_OUTPUT=$(composer audit 2>&1)
COMPOSER_EXIT_CODE=$?

if [ $COMPOSER_EXIT_CODE -eq 0 ]; then
    log_success "Composer audit: No vulnerabilities found"
else
    count_vulnerabilities "$COMPOSER_OUTPUT" "Composer"
fi

# 3. Check for outdated packages
log_info "Checking for outdated packages..."

# NPM outdated
NPM_OUTDATED_COUNT=$(npm outdated 2>/dev/null | wc -l || echo "0")
NPM_OUTDATED_COUNT=$((NPM_OUTDATED_COUNT - 1)) # Subtract header line

if [ "$NPM_OUTDATED_COUNT" -gt 0 ]; then
    log_warning "NPM: $NPM_OUTDATED_COUNT packages are outdated"
else
    log_success "NPM: All packages are up to date"
fi

# Composer outdated
COMPOSER_OUTDATED_COUNT=$(composer outdated --direct 2>/dev/null | wc -l || echo "0")
COMPOSER_OUTDATED_COUNT=$((COMPOSER_OUTDATED_COUNT - 1)) # Subtract header line

if [ "$COMPOSER_OUTDATED_COUNT" -gt 0 ]; then
    log_warning "Composer: $COMPOSER_OUTDATED_COUNT packages are outdated"
else
    log_success "Composer: All packages are up to date"
fi

# 4. Check for known security issues in dependencies
log_info "Checking for known security issues..."

# Check for specific known vulnerabilities
KNOWN_VULNS=0

# Check for specific packages with known issues
if npm list | grep -q "esbuild.*0\.24\.[0-2]"; then
    log_error "Known vulnerability: esbuild <=0.24.2"
    KNOWN_VULNS=$((KNOWN_VULNS + 1))
fi

if composer show laravel/framework | grep -q "v9\."; then
    log_error "Known vulnerability: Laravel v9.x has CVE-2025-27515"
    KNOWN_VULNS=$((KNOWN_VULNS + 1))
fi

# 5. Generate security report
log_info "Generating security report..."

REPORT_FILE="$PROJECT_ROOT/storage/logs/security-report-$(date +%Y%m%d).json"

cat > "$REPORT_FILE" << EOF
{
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "audit_type": "automated_security_audit",
  "summary": {
    "total_vulnerabilities": $TOTAL_ISSUES,
    "critical": $CRITICAL_ISSUES,
    "high": $HIGH_ISSUES,
    "medium": $MEDIUM_ISSUES,
    "low": $LOW_ISSUES,
    "known_vulnerabilities": $KNOWN_VULNS,
    "npm_outdated": $NPM_OUTDATED_COUNT,
    "composer_outdated": $COMPOSER_OUTDATED_COUNT
  },
  "npm_audit": {
    "exit_code": $NPM_EXIT_CODE,
    "output": "$(echo "$NPM_OUTPUT" | sed 's/"/\\"/g' | tr '\n' ' ')"
  },
  "composer_audit": {
    "exit_code": $COMPOSER_EXIT_CODE,
    "output": "$(echo "$COMPOSER_OUTPUT" | sed 's/"/\\"/g' | tr '\n' ' ')"
  },
  "recommendations": [
    $(if [ $TOTAL_ISSUES -gt 0 ]; then echo '"Run npm audit fix --force and composer update to resolve vulnerabilities"'; fi)
    $(if [ $NPM_OUTDATED_COUNT -gt 0 ]; then echo ',"Update outdated npm packages"'; fi)
    $(if [ $COMPOSER_OUTDATED_COUNT -gt 0 ]; then echo ',"Update outdated composer packages"'; fi)
    $(if [ $KNOWN_VULNS -gt 0 ]; then echo ',"Address known security vulnerabilities immediately"'; fi)
  ]
}
EOF

# 6. Send alerts if critical issues found
if [ $CRITICAL_ISSUES -gt 0 ] || [ $KNOWN_VULNS -gt 0 ]; then
    log_error "CRITICAL SECURITY ISSUES FOUND!"
    
    # Send email alert (if configured)
    if [ -n "$ALERT_EMAIL" ]; then
        echo "Critical security vulnerabilities detected in ZenaManage:
        
Total Issues: $TOTAL_ISSUES
Critical: $CRITICAL_ISSUES
High: $HIGH_ISSUES
Medium: $MEDIUM_ISSUES
Low: $LOW_ISSUES
Known Vulnerabilities: $KNOWN_VULNS

Please review the security report: $REPORT_FILE
Log file: $LOG_FILE

Generated at: $(date)" | mail -s "CRITICAL: Security Vulnerabilities Detected" "$ALERT_EMAIL"
    fi
    
    # Send Slack alert (if configured)
    if [ -n "$SLACK_WEBHOOK" ]; then
        curl -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"🚨 CRITICAL: Security vulnerabilities detected in ZenaManage\\n\\nTotal Issues: $TOTAL_ISSUES\\nCritical: $CRITICAL_ISSUES\\nHigh: $HIGH_ISSUES\\nMedium: $MEDIUM_ISSUES\\nLow: $LOW_ISSUES\\nKnown Vulnerabilities: $KNOWN_VULNS\\n\\nPlease review immediately!\"}" \
            "$SLACK_WEBHOOK"
    fi
fi

# 7. Summary
log_info "Security audit completed"
log_info "Total vulnerabilities: $TOTAL_ISSUES"
log_info "Critical: $CRITICAL_ISSUES, High: $HIGH_ISSUES, Medium: $MEDIUM_ISSUES, Low: $LOW_ISSUES"
log_info "Known vulnerabilities: $KNOWN_VULNS"
log_info "Outdated packages: NPM $NPM_OUTDATED_COUNT, Composer $COMPOSER_OUTDATED_COUNT"
log_info "Report saved to: $REPORT_FILE"

# Exit with appropriate code
if [ $CRITICAL_ISSUES -gt 0 ] || [ $KNOWN_VULNS -gt 0 ]; then
    exit 1
elif [ $TOTAL_ISSUES -gt 0 ]; then
    exit 2
else
    exit 0
fi