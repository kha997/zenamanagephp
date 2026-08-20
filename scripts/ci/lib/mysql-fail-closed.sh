#!/usr/bin/env bash
# Shared fail-closed MySQL entrypoint library (GAP-039).
#
# Extracted from scripts/ci/zena-invariants-mysql (the original working
# pattern) to eliminate the 4-way duplication that had grown across
# zena-invariants-mysql, rfi-escalation-concurrency-mysql,
# document-workflow-concurrency-mysql, and treasury-check-constraints-mysql.
# Behavior is unchanged from the original zena-invariants-mysql logic.
#
# Usage (source, then call in order):
#   source "$(dirname "${BASH_SOURCE[0]}")/lib/mysql-fail-closed.sh"
#   zena_mysql_resolve_env
#   zena_mysql_print_config
#   zena_mysql_ensure_connection
#   zena_mysql_preflight_connection
#   # ... only now is it safe to migrate/seed/run tests against MySQL ...

zena_mysql_resolve_with_precedence() {
    local default_value="$1"
    shift
    local env_var
    for env_var in "$@"; do
        local env_value
        env_value="${!env_var:-}"
        if [[ -n "$env_value" ]]; then
            printf '%s' "$env_value"
            return 0
        fi
    done
    printf '%s' "$default_value"
}

zena_mysql_resolve_host_with_fallback() {
    local host="$1"
    if [[ "$host" != "mysql" ]]; then
        printf '%s' "$host"
        return 0
    fi

    # Guard against macOS not resolving "mysql" by falling back to localhost.
    if python3 -c 'import socket, sys; socket.gethostbyname(sys.argv[1])' "$host" >/dev/null 2>&1; then
        printf '%s' "$host"
    else
        printf '127.0.0.1'
    fi
}

# Resolves DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD from
# MYSQL_*/ZENA_MYSQL_*/DB_* (in that precedence order) and exports them,
# plus ZENA_INVARIANTS_DB=mysql — the one variable tests/bootstrap.php
# checks before deciding whether to force SQLite.
zena_mysql_resolve_env() {
    local resolved_host resolved_port resolved_database resolved_username resolved_password

    resolved_host=$(zena_mysql_resolve_with_precedence "mysql" MYSQL_HOST ZENA_MYSQL_HOST DB_HOST)
    resolved_host=$(zena_mysql_resolve_host_with_fallback "$resolved_host")
    resolved_port=$(zena_mysql_resolve_with_precedence "3306" MYSQL_PORT ZENA_MYSQL_PORT DB_PORT)
    resolved_database=$(zena_mysql_resolve_with_precedence "zenamanage_test" MYSQL_DATABASE ZENA_MYSQL_DATABASE DB_DATABASE)
    resolved_username=$(zena_mysql_resolve_with_precedence "root" MYSQL_USERNAME ZENA_MYSQL_USERNAME DB_USERNAME)
    resolved_password=$(zena_mysql_resolve_with_precedence "" MYSQL_PASSWORD ZENA_MYSQL_PASSWORD DB_PASSWORD)

    export DB_HOST="$resolved_host"
    export DB_PORT="$resolved_port"
    export DB_DATABASE="$resolved_database"
    export DB_USERNAME="$resolved_username"
    export DB_PASSWORD="$resolved_password"
    export DB_CONNECTION=mysql
    export ZENA_INVARIANTS_DB=mysql
}

zena_mysql_print_config() {
    php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
    printf(
        "ZENA MYSQL FAIL-CLOSED CONFIG: env=%s | mode=%s | default=%s | mysql_host=%s | mysql_port=%s | mysql_db=%s | mysql_user=%s | mysql_pw=%s\n",
        $app->environment(),
        getenv("ZENA_INVARIANTS_DB") ?: "unset",
        config("database.default"),
        config("database.connections.mysql.host") ?? "null",
        config("database.connections.mysql.port") ?? "null",
        config("database.connections.mysql.database") ?? "null",
        config("database.connections.mysql.username") ?? "null",
        empty(config("database.connections.mysql.password")) ? "EMPTY" : "SET"
    );
'
}

# Fails closed (exit 1) unless Laravel's own resolved default connection is
# genuinely "mysql". Boots via bootstrap/app.php directly — never through
# tests/bootstrap.php/phpunit.xml — so this check cannot itself be fooled by
# the same override it is verifying was NOT applied.
zena_mysql_ensure_connection() {
    local default_connection
    default_connection=$(php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
printf("%s", config("database.default"));
')

    if [[ "$default_connection" != "mysql" ]]; then
        echo "ERROR: zena_mysql_ensure_connection requires mysql but database.default is '$default_connection'." >&2
        exit 1
    fi
}

# Fails closed (exit 1) unless a real PDO connection to the resolved MySQL
# target succeeds within 5 seconds.
zena_mysql_preflight_connection() {
    php -r '
try {
    $host = getenv("DB_HOST") ?: "mysql";
    $port = getenv("DB_PORT") ?: 3306;
    $db = getenv("DB_DATABASE") ?: "zenamanage_test";
    $user = getenv("DB_USERNAME") ?: "root";
    $pass = getenv("DB_PASSWORD") ?: "";
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", $host, $port, $db);
    new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    printf("Preflight MySQL connection succeeded (%s:%s/%s)\n", $host, $port, $db);
} catch (Throwable $e) {
    fwrite(STDERR, "MySQL connection preflight failed: " . $e->getMessage() . "\n");
    exit(1);
}
'
}
