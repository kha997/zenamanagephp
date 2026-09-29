#!/usr/bin/env bash
# GAP-056: hand MySQL credentials to clients through a 0600 option file
# (--defaults-extra-file), never as a -p<password> process argument, and never
# fall back to a default password. Mirrors App\Services\Backup\MysqlClient
# (GAP-054).
#
# Usage (source it):
#   source "<repo>/scripts/lib/mysql-credentials.sh"
#   mysql_option_file DB_CNF "$DB_USER" "$DB_PASSWORD" "$DB_HOST" "$DB_PORT"
#   mysqldump --defaults-extra-file="$DB_CNF" "$DB_NAME" > dump.sql
#
#   mysql_container_client <container> <user> <password> <client> [args...]
#   mysql_compose_root_client <compose-file|-> <service> <client> [args...]
#
# --defaults-extra-file must be the first option given to the client.
# Option files created here are removed when the sourcing shell exits.

_MYSQL_CREDENTIAL_FILES=()

_mysql_credentials_cleanup() {
    local f
    for f in "${_MYSQL_CREDENTIAL_FILES[@]}"; do
        rm -f "$f"
    done
}
trap _mysql_credentials_cleanup EXIT

# Escape a value for a double-quoted MySQL option-file value.
_mysql_option_escape() {
    local value="${1//\\/\\\\}"
    printf '%s' "${value//\"/\\\"}"
}

# Creates a new 0600 option file and stores its path in the variable named by
# the first argument (no subshell, so it is registered for removal on exit).
# printf is a shell builtin, so the password never appears in any process
# argument list. Fails closed when the user or password is empty.
mysql_option_file() {
    local __var="$1" user="$2" password="$3" host="${4:-}" port="${5:-}" __file
    if [ -z "$user" ] || [ -z "$password" ]; then
        echo "mysql-credentials: a database user and password are required" >&2
        return 1
    fi
    __file="$(umask 077 && mktemp "${TMPDIR:-/tmp}/zena-mysql.XXXXXX")" || return 1
    _MYSQL_CREDENTIAL_FILES+=("$__file")
    chmod 600 "$__file"
    {
        printf '[client]\n'
        printf 'user="%s"\n' "$(_mysql_option_escape "$user")"
        printf 'password="%s"\n' "$(_mysql_option_escape "$password")"
        if [ -n "$host" ]; then printf 'host=%s\n' "$host"; fi
        if [ -n "$port" ]; then printf 'port=%s\n' "$port"; fi
    } > "$__file"
    printf -v "$__var" '%s' "$__file"
}

# Runs <client> inside a running container with credentials supplied from the
# host: a 0600 option file is copied in with `docker cp`, used, and removed on
# both sides even when the client fails. Stdin/stdout pass through.
mysql_container_client() {
    local container="$1" user="$2" password="$3" client="$4" cnf target rc
    shift 4
    mysql_option_file cnf "$user" "$password" || return 1
    target="/tmp/zena-mysql-$$-$RANDOM.cnf"
    docker cp "$cnf" "$container:$target" || { rm -f "$cnf"; return 1; }
    if docker exec -i "$container" "$client" --defaults-extra-file="$target" "$@"; then
        rc=0
    else
        rc=$?
    fi
    docker exec "$container" rm -f "$target" || true
    rm -f "$cnf"
    return "$rc"
}

# Runs <client> as MySQL root inside a docker-compose service using the
# container's own MYSQL_ROOT_PASSWORD: the option file is written inside the
# container, so the root password never passes through the host and no
# default is ever substituted. Stdin/stdout pass through.
mysql_compose_root_client() {
    local compose_file="$1" service="$2"
    shift 2
    local -a compose=(docker-compose)
    if [ "$compose_file" != "-" ]; then
        compose+=(-f "$compose_file")
    fi
    # shellcheck disable=SC2016 # expanded inside the container, not here
    "${compose[@]}" exec -T "$service" sh -c '
        if [ -z "${MYSQL_ROOT_PASSWORD:-}" ]; then
            echo "mysql-credentials: MYSQL_ROOT_PASSWORD is not set in the container" >&2
            exit 1
        fi
        esc() {
            s=$1; out=
            while [ -n "$s" ]; do
                c=${s%"${s#?}"}; s=${s#?}
                case $c in \\|\") out="$out\\$c" ;; *) out="$out$c" ;; esac
            done
            printf "%s" "$out"
        }
        f=$(umask 077 && mktemp) || exit 1
        trap "rm -f \"$f\"" EXIT
        chmod 600 "$f"
        printf "[client]\nuser=root\npassword=\"%s\"\n" "$(esc "$MYSQL_ROOT_PASSWORD")" > "$f"
        client=$1; shift
        "$client" --defaults-extra-file="$f" "$@"
    ' sh "$@"
}
