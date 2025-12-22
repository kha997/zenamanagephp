#!/bin/bash

# Script to add dev.zena.local to /etc/hosts for single-origin routing

HOSTS_FILE="/etc/hosts"
HOST_ENTRY="127.0.0.1 dev.zena.local"

echo "Setting up dev.zena.local for single-origin routing..."

# Check if entry already exists
if grep -q "dev.zena.local" "$HOSTS_FILE"; then
    echo "✓ dev.zena.local already exists in /etc/hosts"
    grep "dev.zena.local" "$HOSTS_FILE"
else
    echo "Adding dev.zena.local to /etc/hosts..."
    echo "$HOST_ENTRY" | sudo tee -a "$HOSTS_FILE" > /dev/null
    if [ $? -eq 0 ]; then
        echo "✓ Successfully added dev.zena.local to /etc/hosts"
        echo "  Entry: $HOST_ENTRY"
    else
        echo "✗ Failed to add entry. Please run manually:"
        echo "  sudo echo '$HOST_ENTRY' >> /etc/hosts"
        exit 1
    fi
fi

echo ""
echo "Setup complete! You can now access:"
echo "  - Admin (Blade): http://dev.zena.local/admin/users"
echo "  - App (React): http://dev.zena.local/app/dashboard"
echo "  - API: http://dev.zena.local/api/v1/..."

