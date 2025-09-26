#!/bin/bash

# API Gateway Setup Script
# This script configures Kong API Gateway with services, routes, and plugins

set -e

# Configuration
KONG_ADMIN_URL=${KONG_ADMIN_URL:-"http://localhost:8001"}
KONG_PROXY_URL=${KONG_PROXY_URL:-"http://localhost:8000"}

# Service configurations
declare -A SERVICES=(
    ["user-service"]="http://app1:8000"
    ["project-service"]="http://app2:8000"
    ["task-service"]="http://app3:8000"
    ["document-service"]="http://app1:8000"
    ["notification-service"]="http://app2:8000"
    ["rbac-service"]="http://app3:8000"
    ["analytics-service"]="http://app1:8000"
    ["audit-service"]="http://app2:8000"
)

# Function to wait for Kong to be ready
wait_for_kong() {
    echo "Waiting for Kong to be ready..."
    while ! curl -s "$KONG_ADMIN_URL/status" >/dev/null 2>&1; do
        echo "Kong is not ready yet. Waiting..."
        sleep 5
    done
    echo "Kong is ready!"
}

# Function to create service
create_service() {
    local service_name=$1
    local service_url=$2
    
    echo "Creating service: $service_name"
    
    curl -s -X POST "$KONG_ADMIN_URL/services" \
        -H "Content-Type: application/json" \
        -d "{
            \"name\": \"$service_name\",
            \"url\": \"$service_url\",
            \"connect_timeout\": 60000,
            \"write_timeout\": 60000,
            \"read_timeout\": 60000,
            \"retries\": 5
        }" >/dev/null
    
    echo "✅ Service $service_name created"
}

# Function to create route
create_route() {
    local service_name=$1
    local route_path=$2
    local methods=$3
    
    echo "Creating route for $service_name: $route_path"
    
    curl -s -X POST "$KONG_ADMIN_URL/services/$service_name/routes" \
        -H "Content-Type: application/json" \
        -d "{
            \"name\": \"$service_name-route\",
            \"paths\": [\"$route_path\"],
            \"methods\": [$methods],
            \"strip_path\": false,
            \"preserve_host\": false
        }" >/dev/null
    
    echo "✅ Route $route_path created for $service_name"
}

# Function to add rate limiting plugin
add_rate_limiting() {
    local service_name=$1
    local rate_limit=$2
    
    echo "Adding rate limiting to $service_name: $rate_limit requests per minute"
    
    curl -s -X POST "$KONG_ADMIN_URL/services/$service_name/plugins" \
        -H "Content-Type: application/json" \
        -d "{
            \"name\": \"rate-limiting\",
            \"config\": {
                \"minute\": $rate_limit,
                \"hour\": $(($rate_limit * 60)),
                \"day\": $(($rate_limit * 60 * 24)),
                \"policy\": \"redis\",
                \"redis_host\": \"redis\",
                \"redis_port\": 6379,
                \"redis_timeout\": 2000
            }
        }" >/dev/null
    
    echo "✅ Rate limiting added to $service_name"
}

# Function to add CORS plugin
add_cors() {
    local service_name=$1
    
    echo "Adding CORS to $service_name"
    
    curl -s -X POST "$KONG_ADMIN_URL/services/$service_name/plugins" \
        -H "Content-Type: application/json" \
        -d "{
            \"name\": \"cors\",
            \"config\": {
                \"origins\": [\"*\"],
                \"methods\": [\"GET\", \"POST\", \"PUT\", \"DELETE\", \"OPTIONS\", \"PATCH\"],
                \"headers\": [\"Accept\", \"Accept-Version\", \"Content-Length\", \"Content-MD5\", \"Content-Type\", \"Date\", \"X-Auth-Token\", \"Authorization\"],
                \"exposed_headers\": [\"X-Auth-Token\"],
                \"credentials\": true,
                \"max_age\": 3600,
                \"preflight_continue\": false
            }
        }" >/dev/null
    
    echo "✅ CORS added to $service_name"
}

# Function to add request size limiting
add_request_size_limiting() {
    local service_name=$1
    local size_limit=$2
    
    echo "Adding request size limiting to $service_name: $size_limit"
    
    curl -s -X POST "$KONG_ADMIN_URL/services/$service_name/plugins" \
        -H "Content-Type: application/json" \
        -d "{
            \"name\": \"request-size-limiting\",
            \"config\": {
                \"allowed_payload_size\": $size_limit
            }
        }" >/dev/null
    
    echo "✅ Request size limiting added to $service_name"
}

# Function to add JWT plugin
add_jwt() {
    local service_name=$1
    
    echo "Adding JWT authentication to $service_name"
    
    curl -s -X POST "$KONG_ADMIN_URL/services/$service_name/plugins" \
        -H "Content-Type: application/json" \
        -d "{
            \"name\": \"jwt\",
            \"config\": {
                \"uri_param_names\": [\"jwt\"],
                \"cookie_names\": [\"jwt\"],
                \"header_names\": [\"Authorization\"],
                \"claims_to_verify\": [\"exp\"],
                \"key_claim_name\": \"iss\",
                \"secret_is_base64\": false
            }
        }" >/dev/null
    
    echo "✅ JWT authentication added to $service_name"
}

# Function to add IP restriction
add_ip_restriction() {
    local service_name=$1
    local allowed_ips=$2
    
    echo "Adding IP restriction to $service_name"
    
    curl -s -X POST "$KONG_ADMIN_URL/services/$service_name/plugins" \
        -H "Content-Type: application/json" \
        -d "{
            \"name\": \"ip-restriction\",
            \"config\": {
                \"allow\": [$allowed_ips]
            }
        }" >/dev/null
    
    echo "✅ IP restriction added to $service_name"
}

# Function to setup global plugins
setup_global_plugins() {
    echo "Setting up global plugins..."
    
    # Global rate limiting
    curl -s -X POST "$KONG_ADMIN_URL/plugins" \
        -H "Content-Type: application/json" \
        -d "{
            \"name\": \"rate-limiting\",
            \"config\": {
                \"minute\": 1000,
                \"hour\": 10000,
                \"policy\": \"redis\",
                \"redis_host\": \"redis\",
                \"redis_port\": 6379
            }
        }" >/dev/null
    
    echo "✅ Global rate limiting configured"
    
    # Global CORS
    curl -s -X POST "$KONG_ADMIN_URL/plugins" \
        -H "Content-Type: application/json" \
        -d "{
            \"name\": \"cors\",
            \"config\": {
                \"origins\": [\"*\"],
                \"methods\": [\"GET\", \"POST\", \"PUT\", \"DELETE\", \"OPTIONS\", \"PATCH\"],
                \"headers\": [\"Accept\", \"Accept-Version\", \"Content-Length\", \"Content-MD5\", \"Content-Type\", \"Date\", \"X-Auth-Token\", \"Authorization\"],
                \"exposed_headers\": [\"X-Auth-Token\"],
                \"credentials\": true,
                \"max_age\": 3600
            }
        }" >/dev/null
    
    echo "✅ Global CORS configured"
}

# Function to setup services and routes
setup_services() {
    echo "Setting up services and routes..."
    
    # User Service
    create_service "user-service" "${SERVICES[user-service]}"
    create_route "user-service" "/api/users" "\"GET\", \"POST\", \"PUT\", \"DELETE\""
    create_route "user-service" "/api/auth" "\"GET\", \"POST\", \"PUT\", \"DELETE\""
    create_route "user-service" "/api/profile" "\"GET\", \"POST\", \"PUT\", \"DELETE\""
    add_rate_limiting "user-service" 100
    add_request_size_limiting "user-service" 10485760  # 10MB
    add_jwt "user-service"
    
    # Project Service
    create_service "project-service" "${SERVICES[project-service]}"
    create_route "project-service" "/api/projects" "\"GET\", \"POST\", \"PUT\", \"DELETE\""
    create_route "project-service" "/api/project-templates" "\"GET\", \"POST\", \"PUT\", \"DELETE\""
    add_rate_limiting "project-service" 200
    add_request_size_limiting "project-service" 52428800  # 50MB
    add_jwt "project-service"
    
    # Task Service
    create_service "task-service" "${SERVICES[task-service]}"
    create_route "task-service" "/api/tasks" "\"GET\", \"POST\", \"PUT\", \"DELETE\""
    create_route "task-service" "/api/task-assignments" "\"GET\", \"POST\", \"PUT\", \"DELETE\""
    add_rate_limiting "task-service" 300
    add_request_size_limiting "task-service" 10485760  # 10MB
    add_jwt "task-service"
    
    # Document Service
    create_service "document-service" "${SERVICES[document-service]}"
    create_route "document-service" "/api/documents" "\"GET\", \"POST\", \"PUT\", \"DELETE\""
    create_route "document-service" "/api/document-versions" "\"GET\", \"POST\", \"PUT\", \"DELETE\""
    add_rate_limiting "document-service" 50
    add_request_size_limiting "document-service" 104857600  # 100MB
    add_jwt "document-service"
    
    # Notification Service
    create_service "notification-service" "${SERVICES[notification-service]}"
    create_route "notification-service" "/api/notifications" "\"GET\", \"POST\", \"PUT\", \"DELETE\""
    add_rate_limiting "notification-service" 100
    add_request_size_limiting "notification-service" 1048576  # 1MB
    add_jwt "notification-service"
    
    # RBAC Service
    create_service "rbac-service" "${SERVICES[rbac-service]}"
    create_route "rbac-service" "/api/roles" "\"GET\", \"POST\", \"PUT\", \"DELETE\""
    create_route "rbac-service" "/api/permissions" "\"GET\", \"POST\", \"PUT\", \"DELETE\""
    add_rate_limiting "rbac-service" 50
    add_request_size_limiting "rbac-service" 1048576  # 1MB
    add_jwt "rbac-service"
    
    # Analytics Service
    create_service "analytics-service" "${SERVICES[analytics-service]}"
    create_route "analytics-service" "/api/analytics" "\"GET\", \"POST\""
    create_route "analytics-service" "/api/reports" "\"GET\", \"POST\", \"PUT\", \"DELETE\""
    add_rate_limiting "analytics-service" 100
    add_request_size_limiting "analytics-service" 10485760  # 10MB
    add_jwt "analytics-service"
    
    # Audit Service
    create_service "audit-service" "${SERVICES[audit-service]}"
    create_route "audit-service" "/api/audit" "\"GET\", \"POST\""
    add_rate_limiting "audit-service" 200
    add_request_size_limiting "audit-service" 1048576  # 1MB
    add_jwt "audit-service"
    
    echo "✅ All services and routes configured"
}

# Function to verify configuration
verify_configuration() {
    echo "Verifying Kong configuration..."
    
    # Check services
    echo "Services:"
    curl -s "$KONG_ADMIN_URL/services" | jq -r '.data[] | "\(.name): \(.url)"'
    
    echo ""
    echo "Routes:"
    curl -s "$KONG_ADMIN_URL/routes" | jq -r '.data[] | "\(.name): \(.paths[])"'
    
    echo ""
    echo "Plugins:"
    curl -s "$KONG_ADMIN_URL/plugins" | jq -r '.data[] | "\(.name): \(.service.name // "global")"'
    
    echo ""
    echo "✅ Configuration verification completed"
}

# Main execution
main() {
    echo "Starting Kong API Gateway setup..."
    
    # Wait for Kong to be ready
    wait_for_kong
    
    # Setup global plugins
    setup_global_plugins
    
    # Setup services and routes
    setup_services
    
    # Verify configuration
    verify_configuration
    
    echo "🎉 Kong API Gateway setup completed successfully!"
    echo ""
    echo "Gateway URLs:"
    echo "  Proxy: $KONG_PROXY_URL"
    echo "  Admin API: $KONG_ADMIN_URL"
    echo "  Admin GUI: http://localhost:8002"
    echo ""
    echo "Test the gateway:"
    echo "  curl $KONG_PROXY_URL/api/users"
}

# Run main function
main "$@"
