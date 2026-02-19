#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "${REPO_ROOT}"

REPORT_FILE="${SMOKE_REPORT_FILE:-${REPO_ROOT}/smoke-report.md}"
APP_HOST="${APP_HOST:-127.0.0.1}"
APP_PORT="${APP_PORT:-18080}"
APP_BASE_URL="${APP_BASE_URL:-http://${APP_HOST}:${APP_PORT}}"
SMOKE_EMAIL="${SMOKE_EMAIL:-admin@zena.local}"
SMOKE_PASSWORD="${SMOKE_PASSWORD:-password}"
SERVER_LOG="/tmp/smoke_server.log"
SMOKE_SERVER_MODE="${SMOKE_SERVER_MODE:-router}"

PASS_COUNT=0
FAIL_COUNT=0
SKIP_COUNT=0
PHP_PID=""
ENV_BACKUP=""
TENANT_ID=""
TOKEN=""
HAVE_FAILURE=0
SERVER_MODE=""
SERVER_UP_SEEN=0
TEMP_ROOT_INDEX_LINK=0
SERVER_EXIT_CODE=""

CHECK_LINES=()
DIAG_LINES=()

info() {
  printf '[smoke] %s\n' "$1"
}

record_pass() {
  PASS_COUNT=$((PASS_COUNT + 1))
  CHECK_LINES+=("- PASS: $1")
  info "PASS - $1"
}

record_fail() {
  FAIL_COUNT=$((FAIL_COUNT + 1))
  CHECK_LINES+=("- FAIL: $1")
  HAVE_FAILURE=1
  info "FAIL - $1"
}

record_skip() {
  SKIP_COUNT=$((SKIP_COUNT + 1))
  CHECK_LINES+=("- SKIP: $1")
  info "SKIP - $1"
}

cleanup() {
  if [[ -n "${PHP_PID}" ]]; then
    kill "${PHP_PID}" >/dev/null 2>&1 || true
  fi

  if [[ "${TEMP_ROOT_INDEX_LINK}" -eq 1 && -L index.php ]]; then
    rm -f index.php
  fi

  if [[ -n "${ENV_BACKUP}" && -f "${ENV_BACKUP}" ]]; then
    mv "${ENV_BACKUP}" .env
  fi
}
trap cleanup EXIT

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "Missing required command: $1" >&2
    exit 1
  }
}

set_env_value() {
  local key="$1"
  local value="$2"

  if grep -Eq "^[# ]*${key}=" .env; then
    sed -i.bak "s|^[# ]*${key}=.*|${key}=${value}|" .env
    rm -f .env.bak
  else
    printf '%s=%s\n' "${key}" "${value}" >> .env
  fi
}

json_get() {
  local json="$1"
  local path="$2"

  php -r '
$json = stream_get_contents(STDIN);
$path = $argv[1];
$data = json_decode($json, true);
if (!is_array($data)) {
    exit(2);
}
$segments = $path === "" ? [] : explode(".", $path);
$value = $data;
foreach ($segments as $segment) {
    if ($segment === "") {
        continue;
    }
    if (!is_array($value) || !array_key_exists($segment, $value)) {
        exit(3);
    }
    $value = $value[$segment];
}
if (is_array($value)) {
    echo json_encode($value);
} elseif (is_bool($value)) {
    echo $value ? "true" : "false";
} elseif ($value === null) {
    echo "null";
} else {
    echo (string) $value;
}
' "$path" <<<"$json"
}

json_has_path() {
  local json="$1"
  local path="$2"
  json_get "$json" "$path" >/dev/null 2>&1
}

resolve_uri_json() {
  local route_name="$1"

  php artisan route:list --json 2>/dev/null | php -r '
$routes = json_decode(stream_get_contents(STDIN), true);
$name = $argv[1];
if (!is_array($routes)) {
    exit(1);
}
foreach ($routes as $route) {
    if (($route["name"] ?? "") !== $name) {
        continue;
    }
    $uri = trim((string) ($route["uri"] ?? ""));
    if ($uri !== "") {
        echo "/" . ltrim($uri, "/");
    }
    exit(0);
}
exit(1);
' "$route_name" 2>/dev/null || true
}

resolve_uri_text() {
  local route_name="$1"

  php artisan route:list --name="$route_name" 2>/dev/null | awk '
    BEGIN { found=0 }
    $NF == target {
      if ($2 != "") {
        print "/" $2
        found=1
        exit 0
      }
    }
    /^\|/ {
      n = split($0, cols, "|")
      for (i = 1; i <= n; i++) {
        gsub(/^ +| +$/, "", cols[i])
      }
      if (n >= 6 && cols[6] == target && cols[4] != "") {
        print "/" cols[4]
        found=1
        exit 0
      }
    }
    END { if (!found) exit 1 }
  ' target="$route_name" 2>/dev/null || true
}

resolve_uri_by_name() {
  local route_name="$1"
  local uri

  uri="$(resolve_uri_json "$route_name" | tr -d '\r\n' || true)"
  if [[ -n "$uri" ]]; then
    printf '%s' "$uri"
    return 0
  fi

  uri="$(resolve_uri_text "$route_name" | tr -d '\r\n' || true)"
  if [[ -n "$uri" ]]; then
    printf '%s' "$uri"
    return 0
  fi

  printf ''
}

status_line() {
  local path="$1"
  curl -sS --connect-timeout 3 --max-time 5 -D - -o /dev/null "${APP_BASE_URL}${path}" 2>/dev/null | sed -n '1p' | tr -d '\r'
}

http_call() {
  local method="$1"
  local url="$2"
  local payload="$3"
  shift 3
  local -a extra_headers=("$@")

  local body_file
  local status_file
  body_file="$(mktemp)"
  status_file="$(mktemp)"

  local -a cmd=(curl -sS --connect-timeout 5 --max-time 20 -X "$method" "$url" -o "$body_file" -w '%{http_code}' -H 'Accept: application/json')

  for h in "${extra_headers[@]}"; do
    cmd+=( -H "$h" )
  done

  if [[ -n "$payload" ]]; then
    cmd+=( -H 'Content-Type: application/json' --data "$payload" )
  fi

  "${cmd[@]}" > "$status_file"

  local status
  local body
  status="$(cat "$status_file")"
  body="$(cat "$body_file")"

  rm -f "$body_file" "$status_file"

  printf '%s\n%s\n' "$status" "$body"
}

assert_success_envelope() {
  local body="$1"
  local success
  local status

  success="$(json_get "$body" 'success' 2>/dev/null || true)"
  status="$(json_get "$body" 'status' 2>/dev/null || true)"
  [[ "$success" == "true" && "$status" == "success" ]]
}

describe_error() {
  local body="$1"
  local message
  local code

  message="$(json_get "$body" 'error.message' 2>/dev/null || json_get "$body" 'message' 2>/dev/null || echo 'unknown error')"
  code="$(json_get "$body" 'error.code' 2>/dev/null || echo 'n/a')"
  printf 'error_code=%s message=%s' "$code" "$message"
}

wait_for_listen() {
  local i

  for i in $(seq 1 40); do
    if [[ -n "${PHP_PID}" ]] && ! kill -0 "${PHP_PID}" >/dev/null 2>&1; then
      capture_server_exit_code_if_dead
      return 1
    fi

    if command -v lsof >/dev/null 2>&1; then
      if lsof -iTCP:"${APP_PORT}" -sTCP:LISTEN >/dev/null 2>&1; then
        return 0
      fi
    else
      if curl -sS --connect-timeout 1 --max-time 2 "${APP_BASE_URL}/" >/dev/null 2>&1; then
        return 0
      fi
    fi
    sleep 0.25
  done

  return 1
}

wait_for_readiness() {
  local primary_path="$1"
  local -a candidates=()
  local p
  local line
  local code
  local i

  if [[ -n "$primary_path" ]]; then
    candidates+=("$primary_path")
  fi
  candidates+=("/api/zena/health" "/api/health")

  for i in $(seq 1 60); do
    if [[ -n "${PHP_PID}" ]] && ! kill -0 "${PHP_PID}" >/dev/null 2>&1; then
      capture_server_exit_code_if_dead
      return 1
    fi

    for p in "${candidates[@]}"; do
      line="$(status_line "$p")"
      if [[ "$line" == HTTP/* ]]; then
        SERVER_UP_SEEN=1
        code="$(awk '{print $2}' <<<"$line")"
        if [[ "$code" == "200" ]]; then
          printf '%s' "$p"
          return 0
        fi
      fi
    done
    sleep 0.25
  done

  return 1
}

capture_server_exit_code_if_dead() {
  local exit_code

  if [[ -z "${PHP_PID}" || -n "${SERVER_EXIT_CODE}" ]]; then
    return 0
  fi

  if kill -0 "${PHP_PID}" >/dev/null 2>&1; then
    return 0
  fi

  set +e
  wait "${PHP_PID}" >/dev/null 2>&1
  exit_code=$?
  set -e

  SERVER_EXIT_CODE="${exit_code}"
}

artisan_serve_supports_no_reload() {
  php artisan serve --help 2>&1 | grep -q -- '--no-reload'
}

start_artisan_server() {
  local -a cmd=(php artisan serve --host="${APP_HOST}" --port="${APP_PORT}")

  if artisan_serve_supports_no_reload; then
    cmd+=(--no-reload)
  fi

  start_server_with "artisan serve" "${cmd[@]}"
}

add_diag_block() {
  local title="$1"
  local content="$2"
  DIAG_LINES+=("### ${title}")
  DIAG_LINES+=("\`\`\`")
  DIAG_LINES+=("${content}")
  DIAG_LINES+=("\`\`\`")
}

collect_diagnostics() {
  local listen_output
  local root_line
  local zena_health_line
  local api_health_line
  local php_version
  local php_modules
  local uname_output
  local server_status
  local server_head
  local server_tail
  local laravel_tail

  capture_server_exit_code_if_dead

  php_version="$(php -v 2>&1 || true)"
  php_modules="$( (php -m 2>&1 | sort) || true )"
  uname_output="$(uname -a 2>&1 || true)"

  if command -v lsof >/dev/null 2>&1; then
    listen_output="$(lsof -iTCP:${APP_PORT} -sTCP:LISTEN 2>&1 || true)"
  else
    listen_output="lsof not available"
  fi

  root_line="$(status_line '/' || true)"
  zena_health_line="$(status_line '/api/zena/health' || true)"
  api_health_line="$(status_line '/api/health' || true)"
  server_head="$(sed -n '1,40p' "${SERVER_LOG}" 2>&1 || true)"
  server_tail="$(tail -n 120 "${SERVER_LOG}" 2>&1 || true)"

  if [[ -f storage/logs/laravel.log ]]; then
    laravel_tail="$(tail -n 120 storage/logs/laravel.log 2>&1 || true)"
  else
    laravel_tail="storage/logs/laravel.log not found"
  fi

  if [[ -n "${SERVER_EXIT_CODE}" ]]; then
    server_status="mode=${SERVER_MODE} pid=${PHP_PID:-none} exit_code=${SERVER_EXIT_CODE}"
  elif [[ -n "${PHP_PID}" ]] && kill -0 "${PHP_PID}" >/dev/null 2>&1; then
    server_status="mode=${SERVER_MODE} pid=${PHP_PID} status=running"
  else
    server_status="mode=${SERVER_MODE} pid=${PHP_PID:-none} exit_code=unknown"
  fi

  add_diag_block "php -v" "${php_version}"
  add_diag_block "php -m | sort" "${php_modules}"
  add_diag_block "uname -a" "${uname_output}"
  add_diag_block "server process status" "${server_status}"
  add_diag_block "lsof LISTEN ${APP_PORT}" "${listen_output}"
  add_diag_block "curl status lines" "/ => ${root_line}\n/api/zena/health => ${zena_health_line}\n/api/health => ${api_health_line}"
  add_diag_block "head -n 40 ${SERVER_LOG}" "${server_head}"
  add_diag_block "tail ${SERVER_LOG}" "${server_tail}"
  add_diag_block "tail storage/logs/laravel.log" "${laravel_tail}"

  info "Diagnostics:"
  printf '%b\n' "${listen_output}" || true
  printf '/ => %s\n/api/zena/health => %s\n/api/health => %s\n' "$root_line" "$zena_health_line" "$api_health_line"
  printf '%b\n' "${server_tail}" || true
  printf '%b\n' "${laravel_tail}" || true
}

write_report() {
  {
    echo "# Staging Smoke Report"
    echo
    echo "- Timestamp: $(date -u '+%Y-%m-%d %H:%M:%SZ')"
    echo "- Base URL: ${APP_BASE_URL}"
    echo "- Tenant ID: ${TENANT_ID:-unknown}"
    echo "- Server mode: ${SMOKE_SERVER_MODE}"
    echo "- Passed: ${PASS_COUNT}"
    echo "- Failed: ${FAIL_COUNT}"
    echo "- Skipped: ${SKIP_COUNT}"
    echo
    echo "## Checks"
    printf '%s\n' "${CHECK_LINES[@]}"
    echo
    if [[ "$FAIL_COUNT" -eq 0 ]]; then
      echo "Result: PASS"
    else
      echo "Result: FAIL"
    fi

    if [[ "$HAVE_FAILURE" -eq 1 && ${#DIAG_LINES[@]} -gt 0 ]]; then
      echo
      echo "## Diagnostics"
      printf '%b\n' "${DIAG_LINES[@]}"
    fi
  } > "$REPORT_FILE"
}

summary_and_exit() {
  if [[ "$HAVE_FAILURE" -eq 1 ]]; then
    collect_diagnostics
  fi

  echo
  echo "Smoke summary: PASS=${PASS_COUNT} FAIL=${FAIL_COUNT} SKIP=${SKIP_COUNT}"
  write_report

  if [[ "$FAIL_COUNT" -gt 0 ]]; then
    exit 1
  fi
}

stop_server() {
  if [[ -n "${PHP_PID}" ]]; then
    kill "${PHP_PID}" >/dev/null 2>&1 || true
    PHP_PID=""
  fi
}

start_server_with() {
  local mode="$1"
  shift

  : > "$SERVER_LOG"
  info "Starting local server (${mode})"
  "$@" >"${SERVER_LOG}" 2>&1 &
  PHP_PID=$!
  SERVER_MODE="$mode"

  sleep 0.5
  if ! kill -0 "$PHP_PID" >/dev/null 2>&1; then
    capture_server_exit_code_if_dead
    return 1
  fi

  if ! wait_for_listen; then
    capture_server_exit_code_if_dead
    return 1
  fi

  return 0
}

start_server() {
  local requested_mode
  requested_mode="$(printf '%s' "${SMOKE_SERVER_MODE}" | tr '[:upper:]' '[:lower:]')"

  if [[ "${requested_mode}" == "artisan" ]]; then
    if start_artisan_server; then
      return 0
    fi
    stop_server
    record_fail "server process exited early (mode=artisan)"
    summary_and_exit
  fi

  if [[ "${requested_mode}" != "router" ]]; then
    record_fail "invalid SMOKE_SERVER_MODE=${SMOKE_SERVER_MODE} (expected router|artisan)"
    summary_and_exit
  fi

  if [[ -f server.php ]]; then
    if start_server_with "php -S (server.php)" php -S "${APP_HOST}:${APP_PORT}" server.php; then
      return 0
    fi
  elif [[ -f vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php ]]; then
    if [[ ! -f index.php && -f public/index.php ]]; then
      ln -s public/index.php index.php
      TEMP_ROOT_INDEX_LINK=1
    fi
    if start_server_with "php -S (vendor router)" php -S "${APP_HOST}:${APP_PORT}" vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php; then
      return 0
    fi
  fi

  stop_server
  if start_artisan_server; then
    return 0
  fi

  stop_server
  record_fail "server process exited early"
  summary_and_exit
}

main() {
  require_cmd php
  require_cmd composer
  require_cmd npm
  require_cmd curl

  if [[ ! -f .env ]]; then
    cp .env.example .env
  fi

  ENV_BACKUP="$(mktemp)"
  cp .env "$ENV_BACKUP"

  : > "${REPO_ROOT}/database/smoke-staging.sqlite"

  set_env_value APP_ENV local
  set_env_value APP_DEBUG true
  set_env_value APP_URL "$APP_BASE_URL"
  set_env_value DB_CONNECTION sqlite
  set_env_value DB_DATABASE "${REPO_ROOT}/database/smoke-staging.sqlite"

  info "Bootstrapping local environment with sqlite"
  bash scripts/bootstrap_local.sh

  TENANT_ID="$(
    php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$tenant = App\Models\Tenant::where("domain", "zena.local")->first();
echo $tenant ? $tenant->id : "";
'
  )"

  if [[ -z "$TENANT_ID" ]]; then
    record_fail "resolve tenant id for zena.local"
    summary_and_exit
  fi

  local health_uri login_uri me_uri projects_uri readiness_path
  health_uri="$(resolve_uri_by_name 'api.zena.api.health')"
  login_uri="$(resolve_uri_by_name 'api.zena.auth.login')"
  me_uri="$(resolve_uri_by_name 'api.zena.auth.me')"

  projects_uri="$(resolve_uri_by_name 'api.v1.projects.index')"
  if [[ -z "$projects_uri" ]]; then
    projects_uri="$(resolve_uri_by_name 'api.zena.projects.index')"
  fi
  if [[ -z "$projects_uri" ]]; then
    projects_uri="$(resolve_uri_by_name 'projects.index')"
  fi

  if [[ -z "$login_uri" || -z "$me_uri" || -z "$projects_uri" ]]; then
    record_fail "resolve required named routes"
    summary_and_exit
  fi

  start_server

  readiness_path="$(wait_for_readiness "$health_uri" || true)"
  if [[ -z "$readiness_path" ]]; then
    if [[ "$SERVER_MODE" == php\ -S* ]]; then
      info "Primary server mode failed readiness; retrying with artisan serve"
      stop_server
      if start_artisan_server; then
        readiness_path="$(wait_for_readiness "$health_uri" || true)"
      fi
    fi
  fi
  if [[ -z "$readiness_path" ]]; then
    if [[ "$SERVER_UP_SEEN" -eq 1 ]]; then
      info "Server responded, but readiness endpoint did not return HTTP 200"
    fi
    record_fail "server readiness check failed"
    summary_and_exit
  fi
  info "Readiness path: ${readiness_path}"
  health_uri="${readiness_path}"

  local response status body login_payload

  # a) health endpoint
  response="$(http_call GET "${APP_BASE_URL}${health_uri}" '' "X-Tenant-ID: ${TENANT_ID}")"
  status="$(echo "$response" | sed -n '1p')"
  body="$(echo "$response" | sed -n '2,$p')"
  if [[ "$status" == "200" ]] && assert_success_envelope "$body"; then
    record_pass "health (${health_uri})"
  else
    record_fail "health (${health_uri}) status=${status} $(describe_error "$body")"
  fi

  # b) login
  login_payload="{\"email\":\"${SMOKE_EMAIL}\",\"password\":\"${SMOKE_PASSWORD}\"}"
  response="$(http_call POST "${APP_BASE_URL}${login_uri}" "$login_payload" "X-Tenant-ID: ${TENANT_ID}")"
  status="$(echo "$response" | sed -n '1p')"
  body="$(echo "$response" | sed -n '2,$p')"
  TOKEN="$(json_get "$body" 'data.token' 2>/dev/null || true)"
  if [[ "$status" == "200" ]] && assert_success_envelope "$body" && [[ -n "$TOKEN" && "$TOKEN" != "null" ]]; then
    record_pass "login (${login_uri})"
  else
    record_fail "login (${login_uri}) status=${status} $(describe_error "$body")"
  fi

  if [[ -z "$TOKEN" || "$TOKEN" == "null" ]]; then
    summary_and_exit
  fi

  # c) me
  response="$(http_call GET "${APP_BASE_URL}${me_uri}" '' "X-Tenant-ID: ${TENANT_ID}" "Authorization: Bearer ${TOKEN}")"
  status="$(echo "$response" | sed -n '1p')"
  body="$(echo "$response" | sed -n '2,$p')"
  if [[ "$status" == "200" ]] && assert_success_envelope "$body" && json_has_path "$body" 'data.user.email'; then
    record_pass "me (${me_uri})"
  else
    record_fail "me (${me_uri}) status=${status} $(describe_error "$body")"
  fi

  # d) projects list
  response="$(http_call GET "${APP_BASE_URL}${projects_uri}" '' "X-Tenant-ID: ${TENANT_ID}" "Authorization: Bearer ${TOKEN}")"
  status="$(echo "$response" | sed -n '1p')"
  body="$(echo "$response" | sed -n '2,$p')"
  if [[ "$status" == "200" ]] && assert_success_envelope "$body"; then
    record_pass "projects.index (${projects_uri})"
  else
    record_fail "projects.index (${projects_uri}) status=${status} $(describe_error "$body")"
  fi

  # e) optional mutation is intentionally skipped for deterministic smoke
  record_skip "optional project create/list mutation"

  summary_and_exit
}

main "$@"
