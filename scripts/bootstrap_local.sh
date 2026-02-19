#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "${REPO_ROOT}"

info() {
  printf '[bootstrap] %s\n' "$1"
}

fail() {
  printf '[bootstrap][error] %s\n' "$1" >&2
  exit 1
}

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || fail "Missing required command: $1"
}

read_env() {
  local key="$1"
  local default_value="${2:-}"
  local value

  value="$(awk -F= -v key="$key" '
    $0 !~ /^[[:space:]]*#/ && $1 == key {
      sub(/^[[:space:]]+/, "", $2)
      sub(/[[:space:]]+$/, "", $2)
      print $2
      exit
    }
  ' .env 2>/dev/null || true)"

  value="${value%\"}"
  value="${value#\"}"
  value="${value%\'}"
  value="${value#\'}"

  if [[ -z "$value" ]]; then
    printf '%s' "$default_value"
  else
    printf '%s' "$value"
  fi
}

assert_safe_environment() {
  local app_env db_connection db_host db_name

  app_env="$(read_env APP_ENV local)"
  db_connection="$(read_env DB_CONNECTION mysql)"
  db_host="$(read_env DB_HOST 127.0.0.1)"
  db_name="$(read_env DB_DATABASE '')"

  case "$app_env" in
    local|development|dev|testing)
      ;;
    *)
      fail "Refusing to run: APP_ENV=${app_env}. Allowed: local/development/dev/testing."
      ;;
  esac

  if [[ "$db_connection" != "sqlite" ]]; then
    case "$db_host" in
      127.0.0.1|localhost|mysql|mariadb)
        ;;
      *)
        fail "Refusing to run against non-local DB_HOST=${db_host}."
        ;;
    esac
  fi

  if [[ -n "$db_name" && "$db_name" =~ (prod|production|live) ]]; then
    fail "Refusing to run against DB_DATABASE=${db_name}."
  fi
}

ensure_env_file() {
  if [[ -f .env ]]; then
    info ".env already exists; leaving as-is"
    return
  fi

  if [[ ! -f .env.example ]]; then
    fail ".env is missing and .env.example was not found"
  fi

  cp .env.example .env
  info "Created .env from .env.example"
}

ensure_app_key() {
  local app_key
  app_key="$(read_env APP_KEY '')"

  if [[ -n "$app_key" ]]; then
    info "APP_KEY already set"
    return
  fi

  info "Generating APP_KEY"
  php artisan key:generate --force >/dev/null
}

seed_database() {
  info "Running migrations"
  php artisan migrate --force

  info "Running DatabaseSeeder"
  php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force
}

run_optional_maintenance() {
  local db_connection
  db_connection="$(read_env DB_CONNECTION mysql)"

  if [[ "$db_connection" == "sqlite" ]]; then
    info "SKIP (sqlite): MySQL-only optimize/index maintenance"
    return
  fi

  info "No MySQL-only optimize/index maintenance configured"
}

print_summary() {
  local app_url tenant_id
  app_url="$(read_env APP_URL 'http://127.0.0.1:8000')"

  tenant_id="$(
    php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$tenant = App\Models\Tenant::where("domain", "zena.local")->first();
echo $tenant ? $tenant->id : "";
' 2>/dev/null || true
  )"

  echo
  info "Bootstrap complete"
  echo "Credentials:"
  echo "  email:    admin@zena.local"
  echo "  password: password"
  echo "  tenant:   zena.local"
  echo "  tenant_id:${tenant_id:-<resolve with tinker>}"
  echo
  echo "Next commands:"
  echo "  php artisan serve"
  echo "  npm install"
  echo "  npm run build"
  echo "  composer ssot:lint"
  echo "  COMPOSER_PROCESS_TIMEOUT=0 composer test:fast"
  echo
  echo "Smoke check:"
  echo "  curl -sS ${app_url%/}/api/zena/health"
}

main() {
  info "Validating prerequisites"
  require_cmd php
  require_cmd composer
  require_cmd npm

  ensure_env_file
  assert_safe_environment
  ensure_app_key
  seed_database
  run_optional_maintenance
  print_summary
}

main "$@"
