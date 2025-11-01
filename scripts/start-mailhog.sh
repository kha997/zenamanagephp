#!/bin/bash

# MailHog Startup Script for E2E Email Testing
# This script starts MailHog for testing email delivery

echo "🐱 Starting MailHog for email testing..."

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    echo "   Visit: https://docs.docker.com/get-docker/"
    exit 1
fi

# Check if Docker is running
if ! docker info &> /dev/null; then
    echo "❌ Docker is not running. Please start Docker Desktop."
    exit 1
fi

# Stop existing MailHog container if running
if docker ps -a --format '{{.Names}}' | grep -q '^mailhog$'; then
    echo "🛑 Stopping existing MailHog container..."
    docker stop mailhog > /dev/null 2>&1
    docker rm mailhog > /dev/null 2>&1
fi

# Start MailHog
echo "🚀 Starting MailHog container..."
docker run -d \
    --name mailhog \
    -p 1025:1025 \
    -p 8025:8025 \
    mailhog/mailhog

# Wait for MailHog to be ready
sleep 2

# Verify it's running
if docker ps | grep -q mailhog; then
    echo "✅ MailHog is running!"
    echo ""
    echo "📧 MailHog UI: http://localhost:8025"
    echo "📨 SMTP Server: localhost:1025"
    echo ""
    echo "💡 Configure your .env file:"
    echo "   MAIL_MAILER=smtp"
    echo "   MAIL_HOST=localhost"
    echo "   MAIL_PORT=1025"
    echo "   MAIL_USERNAME="
    echo "   MAIL_PASSWORD="
    echo ""
    echo "🛑 To stop MailHog, run:"
    echo "   docker stop mailhog"
    echo "   docker rm mailhog"
else
    echo "❌ Failed to start MailHog"
    exit 1
fi

