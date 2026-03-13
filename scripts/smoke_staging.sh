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
SMOKE_ENV_NAME="${SMOKE_ENV_NAME:-smoke}"
SMOKE_ENV_FILE="${SMOKE_ENV_FILE:-.env.smoke}"
SMOKE_DB_PATH="${SMOKE_DB_PATH:-/tmp/zenamanage_smoke.sqlite}"

PASS_COUNT=0
FAIL_COUNT=0
SKIP_COUNT=0
PHP_PID=""
TENANT_ID=""
TOKEN=""
HAVE_FAILURE=0
SERVER_UP_SEEN=0
SERVER_EXIT_CODE=""
FAILURE_CLASSIFICATION="none"
READINESS_PATH_USED=""
READINESS_STATUS_LINE=""
RESOLVED_HEALTH_URI=""
RESOLVED_LOGIN_URI=""
RESOLVED_ME_URI=""
RESOLVED_PROJECTS_URI=""

CHECK_LINES=()
DIAG_LINES=()
READINESS_CANDIDATES=()

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

set_failure_classification() {
  FAILURE_CLASSIFICATION="$1"
}

cleanup() {
  if [[ -n "${PHP_PID}" ]]; then
    kill "${PHP_PID}" >/dev/null 2>&1 || true
    wait "${PHP_PID}" >/dev/null 2>&1 || true
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
  local file_path="$1"
  local key="$2"
  local value="$3"

  if grep -Eq "^[# ]*${key}=" "$file_path"; then
    sed -i.bak "s|^[# ]*${key}=.*|${key}=${value}|" "$file_path"
    rm -f "${file_path}.bak"
  else
    printf '%s=%s\n' "${key}" "${value}" >> "$file_path"
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

run_artisan() {
  php artisan --env="${SMOKE_ENV_NAME}" "$@"
}

resolve_uri_json() {
  local route_name="$1"

  run_artisan route:list --json 2>/dev/null | php -r '
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

  run_artisan route:list --name="$route_name" 2>/dev/null | awk '
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
    cmd+=(-H "$h")
  done

  if [[ -n "$payload" ]]; then
    cmd+=(-H 'Content-Type: application/json' --data "$payload")
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

ensure_smoke_env_file() {
  local resolved_env_file="$SMOKE_ENV_FILE"

  if [[ "$resolved_env_file" != /* ]]; then
    resolved_env_file="${REPO_ROOT}/${resolved_env_file}"
  fi

  SMOKE_ENV_FILE="$resolved_env_file"

  if [[ ! -f "$SMOKE_ENV_FILE" ]]; then
    cp .env.example "$SMOKE_ENV_FILE"
  fi

  set_env_value "$SMOKE_ENV_FILE" APP_ENV testing
  set_env_value "$SMOKE_ENV_FILE" APP_DEBUG true
  set_env_value "$SMOKE_ENV_FILE" APP_URL "$APP_BASE_URL"
  set_env_value "$SMOKE_ENV_FILE" DB_CONNECTION sqlite
  set_env_value "$SMOKE_ENV_FILE" DB_DATABASE "$SMOKE_DB_PATH"
  set_env_value "$SMOKE_ENV_FILE" CACHE_DRIVER file
  set_env_value "$SMOKE_ENV_FILE" SESSION_DRIVER file
  set_env_value "$SMOKE_ENV_FILE" QUEUE_CONNECTION sync
  set_env_value "$SMOKE_ENV_FILE" LOG_CHANNEL stack
}

ensure_smoke_app_key() {
  if grep -Eq '^APP_KEY=.+$' "$SMOKE_ENV_FILE"; then
    return 0
  fi

  info "Generating APP_KEY for ${SMOKE_ENV_NAME}"
  run_artisan key:generate --force >/dev/null
}

prepare_smoke_database() {
  mkdir -p "$(dirname "$SMOKE_DB_PATH")"
  rm -f "$SMOKE_DB_PATH"
  : > "$SMOKE_DB_PATH"
}

seed_smoke_database() {
  info "Running smoke migrations"
  run_artisan migrate --force >/dev/null

  info "Seeding smoke database"
  run_artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force >/dev/null
}

resolve_smoke_tenant_id() {
  APP_ENV="${SMOKE_ENV_NAME}" php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$tenant = App\Models\Tenant::where("domain", "zena.local")->first();
echo $tenant ? $tenant->id : "";
'
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

capture_listener_snapshot() {
  if command -v lsof >/dev/null 2>&1; then
    lsof -iTCP:"${APP_PORT}" -sTCP:LISTEN 2>&1 || true
    return 0
  fi

  if command -v ss >/dev/null 2>&1; then
    ss -ltnp "( sport = :${APP_PORT} )" 2>&1 || true
    return 0
  fi

  printf 'Neither lsof nor ss is available'
}

wait_for_process_or_fail() {
  local i

  for i in $(seq 1 10); do
    if kill -0 "$PHP_PID" >/dev/null 2>&1; then
      return 0
    fi
    capture_server_exit_code_if_dead
    sleep 0.25
  done

  capture_server_exit_code_if_dead
  return 1
}

wait_for_listener_or_fail() {
  local i

  for i in $(seq 1 40); do
    if ! kill -0 "$PHP_PID" >/dev/null 2>&1; then
      capture_server_exit_code_if_dead
      return 2
    fi

    if command -v lsof >/dev/null 2>&1; then
      if lsof -iTCP:"${APP_PORT}" -sTCP:LISTEN >/dev/null 2>&1; then
        return 0
      fi
    elif command -v ss >/dev/null 2>&1; then
      if ss -ltn "( sport = :${APP_PORT} )" 2>/dev/null | grep -q ":${APP_PORT}"; then
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

build_readiness_candidates() {
  local primary_path="$1"
  local -a candidates=()

  if [[ -n "$primary_path" ]]; then
    candidates+=("$primary_path")
  fi
  candidates+=("/api/zena/health" "/api/health")

  printf '%s\n' "${candidates[@]}" | awk '!seen[$0]++'
}

wait_for_readiness() {
  local primary_path="$1"
  local p
  local line
  local code
  local i

  READINESS_CANDIDATES=()

  while IFS= read -r p; do
    [[ -n "$p" ]] || continue
    READINESS_CANDIDATES+=("$p")
  done < <(build_readiness_candidates "$primary_path")

  for i in $(seq 1 60); do
    if [[ -n "${PHP_PID}" ]] && ! kill -0 "${PHP_PID}" >/dev/null 2>&1; then
      capture_server_exit_code_if_dead
      return 1
    fi

    for p in "${READINESS_CANDIDATES[@]}"; do
      line="$(status_line "$p")"
      if [[ "$line" == HTTP/* ]]; then
        SERVER_UP_SEEN=1
        code="$(awk '{print $2}' <<<"$line")"
        if [[ "$code" == "200" ]]; then
          READINESS_PATH_USED="$p"
          READINESS_STATUS_LINE="$line"
          printf '%s' "$p"
          return 0
        fi
      fi
    done
    sleep 1
  done

  return 1
}

capture_curl_verbose() {
  local path="$1"

  curl -v --connect-timeout 3 --max-time 10 "${APP_BASE_URL}${path}" 2>&1 || true
}

add_diag_block() {
  local title="$1"
  local content="$2"
  DIAG_LINES+=("### ${title}")
  DIAG_LINES+=('```')
  DIAG_LINES+=("${content}")
  DIAG_LINES+=('```')
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
  local root_verbose
  local zena_health_verbose
  local api_health_verbose
  local readiness_verbose
  local laravel_tail

  capture_server_exit_code_if_dead

  php_version="$(php -v 2>&1 || true)"
  php_modules="$( (php -m 2>&1 | sort) || true )"
  uname_output="$(uname -a 2>&1 || true)"
  listen_output="$(capture_listener_snapshot)"
  root_line="$(status_line '/' || true)"
  zena_health_line="$(status_line '/api/zena/health' || true)"
  api_health_line="$(status_line '/api/health' || true)"
  server_head="$(sed -n '1,80p' "${SERVER_LOG}" 2>&1 || true)"
  server_tail="$(tail -n 200 "${SERVER_LOG}" 2>&1 || true)"
  root_verbose="$(capture_curl_verbose '/')"
  zena_health_verbose="$(capture_curl_verbose '/api/zena/health')"
  api_health_verbose="$(capture_curl_verbose '/api/health')"
  readiness_verbose="$(capture_curl_verbose "${READINESS_PATH_USED:-${RESOLVED_HEALTH_URI:-/api/zena/health}}")"

  if [[ -f storage/logs/laravel.log ]]; then
    laravel_tail="$(tail -n 120 storage/logs/laravel.log 2>&1 || true)"
  else
    laravel_tail="storage/logs/laravel.log not found"
  fi

  if [[ -n "${SERVER_EXIT_CODE}" ]]; then
    server_status="pid=${PHP_PID:-none} exit_code=${SERVER_EXIT_CODE}"
  elif [[ -n "${PHP_PID}" ]] && kill -0 "${PHP_PID}" >/dev/null 2>&1; then
    server_status="pid=${PHP_PID} status=running"
  else
    server_status="pid=${PHP_PID:-none} exit_code=unknown"
  fi

  add_diag_block "php -v" "${php_version}"
  add_diag_block "php -m | sort" "${php_modules}"
  add_diag_block "uname -a" "${uname_output}"
  add_diag_block "server process status" "${server_status}"
  add_diag_block "listener snapshot ${APP_PORT}" "${listen_output}"
  add_diag_block "curl status lines" "/ => ${root_line}\n/api/zena/health => ${zena_health_line}\n/api/health => ${api_health_line}"

  if [[ "${FAILURE_CLASSIFICATION}" == "app listener up but readiness failed" ]]; then
    add_diag_block "curl -v readiness ${READINESS_PATH_USED:-unknown}" "${readiness_verbose}"
  fi

  add_diag_block "curl -v /" "${root_verbose}"
  add_diag_block "curl -v /api/zena/health" "${zena_health_verbose}"
  add_diag_block "curl -v /api/health" "${api_health_verbose}"
  add_diag_block "head -n 80 ${SERVER_LOG}" "${server_head}"
  add_diag_block "tail ${SERVER_LOG}" "${server_tail}"
  add_diag_block "tail storage/logs/laravel.log" "${laravel_tail}"

  info "Diagnostics:"
  printf '%b\n' "${listen_output}" || true
  printf '/ => %s\n/api/zena/health => %s\n/api/health => %s\n' "$root_line" "$zena_health_line" "$api_health_line"
  printf '%b\n' "${server_tail}" || true
}

write_report() {
  {
    echo "# Staging Smoke Report"
    echo
    echo "- Timestamp: $(date -u '+%Y-%m-%d %H:%M:%SZ')"
    echo "- Base URL: ${APP_BASE_URL}"
    echo "- Tenant ID: ${TENANT_ID:-unknown}"
    echo "- Failure Classification: ${FAILURE_CLASSIFICATION}"
    echo "- Passed: ${PASS_COUNT}"
    echo "- Failed: ${FAIL_COUNT}"
    echo "- Skipped: ${SKIP_COUNT}"
    echo
    echo "## Bootstrap Context"
    echo "- Env name: ${SMOKE_ENV_NAME}"
    echo "- Env file: ${SMOKE_ENV_FILE}"
    echo "- DB path: ${SMOKE_DB_PATH}"
    echo "- Port: ${APP_PORT}"
    echo
    echo "## Resolved Routes"
    echo "- api.zena.api.health: ${RESOLVED_HEALTH_URI:-UNRESOLVED}"
    echo "- api.zena.auth.login: ${RESOLVED_LOGIN_URI:-UNRESOLVED}"
    echo "- api.zena.auth.me: ${RESOLVED_ME_URI:-UNRESOLVED}"
    echo "- projects route: ${RESOLVED_PROJECTS_URI:-UNRESOLVED}"
    echo "- readiness path used: ${READINESS_PATH_USED:-UNREACHED}"
    echo "- readiness status line: ${READINESS_STATUS_LINE:-UNREACHED}"
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

start_artisan_server() {
  local -a cmd=(php artisan serve --env="${SMOKE_ENV_NAME}" --host="${APP_HOST}" --port="${APP_PORT}")

  if artisan_serve_supports_no_reload; then
    cmd+=(--no-reload)
  fi

  : > "$SERVER_LOG"
  info "Starting local server (artisan serve)"
  "${cmd[@]}" >"${SERVER_LOG}" 2>&1 &
  PHP_PID=$!
}

start_server() {
  start_artisan_server

  if ! wait_for_process_or_fail; then
    capture_server_exit_code_if_dead
    set_failure_classification "server bootstrap failed before listen"
    record_fail "server bootstrap failed before listen"
    summary_and_exit
  fi

  if ! wait_for_listener_or_fail; then
    capture_server_exit_code_if_dead
    if [[ -n "${SERVER_EXIT_CODE}" ]]; then
      set_failure_classification "server bootstrap failed before listen"
      record_fail "server bootstrap failed before listen"
    else
      set_failure_classification "server never bound to ${APP_PORT}"
      record_fail "server never bound to ${APP_PORT}"
    fi
    summary_and_exit
  fi
}

resolve_required_routes() {
  RESOLVED_HEALTH_URI="$(resolve_uri_by_name 'api.zena.api.health')"
  RESOLVED_LOGIN_URI="$(resolve_uri_by_name 'api.zena.auth.login')"
  RESOLVED_ME_URI="$(resolve_uri_by_name 'api.zena.auth.me')"

  RESOLVED_PROJECTS_URI="$(resolve_uri_by_name 'api.v1.projects.index')"
  if [[ -z "$RESOLVED_PROJECTS_URI" ]]; then
    RESOLVED_PROJECTS_URI="$(resolve_uri_by_name 'api.zena.projects.index')"
  fi
  if [[ -z "$RESOLVED_PROJECTS_URI" ]]; then
    RESOLVED_PROJECTS_URI="$(resolve_uri_by_name 'projects.index')"
  fi

  if [[ -z "$RESOLVED_HEALTH_URI" ]]; then
    set_failure_classification "route contract failure"
    record_fail "resolve named route api.zena.api.health"
    summary_and_exit
  fi

  if [[ -z "$RESOLVED_LOGIN_URI" || -z "$RESOLVED_ME_URI" || -z "$RESOLVED_PROJECTS_URI" ]]; then
    set_failure_classification "route contract failure"
    record_fail "resolve required named routes"
    summary_and_exit
  fi
}

main() {
  require_cmd php
  require_cmd curl

  if ! ensure_smoke_env_file; then
    set_failure_classification "bootstrap failure"
    record_fail "prepare smoke env file"
    summary_and_exit
  fi

  if ! ensure_smoke_app_key; then
    set_failure_classification "bootstrap failure"
    record_fail "generate smoke app key"
    summary_and_exit
  fi

  if ! prepare_smoke_database; then
    set_failure_classification "bootstrap failure"
    record_fail "prepare smoke database"
    summary_and_exit
  fi

  if ! seed_smoke_database; then
    set_failure_classification "bootstrap failure"
    record_fail "bootstrap smoke database"
    summary_and_exit
  fi

  TENANT_ID="$(resolve_smoke_tenant_id || true)"
  if [[ -z "$TENANT_ID" ]]; then
    set_failure_classification "bootstrap failure"
    record_fail "resolve tenant id for zena.local"
    summary_and_exit
  fi

  resolve_required_routes
  start_server

  if ! wait_for_readiness "$RESOLVED_HEALTH_URI" >/dev/null; then
    if [[ "$SERVER_UP_SEEN" -eq 1 ]]; then
      set_failure_classification "app listener up but readiness failed"
    else
      set_failure_classification "server readiness failed before any HTTP response"
    fi
    record_fail "server readiness check failed"
    summary_and_exit
  fi
  info "Readiness path: ${READINESS_PATH_USED}"

  local response status body login_payload

  response="$(http_call GET "${APP_BASE_URL}${RESOLVED_HEALTH_URI}" '' "X-Tenant-ID: ${TENANT_ID}")"
  status="$(echo "$response" | sed -n '1p')"
  body="$(echo "$response" | sed -n '2,$p')"
  if [[ "$status" == "200" ]] && assert_success_envelope "$body"; then
    record_pass "health (${RESOLVED_HEALTH_URI})"
  else
    set_failure_classification "api contract failure"
    record_fail "health (${RESOLVED_HEALTH_URI}) status=${status} $(describe_error "$body")"
  fi

  login_payload="{\"email\":\"${SMOKE_EMAIL}\",\"password\":\"${SMOKE_PASSWORD}\"}"
  response="$(http_call POST "${APP_BASE_URL}${RESOLVED_LOGIN_URI}" "$login_payload" "X-Tenant-ID: ${TENANT_ID}")"
  status="$(echo "$response" | sed -n '1p')"
  body="$(echo "$response" | sed -n '2,$p')"
  TOKEN="$(json_get "$body" 'data.token' 2>/dev/null || true)"
  if [[ "$status" == "200" ]] && assert_success_envelope "$body" && [[ -n "$TOKEN" && "$TOKEN" != "null" ]]; then
    record_pass "login (${RESOLVED_LOGIN_URI})"
  else
    set_failure_classification "auth failure"
    record_fail "login (${RESOLVED_LOGIN_URI}) status=${status} $(describe_error "$body")"
  fi

  if [[ -z "$TOKEN" || "$TOKEN" == "null" ]]; then
    summary_and_exit
  fi

  response="$(http_call GET "${APP_BASE_URL}${RESOLVED_ME_URI}" '' "X-Tenant-ID: ${TENANT_ID}" "Authorization: Bearer ${TOKEN}")"
  status="$(echo "$response" | sed -n '1p')"
  body="$(echo "$response" | sed -n '2,$p')"
  if [[ "$status" == "200" ]] && assert_success_envelope "$body" && json_has_path "$body" 'data.user.email'; then
    record_pass "me (${RESOLVED_ME_URI})"
  else
    set_failure_classification "auth failure"
    record_fail "me (${RESOLVED_ME_URI}) status=${status} $(describe_error "$body")"
  fi

  response="$(http_call GET "${APP_BASE_URL}${RESOLVED_PROJECTS_URI}" '' "X-Tenant-ID: ${TENANT_ID}" "Authorization: Bearer ${TOKEN}")"
  status="$(echo "$response" | sed -n '1p')"
  body="$(echo "$response" | sed -n '2,$p')"
  if [[ "$status" == "200" ]] && assert_success_envelope "$body"; then
    record_pass "projects.index (${RESOLVED_PROJECTS_URI})"
  else
    set_failure_classification "api contract failure"
    record_fail "projects.index (${RESOLVED_PROJECTS_URI}) status=${status} $(describe_error "$body")"
  fi

  record_skip "optional project create/list mutation"

  summary_and_exit
}

main "$@"
