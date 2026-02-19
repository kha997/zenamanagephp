#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

OUT_DIR="${NIGHTLY_OUTPUT_DIR:-storage/app/nightly}"
REPORT_FILE="${NIGHTLY_REPORT_FILE:-${OUT_DIR}/nightly-report.md}"
mkdir -p "$OUT_DIR"

overall_status=0

run_stage() {
  local label="$1"
  shift
  echo "[nightly] running: ${label}"
  "$@"
}

tcp_reachable() {
  local host="$1"
  local port="$2"
  php -r '
    $host = $argv[1] ?? "127.0.0.1";
    $port = (int) ($argv[2] ?? 0);
    $errno = 0;
    $errstr = "";
    $fp = @fsockopen($host, $port, $errno, $errstr, 1);
    if ($fp === false) {
      exit(1);
    }
    fclose($fp);
    exit(0);
  ' "$host" "$port"
}

http_reachable() {
  local url="$1"
  php -r '
    $url = $argv[1] ?? "";
    if ($url === "") {
      exit(1);
    }
    $opts = ["http" => ["method" => "GET", "timeout" => 1]];
    $ctx = stream_context_create($opts);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
      exit(1);
    }
    exit(0);
  ' "$url"
}

parse_junit_counts() {
  local file="$1"
  php -r '
    $file = $argv[1] ?? "";
    if ($file === "" || !is_file($file)) {
      echo "0 0 0 0 0";
      exit(0);
    }

    $doc = new DOMDocument();
    if (!@$doc->load($file)) {
      echo "0 0 0 0 0";
      exit(0);
    }

    $root = $doc->documentElement;
    if (!$root) {
      echo "0 0 0 0 0";
      exit(0);
    }

    $tests = (int) $root->getAttribute("tests");
    $failures = (int) $root->getAttribute("failures");
    $errors = (int) $root->getAttribute("errors");
    $skipped = (int) $root->getAttribute("skipped");

    if ($tests === 0 && $root->tagName === "testsuites") {
      $tests = 0;
      $failures = 0;
      $errors = 0;
      $skipped = 0;
      foreach ($root->childNodes as $child) {
        if (!($child instanceof DOMElement) || $child->tagName !== "testsuite") {
          continue;
        }
        $tests += (int) $child->getAttribute("tests");
        $failures += (int) $child->getAttribute("failures");
        $errors += (int) $child->getAttribute("errors");
        $skipped += (int) $child->getAttribute("skipped");
      }
    }

    $passed = $tests - $failures - $errors - $skipped;
    if ($passed < 0) {
      $passed = 0;
    }

    echo $tests . " " . $passed . " " . $failures . " " . $errors . " " . $skipped;
  ' "$file"
}

run_group() {
  local group="$1"
  local env_name="$2"
  local env_value="$3"
  local junit_file="${OUT_DIR}/junit-${group}.xml"
  local log_file="${OUT_DIR}/group-${group}.log"
  local exit_code=0

  echo "[nightly] running group: ${group}"
  rm -f "$junit_file" "$log_file"

  if env "${env_name}=${env_value}" RUN_REDIS_TESTS=1 php -d pcov.enabled=0 ./vendor/bin/phpunit --group "$group" --log-junit "$junit_file" >"$log_file" 2>&1; then
    exit_code=0
  else
    exit_code=$?
    overall_status=1
  fi

  read -r tests passed failures errors skipped <<<"$(parse_junit_counts "$junit_file")"
  local status="PASS"
  if [[ "$exit_code" -ne 0 ]]; then
    status="FAIL"
  elif [[ "$tests" -gt 0 && "$skipped" -eq "$tests" ]]; then
    status="SKIP"
  fi

  echo "[nightly] ${group}: status=${status} tests=${tests} pass=${passed} fail=${failures} error=${errors} skip=${skipped}"

  printf '%s|%s|%s|%s|%s|%s|%s\n' "$group" "$status" "$tests" "$passed" "$failures" "$errors" "$skipped" >> "${OUT_DIR}/summary.tsv"
}

run_stage "optimize:clear" php artisan optimize:clear
run_stage "ssot:lint" composer ssot:lint
run_stage "test:fast" php -d pcov.enabled=0 ./vendor/bin/phpunit --exclude-group slow,load,stress,redis

rm -f "${OUT_DIR}/summary.tsv"
run_group "slow" "RUN_SLOW_TESTS" "1"
run_group "load" "RUN_LOAD_TESTS" "1"
run_group "stress" "RUN_STRESS_TESTS" "1"
run_group "redis" "RUN_REDIS_TESTS" "1"

REDIS_HOST_VALUE="${REDIS_HOST:-127.0.0.1}"
REDIS_PORT_VALUE="${REDIS_PORT:-6379}"
APP_URL_VALUE="${APP_URL:-http://127.0.0.1:8000}"
WEBSOCKET_HOST_VALUE="${WEBSOCKET_HOST:-127.0.0.1}"
WEBSOCKET_PORT_VALUE="${WEBSOCKET_PORT:-8080}"

redis_reachable="no"
if tcp_reachable "$REDIS_HOST_VALUE" "$REDIS_PORT_VALUE"; then
  redis_reachable="yes"
fi

app_reachable="no"
if http_reachable "${APP_URL_VALUE%/}/api/csrf-token"; then
  app_reachable="yes"
fi

websocket_reachable="no"
if tcp_reachable "$WEBSOCKET_HOST_VALUE" "$WEBSOCKET_PORT_VALUE"; then
  websocket_reachable="yes"
fi

{
  echo "# Nightly Test Matrix Report"
  echo ""
  echo "- Generated: $(date -u +'%Y-%m-%dT%H:%M:%SZ')"
  echo "- Command: composer test:nightly"
  echo "- Output directory: \`${OUT_DIR}\`"
  echo ""
  echo "## Groups Executed"
  echo ""
  echo "| Group | Status | Tests | Pass | Fail | Error | Skip |"
  echo "| --- | --- | ---: | ---: | ---: | ---: | ---: |"
  while IFS='|' read -r group status tests passed failures errors skipped; do
    [[ -z "$group" ]] && continue
    echo "| ${group} | ${status} | ${tests} | ${passed} | ${failures} | ${errors} | ${skipped} |"
  done < "${OUT_DIR}/summary.tsv"
  echo ""
  echo "## Key Environment Prerequisites"
  echo ""
  echo "- \`REDIS_HOST\`: ${REDIS_HOST_VALUE}"
  echo "- \`REDIS_PORT\`: ${REDIS_PORT_VALUE}"
  echo "- Redis reachable: ${redis_reachable}"
  echo "- \`APP_URL\`: ${APP_URL_VALUE}"
  echo "- App URL reachable (\`/api/csrf-token\`): ${app_reachable}"
  echo "- \`WEBSOCKET_HOST\`: ${WEBSOCKET_HOST_VALUE}"
  echo "- \`WEBSOCKET_PORT\`: ${WEBSOCKET_PORT_VALUE}"
  echo "- WebSocket port reachable: ${websocket_reachable}"
  echo ""
  echo "## Notes"
  echo ""
  echo "- Group status is \`SKIP\` when all tests in that group were skipped."
  echo "- Individual test skip reasons are captured by PHPUnit in each group log/JUnit file."
} > "$REPORT_FILE"

echo "[nightly] report written: ${REPORT_FILE}"

exit "$overall_status"
