#!/bin/bash

# Self-Hosted GitHub Actions Runner Setup Script
# For ZenaManage E2E Smoke Tests

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Setting up Self-Hosted GitHub Actions Runner${NC}"
echo ""

# Configuration
REPO_URL="${1:-https://github.com/kha997/zenamanagephp}"
RUNNER_NAME="${2:-zenamanage-e2e-runner}"
RUNNER_DIR="${3:-./actions-runner}"

echo -e "${YELLOW}Configuration:${NC}"
echo "  Repository: $REPO_URL"
echo "  Runner name: $RUNNER_NAME"
echo "  Runner directory: $RUNNER_DIR"
echo ""

# Check if runner directory already exists
if [ -d "$RUNNER_DIR" ]; then
  echo -e "${YELLOW}⚠️  Runner directory already exists: $RUNNER_DIR${NC}"
  read -p "Do you want to remove it and start fresh? (y/N): " -n 1 -r
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${BLUE}Removing existing runner...${NC}"
    cd "$RUNNER_DIR"
    ./run.sh stop || true
    cd ..
    rm -rf "$RUNNER_DIR"
    echo -e "${GREEN}✅ Removed existing runner${NC}"
  else
    echo -e "${YELLOW}Skipping setup. Using existing runner.${NC}"
    exit 0
  fi
fi

# Create runner directory
echo -e "${BLUE}📁 Creating runner directory...${NC}"
mkdir -p "$RUNNER_DIR"
cd "$RUNNER_DIR"

# Detect OS and architecture
DETECTED_OS=$(uname -s | tr '[:upper:]' '[:lower:]')
ARCH=$(uname -m)

# Map OS names to GitHub Actions runner naming
case $DETECTED_OS in
  darwin)
    OS="osx"
    ;;
  linux)
    OS="linux"
    ;;
  *)
    echo -e "${RED}❌ Unsupported OS: $DETECTED_OS${NC}"
    exit 1
    ;;
esac

case $ARCH in
  x86_64)
    ARCH="x64"
    ;;
  arm64|aarch64)
    ARCH="arm64"
    ;;
  *)
    echo -e "${RED}❌ Unsupported architecture: $ARCH${NC}"
    exit 1
    ;;
esac

echo -e "${BLUE}Detected: $DETECTED_OS-$ARCH (Runner: $OS-$ARCH)${NC}"

# Download runner
RUNNER_VERSION="2.311.0"
RUNNER_URL="https://github.com/actions/runner/releases/download/v${RUNNER_VERSION}/actions-runner-${OS}-${ARCH}-${RUNNER_VERSION}.tar.gz"

echo -e "${BLUE}📥 Downloading GitHub Actions Runner v${RUNNER_VERSION}...${NC}"
curl -o actions-runner.tar.gz -L "$RUNNER_URL"

# Extract
echo -e "${BLUE}📦 Extracting runner...${NC}"
tar xzf ./actions-runner.tar.gz
rm ./actions-runner.tar.gz

echo -e "${GREEN}✅ Runner downloaded and extracted${NC}"
echo ""

# Instructions for configuration
echo -e "${YELLOW}📋 Next Steps:${NC}"
echo ""
echo "1. Get a registration token from GitHub:"
echo "   - Go to: $REPO_URL/settings/actions/runners/new"
echo "   - Select 'Self-hosted' as runner type"
echo "   - Copy the registration token"
echo ""
echo "2. Run the configuration command:"
echo "   cd $RUNNER_DIR"
echo "   ./config.sh --url $REPO_URL --token YOUR_TOKEN --name $RUNNER_NAME --work ../_work --labels e2e,self-hosted"
echo ""
echo "3. Start the runner:"
echo "   ./run.sh"
echo ""
echo "   Or run as a service (recommended for production):"
echo "   sudo ./svc.sh install"
echo "   sudo ./svc.sh start"
echo ""
echo -e "${GREEN}✅ Setup script completed!${NC}"
echo ""
echo -e "${BLUE}💡 Tips:${NC}"
echo "  - Runner will consume resources when idle"
echo "  - Consider using systemd service for auto-start"
echo "  - Monitor runner logs in: $RUNNER_DIR/_diag/"
echo ""

