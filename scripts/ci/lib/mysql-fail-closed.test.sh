#!/usr/bin/env bash
# Self-test for scripts/ci/lib/mysql-fail-closed.sh — no real MySQL or
# Laravel app boot required: this shadows `php` on PATH with a fake that
# returns canned output, so the test verifies the library's control flow
# (fail-closed on wrong connection, fail-closed on unreachable server)
# without needing infrastructure.
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$ROOT_DIR"

# shellcheck disable=SC1091
source scripts/ci/lib/mysql-fail-closed.sh

FAKE_PHP_DIR="$(mktemp -d)"
trap 'rm -rf "$FAKE_PHP_DIR"' EXIT

pass=0
fail=0

assert_eq() {
    local desc="$1" expected="$2" actual="$3"
    if [[ "$expected" == "$actual" ]]; then
        echo "PASS: $desc"
        pass=$((pass + 1))
    else
        echo "FAIL: $desc (expected '$expected', got '$actual')"
        fail=$((fail + 1))
    fi
}

assert_exit_nonzero() {
    local desc="$1"
    shift
    if "$@" >/tmp/mysql-fail-closed-test-output 2>&1; then
        echo "FAIL: $desc (expected nonzero exit, got 0)"
        fail=$((fail + 1))
    else
        echo "PASS: $desc"
        pass=$((pass + 1))
    fi
}

# --- zena_mysql_resolve_with_precedence: picks first non-empty in order ---
unset FOO_A FOO_B FOO_C 2>/dev/null || true
export FOO_B="from-b"
result="$(zena_mysql_resolve_with_precedence "default-val" FOO_A FOO_B FOO_C)"
assert_eq "resolve_with_precedence picks first set var" "from-b" "$result"
unset FOO_B

result="$(zena_mysql_resolve_with_precedence "default-val" FOO_A FOO_B FOO_C)"
assert_eq "resolve_with_precedence falls back to default" "default-val" "$result"

# --- zena_mysql_resolve_host_with_fallback: passes through non-'mysql' hosts ---
result="$(zena_mysql_resolve_host_with_fallback "127.0.0.1")"
assert_eq "resolve_host_with_fallback passes through non-mysql host" "127.0.0.1" "$result"

# --- zena_mysql_ensure_connection: fails closed when default != mysql ---
cat > "$FAKE_PHP_DIR/php" << 'FAKEPHP'
#!/usr/bin/env bash
# Fake `php -r '...'` that always reports database.default=sqlite,
# simulating tests/bootstrap.php's override having fired.
echo "sqlite"
FAKEPHP
chmod +x "$FAKE_PHP_DIR/php"

PATH="$FAKE_PHP_DIR:$PATH" assert_exit_nonzero "ensure_connection fails closed when default is sqlite" \
    bash -c 'source scripts/ci/lib/mysql-fail-closed.sh && zena_mysql_ensure_connection'

# --- zena_mysql_ensure_connection: passes when default is mysql ---
cat > "$FAKE_PHP_DIR/php" << 'FAKEPHP'
#!/usr/bin/env bash
echo "mysql"
FAKEPHP
chmod +x "$FAKE_PHP_DIR/php"

if PATH="$FAKE_PHP_DIR:$PATH" bash -c 'source scripts/ci/lib/mysql-fail-closed.sh && zena_mysql_ensure_connection'; then
    echo "PASS: ensure_connection passes when default is mysql"
    pass=$((pass + 1))
else
    echo "FAIL: ensure_connection passes when default is mysql"
    fail=$((fail + 1))
fi

# --- zena_mysql_preflight_connection: fails closed on unreachable server ---
export DB_HOST="127.0.0.1"
export DB_PORT="1"  # port 1 is never a real MySQL server in CI or locally
export DB_DATABASE="zenamanage_test"
export DB_USERNAME="root"
export DB_PASSWORD=""
assert_exit_nonzero "preflight_connection fails closed on unreachable server" \
    zena_mysql_preflight_connection

echo ""
echo "mysql-fail-closed.test.sh: $pass passed, $fail failed"
[[ "$fail" -eq 0 ]]
