#!/bin/bash

# MailHog Stop Script

echo "🛑 Stopping MailHog..."

if docker ps --format '{{.Names}}' | grep -q '^mailhog$'; then
    docker stop mailhog
    docker rm mailhog
    echo "✅ MailHog stopped and removed"
else
    echo "ℹ️  MailHog is not running"
fi

