#!/bin/bash

# Aggregate Test Results Script
# 
# Purpose: Aggregate test results from multiple test runs (by domain, test type, etc.)
# Usage: ./scripts/aggregate-test-results.sh [options]
#
# Options:
#   --domain <domain>     Aggregate results for specific domain (auth, projects, tasks, etc.)
#   --type <type>         Aggregate results by test type (unit, feature, integration, e2e)
#   --output <file>       Output file path (default: test-results-aggregated.json)
#   --format <format>     Output format: json, xml, text (default: json)
#
# Examples:
#   ./scripts/aggregate-test-results.sh --domain auth
#   ./scripts/aggregate-test-results.sh --type unit
#   ./scripts/aggregate-test-results.sh --domain projects --type feature --format text
#
# Requirements:
#   - jq (JSON processor) - install with: sudo apt-get install jq (Ubuntu/Debian) or brew install jq (macOS)
#   - JUnit XML files in storage/app/test-results/ directory

set -e

# Default values
DOMAIN=""
TYPE=""
OUTPUT_FILE="test-results-aggregated.json"
FORMAT="json"
RESULTS_DIR="storage/app/test-results"

# Check for jq dependency
check_jq() {
    if ! command -v jq &> /dev/null; then
        echo "Error: jq is required but not installed." >&2
        echo "Install with:" >&2
        echo "  Ubuntu/Debian: sudo apt-get install jq" >&2
        echo "  macOS: brew install jq" >&2
        echo "  Or download from: https://stedolan.github.io/jq/download/" >&2
        exit 1
    fi
}

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --domain)
            DOMAIN="$2"
            shift 2
            ;;
        --type)
            TYPE="$2"
            shift 2
            ;;
        --output)
            OUTPUT_FILE="$2"
            shift 2
            ;;
        --format)
            FORMAT="$2"
            shift 2
            ;;
        --help)
            echo "Usage: $0 [options]"
            echo "Options:"
            echo "  --domain <domain>     Aggregate results for specific domain"
            echo "  --type <type>         Aggregate results by test type"
            echo "  --output <file>       Output file path"
            echo "  --format <format>    Output format: json, xml, text"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Check for jq dependency (only for JSON format)
if [[ "$FORMAT" == "json" ]]; then
    check_jq
fi

# Create results directory if it doesn't exist
mkdir -p "$RESULTS_DIR"

# Function to extract JUnit XML results
extract_junit_xml() {
    local xml_file="$1"
    
    if [[ ! -f "$xml_file" ]]; then
        echo '{"tests":0,"failures":0,"errors":0,"skipped":0}'
        return
    fi
    
    # Extract test results from JUnit XML using grep and default to 0 if not found
    local tests=$(grep -o 'tests="[0-9]*"' "$xml_file" 2>/dev/null | grep -o '[0-9]*' | head -1 || echo "0")
    local failures=$(grep -o 'failures="[0-9]*"' "$xml_file" 2>/dev/null | grep -o '[0-9]*' | head -1 || echo "0")
    local errors=$(grep -o 'errors="[0-9]*"' "$xml_file" 2>/dev/null | grep -o '[0-9]*' | head -1 || echo "0")
    local skipped=$(grep -o 'skipped="[0-9]*"' "$xml_file" 2>/dev/null | grep -o '[0-9]*' | head -1 || echo "0")
    
    # Calculate passed tests
    local passed=$((tests - failures - errors - skipped))
    
    # Return as JSON
    jq -n \
        --argjson tests "$tests" \
        --argjson failures "$failures" \
        --argjson errors "$errors" \
        --argjson skipped "$skipped" \
        --argjson passed "$passed" \
        '{tests: $tests, failures: $failures, errors: $errors, skipped: $skipped, passed: $passed}'
}

# Initialize aggregated results JSON structure using jq
init_aggregated_json() {
    jq -n \
        --arg timestamp "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
        --arg domain "${DOMAIN:-all}" \
        --arg type "${TYPE:-all}" \
        '{
            timestamp: $timestamp,
            domain: $domain,
            type: $type,
            summary: {
                total_tests: 0,
                passed: 0,
                failed: 0,
                skipped: 0,
                errors: 0
            },
            domains: {},
            test_types: {},
            files: []
        }'
}

# Aggregate results from XML files
AGGREGATED_JSON=$(init_aggregated_json)

# Process files and update aggregated JSON (using process substitution to avoid subshell)
while IFS= read -r xml_file; do
    # Extract domain and type from filename if possible
    local filename=$(basename "$xml_file")
    local domain_match=""
    local type_match=""
    
    # Try to extract domain from filename
    if [[ "$filename" =~ (auth|projects|tasks|documents|users|dashboard) ]]; then
        domain_match="${BASH_REMATCH[1]}"
    fi
    
    # Try to extract type from filename
    if [[ "$filename" =~ (unit|feature|integration|e2e) ]]; then
        type_match="${BASH_REMATCH[1]}"
    fi
    
    # Filter by domain if specified
    if [[ -n "$DOMAIN" && "$domain_match" != "$DOMAIN" ]]; then
        continue
    fi
    
    # Filter by type if specified
    if [[ -n "$TYPE" && "$type_match" != "$TYPE" ]]; then
        continue
    fi
    
    # Extract results from XML
    local file_results=$(extract_junit_xml "$xml_file")
    
    # Update aggregated JSON using jq
    AGGREGATED_JSON=$(echo "$AGGREGATED_JSON" | jq \
        --arg filename "$filename" \
        --arg domain "$domain_match" \
        --arg type "$type_match" \
        --argjson results "$file_results" \
        '
        .files += [{
            filename: $filename,
            domain: (if $domain != "" then $domain else "unknown" end),
            type: (if $type != "" then $type else "unknown" end),
            results: $results
        }] |
        .summary.total_tests += $results.tests |
        .summary.passed += $results.passed |
        .summary.failed += ($results.failures + $results.errors) |
        .summary.skipped += $results.skipped |
        .summary.errors += $results.errors |
        (if $domain != "" then 
            .domains[$domain] = (.domains[$domain] // {
                total_tests: 0,
                passed: 0,
                failed: 0,
                skipped: 0,
                errors: 0
            } |
            .total_tests += $results.tests |
            .passed += $results.passed |
            .failed += ($results.failures + $results.errors) |
            .skipped += $results.skipped |
            .errors += $results.errors)
        else . end) |
        (if $type != "" then
            .test_types[$type] = (.test_types[$type] // {
                total_tests: 0,
                passed: 0,
                failed: 0,
                skipped: 0,
                errors: 0
            } |
            .total_tests += $results.tests |
            .passed += $results.passed |
            .failed += ($results.failures + $results.errors) |
            .skipped += $results.skipped |
            .errors += $results.errors)
        else . end)
        ')
    
    echo "Processing: $filename (tests: $(echo "$file_results" | jq -r '.tests'), failures: $(echo "$file_results" | jq -r '.failures'), errors: $(echo "$file_results" | jq -r '.errors'))" >&2
done < <(find "$RESULTS_DIR" -name "*.xml" -type f 2>/dev/null)

# Calculate percentages for summary
AGGREGATED_JSON=$(echo "$AGGREGATED_JSON" | jq '
    .summary.percentage_passed = (if .summary.total_tests > 0 then 
        ((.summary.passed / .summary.total_tests) * 100) | floor 
    else 0 end) |
    .summary.percentage_failed = (if .summary.total_tests > 0 then 
        ((.summary.failed / .summary.total_tests) * 100) | floor 
    else 0 end)
')

# Output aggregated results
case "$FORMAT" in
    json)
        echo "$AGGREGATED_JSON" | jq '.' > "$OUTPUT_FILE"
        echo "Aggregated results saved to: $OUTPUT_FILE"
        echo ""
        echo "Summary:"
        echo "$AGGREGATED_JSON" | jq -r '
            "Total Tests: \(.summary.total_tests)
Passed: \(.summary.passed) (\(.summary.percentage_passed)%)
Failed: \(.summary.failed) (\(.summary.percentage_failed)%)
Skipped: \(.summary.skipped)
Errors: \(.summary.errors)"
        '
        ;;
    text)
        {
            echo "=== Test Results Summary ==="
            echo "Domain: ${DOMAIN:-all}"
            echo "Type: ${TYPE:-all}"
            echo "Timestamp: $(echo "$AGGREGATED_JSON" | jq -r '.timestamp')"
            echo ""
            echo "Summary:"
            echo "$AGGREGATED_JSON" | jq -r '
                "  Total Tests: \(.summary.total_tests)
  Passed: \(.summary.passed) (\(.summary.percentage_passed)%)
  Failed: \(.summary.failed) (\(.summary.percentage_failed)%)
  Skipped: \(.summary.skipped)
  Errors: \(.summary.errors)"
            '
            echo ""
            if [[ $(echo "$AGGREGATED_JSON" | jq 'keys | contains(["domains"])') == "true" ]] && [[ $(echo "$AGGREGATED_JSON" | jq '.domains | length') -gt 0 ]]; then
                echo "By Domain:"
                echo "$AGGREGATED_JSON" | jq -r '.domains | to_entries[] | "  \(.key): \(.value.total_tests) tests, \(.value.passed) passed, \(.value.failed) failed"'
                echo ""
            fi
            if [[ $(echo "$AGGREGATED_JSON" | jq 'keys | contains(["test_types"])') == "true" ]] && [[ $(echo "$AGGREGATED_JSON" | jq '.test_types | length') -gt 0 ]]; then
                echo "By Type:"
                echo "$AGGREGATED_JSON" | jq -r '.test_types | to_entries[] | "  \(.key): \(.value.total_tests) tests, \(.value.passed) passed, \(.value.failed) failed"'
            fi
        } > "$OUTPUT_FILE"
        echo "Results saved to: $OUTPUT_FILE"
        ;;
    xml)
        # Convert JSON to XML format
        {
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>"
            echo "<testsuites>"
            echo "  <testsuite name=\"aggregated\""
            echo "             tests=\"$(echo "$AGGREGATED_JSON" | jq -r '.summary.total_tests')\""
            echo "             failures=\"$(echo "$AGGREGATED_JSON" | jq -r '.summary.failed')\""
            echo "             errors=\"$(echo "$AGGREGATED_JSON" | jq -r '.summary.errors')\""
            echo "             skipped=\"$(echo "$AGGREGATED_JSON" | jq -r '.summary.skipped')\">"
            echo "  </testsuite>"
            echo "</testsuites>"
        } > "$OUTPUT_FILE"
        echo "Aggregated results saved to: $OUTPUT_FILE"
        ;;
    *)
        echo "Unknown format: $FORMAT" >&2
        exit 1
        ;;
esac

echo ""
echo "Aggregation complete!"

