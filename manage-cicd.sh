#!/bin/bash

# CI/CD Pipeline Management Script
# Dashboard System - Pipeline Management

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

# Check pipeline status
check_pipeline_status() {
    log_info "Checking pipeline status..."
    
    # Check if GitHub CLI is installed
    if ! command -v gh &> /dev/null; then
        log_warning "GitHub CLI is not installed. Install it to use this feature."
        echo "Installation: https://cli.github.com/"
        return 1
    fi
    
    # Check authentication
    if ! gh auth status &> /dev/null; then
        log_error "GitHub CLI is not authenticated. Please run 'gh auth login'"
        return 1
    fi
    
    # Get latest workflow runs
    log_info "Latest workflow runs:"
    gh run list --limit 10
    
    # Get current branch
    CURRENT_BRANCH=$(git branch --show-current)
    log_info "Current branch: $CURRENT_BRANCH"
    
    # Check if there are any running workflows
    RUNNING_WORKFLOWS=$(gh run list --status in_progress --limit 5)
    if [ -n "$RUNNING_WORKFLOWS" ]; then
        log_info "Running workflows:"
        echo "$RUNNING_WORKFLOWS"
    else
        log_info "No workflows currently running"
    fi
}

# Trigger manual deployment
trigger_deployment() {
    local environment="$1"
    
    if [ -z "$environment" ]; then
        log_error "Please specify environment (staging or production)"
        return 1
    fi
    
    log_info "Triggering manual deployment to $environment..."
    
    # Check if GitHub CLI is installed
    if ! command -v gh &> /dev/null; then
        log_error "GitHub CLI is not installed. Install it to use this feature."
        return 1
    fi
    
    # Trigger workflow
    gh workflow run automated-deployment.yml \
        --field environment="$environment" \
        --field version="$(git describe --tags --abbrev=0 2>/dev/null || echo 'latest')"
    
    log_success "Deployment triggered for $environment"
}

# Create release
create_release() {
    local version="$1"
    local release_type="$2"
    
    if [ -z "$version" ] || [ -z "$release_type" ]; then
        log_error "Please specify version and release type"
        echo "Usage: $0 release <version> <type>"
        echo "Types: major, minor, patch, prerelease"
        return 1
    fi
    
    log_info "Creating release $version ($release_type)..."
    
    # Check if GitHub CLI is installed
    if ! command -v gh &> /dev/null; then
        log_error "GitHub CLI is not installed. Install it to use this feature."
        return 1
    fi
    
    # Trigger release workflow
    gh workflow run release-management.yml \
        --field version="$version" \
        --field release_type="$release_type"
    
    log_success "Release $version ($release_type) triggered"
}

# View pipeline logs
view_logs() {
    local workflow_id="$1"
    
    if [ -z "$workflow_id" ]; then
        log_info "Available workflows:"
        gh run list --limit 10
        echo ""
        read -p "Enter workflow ID to view logs: " workflow_id
    fi
    
    if [ -z "$workflow_id" ]; then
        log_error "Workflow ID is required"
        return 1
    fi
    
    log_info "Viewing logs for workflow $workflow_id..."
    gh run view "$workflow_id" --log
}

# Cancel running workflow
cancel_workflow() {
    local workflow_id="$1"
    
    if [ -z "$workflow_id" ]; then
        log_info "Running workflows:"
        gh run list --status in_progress --limit 10
        echo ""
        read -p "Enter workflow ID to cancel: " workflow_id
    fi
    
    if [ -z "$workflow_id" ]; then
        log_error "Workflow ID is required"
        return 1
    fi
    
    log_warning "Cancelling workflow $workflow_id..."
    gh run cancel "$workflow_id"
    log_success "Workflow $workflow_id cancelled"
}

# Rerun failed workflow
rerun_workflow() {
    local workflow_id="$1"
    
    if [ -z "$workflow_id" ]; then
        log_info "Failed workflows:"
        gh run list --status failure --limit 10
        echo ""
        read -p "Enter workflow ID to rerun: " workflow_id
    fi
    
    if [ -z "$workflow_id" ]; then
        log_error "Workflow ID is required"
        return 1
    fi
    
    log_info "Rerunning workflow $workflow_id..."
    gh run rerun "$workflow_id"
    log_success "Workflow $workflow_id rerun triggered"
}

# View security alerts
view_security_alerts() {
    log_info "Viewing security alerts..."
    
    # Check if GitHub CLI is installed
    if ! command -v gh &> /dev/null; then
        log_error "GitHub CLI is not installed. Install it to use this feature."
        return 1
    fi
    
    # Get security alerts
    gh api repos/:owner/:repo/dependabot/alerts --jq '.[] | {number: .number, state: .state, severity: .security_advisory.severity, summary: .security_advisory.summary}'
}

# View code scanning results
view_code_scanning() {
    log_info "Viewing code scanning results..."
    
    # Check if GitHub CLI is installed
    if ! command -v gh &> /dev/null; then
        log_error "GitHub CLI is not installed. Install it to use this feature."
        return 1
    fi
    
    # Get code scanning results
    gh api repos/:owner/:repo/code-scanning/alerts --jq '.[] | {number: .number, state: .state, severity: .rule.severity, description: .rule.description}'
}

# View test coverage
view_test_coverage() {
    log_info "Viewing test coverage..."
    
    # Check if Codecov CLI is installed
    if ! command -v codecov &> /dev/null; then
        log_warning "Codecov CLI is not installed. Install it to use this feature."
        echo "Installation: https://docs.codecov.com/docs/codecov-uploader"
        return 1
    fi
    
    # Get coverage report
    codecov --token="$CODECOV_TOKEN" --report
}

# View deployment status
view_deployment_status() {
    log_info "Viewing deployment status..."
    
    # Check if GitHub CLI is installed
    if ! command -v gh &> /dev/null; then
        log_error "GitHub CLI is not installed. Install it to use this feature."
        return 1
    fi
    
    # Get deployment status
    gh api repos/:owner/:repo/deployments --jq '.[] | {id: .id, environment: .environment, status: .status, created_at: .created_at}'
}

# View environment status
view_environment_status() {
    local environment="$1"
    
    if [ -z "$environment" ]; then
        log_info "Available environments:"
        gh api repos/:owner/:repo/environments --jq '.[] | .name'
        echo ""
        read -p "Enter environment name: " environment
    fi
    
    if [ -z "$environment" ]; then
        log_error "Environment name is required"
        return 1
    fi
    
    log_info "Viewing environment status for $environment..."
    gh api repos/:owner/:repo/environments/"$environment" --jq '{name: .name, protection_rules: .protection_rules, deployment_branch_policy: .deployment_branch_policy}'
}

# View workflow usage
view_workflow_usage() {
    log_info "Viewing workflow usage..."
    
    # Check if GitHub CLI is installed
    if ! command -v gh &> /dev/null; then
        log_error "GitHub CLI is not installed. Install it to use this feature."
        return 1
    fi
    
    # Get workflow usage
    gh api repos/:owner/:repo/actions/workflows --jq '.[] | {name: .name, state: .state, created_at: .created_at, updated_at: .updated_at}'
}

# View repository insights
view_repository_insights() {
    log_info "Viewing repository insights..."
    
    # Check if GitHub CLI is installed
    if ! command -v gh &> /dev/null; then
        log_error "GitHub CLI is not installed. Install it to use this feature."
        return 1
    fi
    
    # Get repository insights
    gh api repos/:owner/:repo --jq '{name: .name, stars: .stargazers_count, forks: .forks_count, open_issues: .open_issues_count, language: .language}'
}

# Show help
show_help() {
    echo "CI/CD Pipeline Management Script for ZenaManage Dashboard"
    echo ""
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  status                    Check pipeline status"
    echo "  deploy [env]             Trigger manual deployment (staging/production)"
    echo "  release [ver] [type]     Create release (major/minor/patch/prerelease)"
    echo "  logs [id]                View workflow logs"
    echo "  cancel [id]              Cancel running workflow"
    echo "  rerun [id]               Rerun failed workflow"
    echo "  security                 View security alerts"
    echo "  scanning                 View code scanning results"
    echo "  coverage                 View test coverage"
    echo "  deployments              View deployment status"
    echo "  environment [env]        View environment status"
    echo "  workflows                View workflow usage"
    echo "  insights                 View repository insights"
    echo "  help                     Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 status"
    echo "  $0 deploy staging"
    echo "  $0 release v1.2.3 minor"
    echo "  $0 logs 123456789"
    echo "  $0 security"
    echo ""
    echo "Note: This script requires GitHub CLI (gh) to be installed and authenticated."
}

# Main function
main() {
    local command="$1"
    local arg1="$2"
    local arg2="$3"
    
    case "$command" in
        "status")
            check_pipeline_status
            ;;
        "deploy")
            trigger_deployment "$arg1"
            ;;
        "release")
            create_release "$arg1" "$arg2"
            ;;
        "logs")
            view_logs "$arg1"
            ;;
        "cancel")
            cancel_workflow "$arg1"
            ;;
        "rerun")
            rerun_workflow "$arg1"
            ;;
        "security")
            view_security_alerts
            ;;
        "scanning")
            view_code_scanning
            ;;
        "coverage")
            view_test_coverage
            ;;
        "deployments")
            view_deployment_status
            ;;
        "environment")
            view_environment_status "$arg1"
            ;;
        "workflows")
            view_workflow_usage
            ;;
        "insights")
            view_repository_insights
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
