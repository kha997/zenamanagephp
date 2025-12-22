#!/bin/bash

# Script to add OpenTelemetry environment variables to .env file
# Usage: ./scripts/setup-opentelemetry-env.sh [environment]
# Environment: local, staging, production (default: local)

ENV_TYPE=${1:-local}
ENV_FILE=".env"

if [ ! -f "$ENV_FILE" ]; then
    echo "Error: .env file not found!"
    echo "Please create .env file first: cp .env.example .env"
    exit 1
fi

# Check if OpenTelemetry vars already exist
if grep -q "OPENTELEMETRY_ENABLED" "$ENV_FILE"; then
    echo "⚠️  OpenTelemetry variables already exist in .env"
    echo "Please update them manually or remove and run this script again."
    exit 0
fi

echo "📝 Adding OpenTelemetry configuration to .env file..."
echo ""

# Add OpenTelemetry section
cat >> "$ENV_FILE" << 'EOF'

# ============================================
# OpenTelemetry Configuration
# ============================================
EOF

# Add configuration based on environment
case $ENV_TYPE in
    local)
        cat >> "$ENV_FILE" << 'EOF'
# Enable/Disable OpenTelemetry
OPENTELEMETRY_ENABLED=true

# Service Information
OPENTELEMETRY_SERVICE_NAME=zenamanage
OPENTELEMETRY_SERVICE_VERSION=1.0.0

# Trace Configuration
OPENTELEMETRY_TRACE_ENABLED=true
OPENTELEMETRY_TRACE_EXPORTER=console
# Options: 'otlp', 'jaeger', 'zipkin', 'console' (console for local dev)

# OTLP Exporter (for production, use this instead of console)
# OPENTELEMETRY_OTLP_ENDPOINT=http://localhost:4318/v1/traces
# OPENTELEMETRY_OTLP_PROTOCOL=http/protobuf

# Jaeger Exporter (Alternative)
# OPENTELEMETRY_JAEGER_ENDPOINT=http://localhost:14268/api/traces

# Zipkin Exporter (Alternative)
# OPENTELEMETRY_ZIPKIN_ENDPOINT=http://localhost:9411/api/v2/spans

# Metrics Configuration
OPENTELEMETRY_METRICS_ENABLED=true
OPENTELEMETRY_METRICS_EXPORTER=otlp
OPENTELEMETRY_METRICS_OTLP_ENDPOINT=http://localhost:4318/v1/metrics

# Sampling Rate (0.0 to 1.0)
# 1.0 = sample all traces, 0.1 = sample 10% of traces
OPENTELEMETRY_SAMPLING_RATE=1.0
EOF
        echo "✅ Added OpenTelemetry configuration for LOCAL environment"
        echo "   Using 'console' exporter - traces will appear in logs"
        ;;
    staging|production)
        cat >> "$ENV_FILE" << EOF
# Enable/Disable OpenTelemetry
OPENTELEMETRY_ENABLED=true

# Service Information
OPENTELEMETRY_SERVICE_NAME=zenamanage
OPENTELEMETRY_SERVICE_VERSION=1.0.0

# Trace Configuration
OPENTELEMETRY_TRACE_ENABLED=true
OPENTELEMETRY_TRACE_EXPORTER=otlp
# Options: 'otlp' (recommended), 'jaeger', 'zipkin', 'console'

# OTLP Exporter (Recommended - works with most backends)
OPENTELEMETRY_OTLP_ENDPOINT=http://localhost:4318/v1/traces
OPENTELEMETRY_OTLP_PROTOCOL=http/protobuf
# Options: 'http/protobuf' (default), 'http/json'
# ⚠️  UPDATE THIS ENDPOINT for your OTLP Collector URL

# Metrics Configuration
OPENTELEMETRY_METRICS_ENABLED=true
OPENTELEMETRY_METRICS_EXPORTER=otlp
OPENTELEMETRY_METRICS_OTLP_ENDPOINT=http://localhost:4318/v1/metrics

# Sampling Rate (0.0 to 1.0)
# Lower rate for production to save resources
OPENTELEMETRY_SAMPLING_RATE=0.1
EOF
        echo "✅ Added OpenTelemetry configuration for ${ENV_TYPE^^} environment"
        echo "   ⚠️  Please update OPENTELEMETRY_OTLP_ENDPOINT with your collector URL"
        ;;
    *)
        echo "❌ Unknown environment: $ENV_TYPE"
        echo "Usage: $0 [local|staging|production]"
        exit 1
        ;;
esac

echo ""
echo "📋 Next steps:"
echo "   1. Review the added configuration in .env"
echo "   2. Update endpoints if needed (especially for production)"
echo "   3. Clear config cache: php artisan config:clear"
echo "   4. Test: php artisan tinker -> config('opentelemetry.enabled')"
echo ""
echo "📖 See docs/setup/OPENTELEMETRY_ENV_SETUP.md for detailed configuration"

