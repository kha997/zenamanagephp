#!/bin/bash

# Script to stop Nginx dev proxy

CONTAINER_NAME="zenamanage-dev-proxy"

echo "Stopping Nginx dev proxy..."

# Check if container exists
if docker ps -a | grep -q "$CONTAINER_NAME"; then
    echo "Stopping container: $CONTAINER_NAME"
    docker stop "$CONTAINER_NAME" > /dev/null 2>&1
    docker rm "$CONTAINER_NAME" > /dev/null 2>&1
    echo "✓ Nginx dev proxy stopped and removed"
else
    echo "✗ Container $CONTAINER_NAME not found"
    exit 1
fi

