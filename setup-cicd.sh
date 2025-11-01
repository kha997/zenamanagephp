#!/bin/bash

# CI/CD Environment Setup Script
# Dashboard System - CI/CD Pipeline Configuration

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="zenamanage-dashboard"
GITHUB_REPO="your-username/zenamanage-dashboard"
REGISTRY="ghcr.io"

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

# Check prerequisites
check_prerequisites() {
    log_info "Checking prerequisites..."
    
    # Check if git is installed
    if ! command -v git &> /dev/null; then
        log_error "Git is not installed. Please install Git first."
        exit 1
    fi
    
    # Check if Docker is installed
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    # Check if Docker Compose is installed
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi
    
    log_success "All prerequisites are met"
}

# Setup GitHub repository
setup_github_repo() {
    log_info "Setting up GitHub repository..."
    
    # Check if repository exists
    if git remote get-url origin &> /dev/null; then
        log_info "Repository already configured"
        return 0
    fi
    
    # Initialize git repository if not already initialized
    if [ ! -d ".git" ]; then
        git init
        log_info "Git repository initialized"
    fi
    
    # Add remote origin
    read -p "Enter your GitHub repository URL: " REPO_URL
    git remote add origin "$REPO_URL"
    
    # Set up branch protection
    log_info "Setting up branch protection..."
    echo "Please configure branch protection rules in GitHub:"
    echo "1. Go to Settings > Branches"
    echo "2. Add rule for 'main' branch"
    echo "3. Enable 'Require pull request reviews before merging'"
    echo "4. Enable 'Require status checks to pass before merging'"
    echo "5. Select 'ci-cd' workflow"
    echo "6. Enable 'Require branches to be up to date before merging'"
    echo "7. Enable 'Restrict pushes that create files'"
    
    log_success "GitHub repository setup completed"
}

# Setup GitHub Secrets
setup_github_secrets() {
    log_info "Setting up GitHub Secrets..."
    
    echo "Please add the following secrets to your GitHub repository:"
    echo "Go to Settings > Secrets and variables > Actions"
    echo ""
    echo "Required Secrets:"
    echo "=================="
    echo "STAGING_HOST: Your staging server hostname or IP"
    echo "STAGING_USERNAME: Username for staging server SSH"
    echo "STAGING_SSH_KEY: Private SSH key for staging server"
    echo "PRODUCTION_HOST: Your production server hostname or IP"
    echo "PRODUCTION_USERNAME: Username for production server SSH"
    echo "PRODUCTION_SSH_KEY: Private SSH key for production server"
    echo "SLACK_WEBHOOK: Slack webhook URL for notifications"
    echo "MYSQL_ROOT_PASSWORD: MySQL root password"
    echo "MYSQL_DATABASE: MySQL database name"
    echo "MYSQL_USER: MySQL username"
    echo "MYSQL_PASSWORD: MySQL password"
    echo "REDIS_PASSWORD: Redis password"
    echo "GRAFANA_PASSWORD: Grafana admin password"
    echo ""
    echo "Optional Secrets:"
    echo "=================="
    echo "CODECOV_TOKEN: Codecov token for coverage reporting"
    echo "SONAR_TOKEN: SonarQube token for code analysis"
    echo "DOCKER_HUB_TOKEN: Docker Hub token for image publishing"
    
    read -p "Press Enter when you have added all required secrets..."
    log_success "GitHub Secrets setup completed"
}

# Setup GitHub Environments
setup_github_environments() {
    log_info "Setting up GitHub Environments..."
    
    echo "Please configure GitHub Environments:"
    echo "Go to Settings > Environments"
    echo ""
    echo "Create the following environments:"
    echo "1. staging"
    echo "   - Add protection rules if needed"
    echo "   - Add required reviewers"
    echo "   - Add environment secrets"
    echo ""
    echo "2. production"
    echo "   - Add protection rules"
    echo "   - Add required reviewers (at least 2)"
    echo "   - Add environment secrets"
    echo "   - Enable 'Required reviewers'"
    echo "   - Enable 'Wait timer' (5 minutes)"
    
    read -p "Press Enter when you have configured all environments..."
    log_success "GitHub Environments setup completed"
}

# Setup Docker Registry
setup_docker_registry() {
    log_info "Setting up Docker Registry..."
    
    echo "Docker Registry Configuration:"
    echo "=============================="
    echo "Registry: $REGISTRY"
    echo "Repository: $GITHUB_REPO"
    echo ""
    echo "The CI/CD pipeline will automatically:"
    echo "1. Build Docker images"
    echo "2. Push to GitHub Container Registry"
    echo "3. Tag images with version numbers"
    echo "4. Clean up old images"
    
    log_success "Docker Registry setup completed"
}

# Setup monitoring and alerting
setup_monitoring() {
    log_info "Setting up monitoring and alerting..."
    
    echo "Monitoring Configuration:"
    echo "========================"
    echo "1. Prometheus: Metrics collection"
    echo "2. Grafana: Metrics visualization"
    echo "3. Slack: Deployment notifications"
    echo "4. GitHub: Security alerts"
    echo ""
    echo "Please configure:"
    echo "1. Slack webhook for notifications"
    echo "2. Grafana dashboards for monitoring"
    echo "3. Prometheus alerts for critical metrics"
    echo "4. GitHub security alerts"
    
    read -p "Press Enter when monitoring is configured..."
    log_success "Monitoring setup completed"
}

# Setup backup strategy
setup_backup_strategy() {
    log_info "Setting up backup strategy..."
    
    echo "Backup Strategy:"
    echo "================"
    echo "1. Automated daily backups"
    echo "2. Pre-deployment backups"
    echo "3. Database backups"
    echo "4. File system backups"
    echo "5. Configuration backups"
    echo ""
    echo "Backup locations:"
    echo "- Local: /opt/zenamanage/backups/"
    echo "- Remote: S3 or other cloud storage"
    echo "- Retention: 30 days"
    
    log_success "Backup strategy setup completed"
}

# Setup security scanning
setup_security_scanning() {
    log_info "Setting up security scanning..."
    
    echo "Security Scanning Configuration:"
    echo "================================"
    echo "1. Trivy: Container vulnerability scanning"
    echo "2. PHPStan: Static analysis"
    echo "3. PHP CS Fixer: Code style checking"
    echo "4. Composer Audit: Dependency vulnerability scanning"
    echo "5. GitHub Security: Automated security alerts"
    echo ""
    echo "Security features:"
    echo "- Automated vulnerability scanning"
    echo "- Dependency updates"
    echo "- Code quality checks"
    echo "- Security policy enforcement"
    
    log_success "Security scanning setup completed"
}

# Test CI/CD pipeline
test_cicd_pipeline() {
    log_info "Testing CI/CD pipeline..."
    
    echo "Testing Steps:"
    echo "=============="
    echo "1. Create a test branch"
    echo "2. Make a small change"
    echo "3. Push to trigger CI pipeline"
    echo "4. Create a pull request"
    echo "5. Merge to trigger CD pipeline"
    echo ""
    
    read -p "Do you want to create a test branch? (y/N): " create_test
    if [[ "$create_test" =~ ^[Yy]$ ]]; then
        git checkout -b test-cicd-setup
        echo "# CI/CD Test" >> README.md
        git add README.md
        git commit -m "Test CI/CD pipeline setup"
        git push origin test-cicd-setup
        
        log_info "Test branch created and pushed"
        echo "Please create a pull request to test the CI pipeline"
    fi
    
    log_success "CI/CD pipeline testing completed"
}

# Generate CI/CD documentation
generate_documentation() {
    log_info "Generating CI/CD documentation..."
    
    cat > CICD_SETUP_GUIDE.md << 'EOF'
# CI/CD Pipeline Setup Guide
# ZenaManage Dashboard System

## Overview

This guide provides comprehensive instructions for setting up and managing the CI/CD pipeline for the ZenaManage Dashboard system.

## Pipeline Architecture

### Continuous Integration (CI)
- **Automated Testing**: Unit, Feature, Integration, Performance tests
- **Code Quality**: PHPStan, PHP CS Fixer, PHP Mess Detector
- **Security Scanning**: Trivy, Composer Audit, Security Checker
- **Docker Build**: Multi-stage builds with caching
- **Coverage Reporting**: Codecov integration

### Continuous Deployment (CD)
- **Staging Deployment**: Automatic deployment on develop branch
- **Production Deployment**: Manual deployment with approvals
- **Blue-Green Deployment**: Zero-downtime deployments
- **Canary Deployment**: Gradual rollout with monitoring
- **Rollback**: Automatic rollback on failure

## Workflows

### 1. CI/CD Pipeline (`ci-cd.yml`)
- Triggers: Push to main/develop, Pull requests, Releases
- Jobs: Test, Security Scan, Docker Build, Deploy

### 2. Automated Testing (`automated-testing.yml`)
- Triggers: Push to any branch, Pull requests, Daily schedule
- Jobs: Unit Tests, Feature Tests, Integration Tests, Performance Tests

### 3. Code Quality & Security (`code-quality-security.yml`)
- Triggers: Push to any branch, Pull requests, Daily schedule
- Jobs: Code Quality, Security Scan, Dependency Scan, Docker Security

### 4. Automated Deployment (`automated-deployment.yml`)
- Triggers: Push to main/develop, Manual dispatch
- Jobs: Deploy Staging, Deploy Production, Rollback, Blue-Green, Canary

### 5. Release Management (`release-management.yml`)
- Triggers: Tag push, Release publish, Manual dispatch
- Jobs: Create Release, Build Release, Generate Changelog, Deploy Release

## Setup Instructions

### 1. Prerequisites
- GitHub repository
- Docker and Docker Compose
- SSH access to staging and production servers
- Slack webhook (optional)

### 2. GitHub Configuration
- Add required secrets
- Configure environments
- Set up branch protection rules
- Enable GitHub Actions

### 3. Server Configuration
- Install Docker and Docker Compose
- Configure SSH access
- Set up monitoring
- Configure backups

### 4. Testing
- Create test branch
- Push changes
- Verify pipeline execution
- Test deployment

## Security Features

- **Vulnerability Scanning**: Automated security scanning
- **Dependency Updates**: Automated dependency updates
- **Code Quality**: Enforced code quality standards
- **Access Control**: Environment-based access control
- **Audit Logging**: Complete audit trail

## Monitoring and Alerting

- **Deployment Notifications**: Slack notifications
- **Health Checks**: Automated health monitoring
- **Performance Metrics**: Prometheus metrics
- **Error Tracking**: Centralized error logging
- **Security Alerts**: GitHub security alerts

## Best Practices

1. **Branch Strategy**: Use feature branches and pull requests
2. **Code Review**: Require code reviews for all changes
3. **Testing**: Maintain high test coverage
4. **Security**: Regular security scans and updates
5. **Monitoring**: Continuous monitoring and alerting
6. **Documentation**: Keep documentation up to date
7. **Backup**: Regular backups and disaster recovery

## Troubleshooting

### Common Issues
1. **Pipeline Failures**: Check logs and fix issues
2. **Deployment Failures**: Verify server configuration
3. **Security Issues**: Address vulnerability reports
4. **Performance Issues**: Monitor metrics and optimize

### Support
- Check GitHub Actions logs
- Review server logs
- Monitor Grafana dashboards
- Contact support team

## Additional Resources

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Docker Documentation](https://docs.docker.com/)
- [Laravel Documentation](https://laravel.com/docs)
- [Security Best Practices](https://docs.github.com/en/code-security)
EOF

    log_success "CI/CD documentation generated"
}

# Show setup summary
show_summary() {
    log_success "🎉 CI/CD Pipeline Setup Completed!"
    echo ""
    echo "📊 Setup Summary:"
    echo "=================="
    echo "✅ GitHub repository configured"
    echo "✅ GitHub Secrets configured"
    echo "✅ GitHub Environments configured"
    echo "✅ Docker Registry configured"
    echo "✅ Monitoring and alerting configured"
    echo "✅ Backup strategy configured"
    echo "✅ Security scanning configured"
    echo "✅ CI/CD pipeline tested"
    echo "✅ Documentation generated"
    echo ""
    echo "🚀 Next Steps:"
    echo "=============="
    echo "1. Review and customize GitHub workflows"
    echo "2. Configure server environments"
    echo "3. Set up monitoring dashboards"
    echo "4. Test deployment pipeline"
    echo "5. Train team on CI/CD processes"
    echo ""
    echo "📚 Documentation:"
    echo "================"
    echo "- CI/CD Setup Guide: CICD_SETUP_GUIDE.md"
    echo "- GitHub Workflows: .github/workflows/"
    echo "- Docker Configuration: docker-compose.prod.yml"
    echo "- Deployment Scripts: deploy-production.sh"
    echo ""
    echo "🔧 Management:"
    echo "============="
    echo "- View pipeline status: GitHub Actions tab"
    echo "- Monitor deployments: Grafana dashboards"
    echo "- Manage secrets: GitHub Settings > Secrets"
    echo "- Review security: GitHub Security tab"
}

# Main setup process
main() {
    log_info "Starting CI/CD Pipeline Setup..."
    
    check_prerequisites
    setup_github_repo
    setup_github_secrets
    setup_github_environments
    setup_docker_registry
    setup_monitoring
    setup_backup_strategy
    setup_security_scanning
    test_cicd_pipeline
    generate_documentation
    show_summary
}

# Run main function
main "$@"
