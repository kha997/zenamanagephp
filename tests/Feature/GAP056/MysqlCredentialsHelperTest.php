<?php

namespace Tests\Feature\GAP056;

use PHPUnit\Framework\TestCase;

/**
 * GAP-056: scripts/lib/mysql-credentials.sh hands MySQL credentials to clients
 * through a 0600 option file, never on a process command line.
 *
 * The helper runs in real bash with stub mysql/mysqldump/docker/docker-compose
 * executables on PATH that record their argv and the option file they receive.
 */
final class MysqlCredentialsHelperTest extends TestCase
{
    private const SECRET = 's3cr3t "quoted" \\ pass#word';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/gap056-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/bin', 0700, true);
        mkdir($this->dir . '/tmp', 0700, true);

        // Records argv, and for any --defaults-extra-file=<f> records the
        // file's octal mode and content. Exit code comes from STUB_EXIT.
        $client = <<<'SH'
#!/usr/bin/env bash
log="$STUB_LOG"
printf 'ARGV %s\n' "$(basename "$0") $*" >> "$log"
for a in "$@"; do
  case "$a" in
    --defaults-extra-file=*)
      f="${a#--defaults-extra-file=}"
      mode="$(stat -c %a "$f" 2>/dev/null || stat -f %Lp "$f")"
      printf 'MODE %s\n' "$mode" >> "$log"
      printf 'FILE %s\n' "$f" >> "$log"
      sed 's/^/CNF /' "$f" >> "$log"
      ;;
  esac
done
exit "${STUB_EXIT:-0}"
SH;
        foreach (['mysql', 'mysqldump'] as $name) {
            file_put_contents($this->dir . "/bin/$name", $client);
            chmod($this->dir . "/bin/$name", 0755);
        }

        // docker: `cp <src> <ctr>:<dst>` copies into a fake container dir;
        // `exec [-i] <ctr> <client> args` runs the stub client with paths
        // mapped into that dir; `exec <ctr> rm -f <path>` removes it.
        $docker = <<<'SH'
#!/usr/bin/env bash
printf 'ARGV docker %s\n' "$*" >> "$STUB_LOG"
root="$STUB_CONTAINER_ROOT"
case "$1" in
  cp) dst="${3#*:}"; mkdir -p "$root$(dirname "$dst")"; cp -p "$2" "$root$dst" ;;
  exec)
    shift; [ "$1" = "-i" ] && shift; shift
    if [ "$1" = "rm" ]; then rm -f "$root${3}"; exit 0; fi
    client="$1"; shift; args=()
    for a in "$@"; do
      case "$a" in --defaults-extra-file=*) args+=("--defaults-extra-file=$root${a#--defaults-extra-file=}");; *) args+=("$a");; esac
    done
    exec "$client" "${args[@]}"
    ;;
esac
SH;
        file_put_contents($this->dir . '/bin/docker', $docker);
        chmod($this->dir . '/bin/docker', 0755);

        // docker-compose ... exec -T <service> sh -c '<script>' sh <client> args:
        // run the in-container script locally with the container's env.
        $compose = <<<'SH'
#!/usr/bin/env bash
printf 'ARGV docker-compose %s\n' "$*" >> "$STUB_LOG"
while [ "$#" -gt 0 ] && [ "$1" != "sh" ]; do shift; done
shift
MYSQL_ROOT_PASSWORD="$STUB_CONTAINER_ROOT_PASSWORD" exec sh "$@"
SH;
        file_put_contents($this->dir . '/bin/docker-compose', $compose);
        chmod($this->dir . '/bin/docker-compose', 0755);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    /** @return array{0:int,1:string} exit code and recorded log */
    private function runHelper(string $body, array $env = []): array
    {
        $helper = dirname(__DIR__, 3) . '/scripts/lib/mysql-credentials.sh';
        $script = "set -e\nsource " . escapeshellarg($helper) . "\n" . $body . "\n";
        $log = $this->dir . '/log';
        touch($log);
        $envPrefix = 'env -i PATH=' . escapeshellarg($this->dir . '/bin:/usr/bin:/bin')
            . ' HOME=' . escapeshellarg($this->dir)
            . ' TMPDIR=' . escapeshellarg($this->dir . '/tmp')
            . ' STUB_LOG=' . escapeshellarg($log)
            . ' STUB_CONTAINER_ROOT=' . escapeshellarg($this->dir . '/ctr');
        foreach ($env as $k => $v) {
            $envPrefix .= ' ' . $k . '=' . escapeshellarg($v);
        }
        exec($envPrefix . ' bash -c ' . escapeshellarg($script) . ' 2>&1', $out, $code);

        return [$code, (string) file_get_contents($log) . "\n--- output ---\n" . implode("\n", $out)];
    }

    private function argvLines(string $log): string
    {
        return implode("\n", array_filter(explode("\n", $log), fn ($l) => str_starts_with($l, 'ARGV ')));
    }

    public function test_host_client_gets_a_0600_option_file_and_no_password_on_argv(): void
    {
        [$code, $log] = $this->runHelper(
            'mysql_option_file cnf app "$PW" db.internal 3307' . "\n"
            . 'mysqldump --defaults-extra-file="$cnf" zena > /dev/null',
            ['PW' => self::SECRET]
        );

        $this->assertSame(0, $code, $log);
        $this->assertStringNotContainsString('s3cr3t', $this->argvLines($log));
        $this->assertStringContainsString('MODE 600', $log);
        $this->assertStringContainsString('CNF user="app"', $log);
        $this->assertStringContainsString('CNF host=db.internal', $log);
        $this->assertStringContainsString('CNF port=3307', $log);
        $this->assertStringContainsString('CNF password="s3cr3t \\"quoted\\" \\\\ pass#word"', $log);
        $this->assertSame([], glob($this->dir . '/tmp/*'), 'option file must be removed on exit');
    }

    public function test_option_file_is_removed_even_when_the_client_fails(): void
    {
        [$code, $log] = $this->runHelper(
            'mysql_option_file cnf app "$PW"' . "\n" . 'mysql --defaults-extra-file="$cnf" -e "SELECT 1"',
            ['PW' => self::SECRET, 'STUB_EXIT' => '3']
        );

        $this->assertNotSame(0, $code, $log);
        $this->assertSame([], glob($this->dir . '/tmp/*'), 'option file must be removed on failure');
    }

    public function test_missing_password_fails_closed_without_calling_a_client(): void
    {
        [$code, $log] = $this->runHelper(
            'mysql_option_file cnf app ""' . "\n" . 'mysqldump --defaults-extra-file="$cnf" zena'
        );

        $this->assertNotSame(0, $code, $log);
        $this->assertStringNotContainsString('ARGV mysqldump', $log);
    }

    public function test_container_client_copies_a_0600_file_and_cleans_both_sides(): void
    {
        [$code, $log] = $this->runHelper(
            'mysql_container_client zena_db app "$PW" mysqldump zena > /dev/null',
            ['PW' => self::SECRET]
        );

        $this->assertSame(0, $code, $log);
        $this->assertStringNotContainsString('s3cr3t', $this->argvLines($log));
        $this->assertStringContainsString('MODE 600', $log);
        $this->assertStringContainsString('ARGV mysqldump --defaults-extra-file=', $log);
        $this->assertSame([], glob($this->dir . '/tmp/*'), 'host copy must be removed');
        $this->assertSame([], glob($this->dir . '/ctr/tmp/*') ?: [], 'container copy must be removed');
    }

    public function test_compose_root_client_uses_the_containers_own_root_password(): void
    {
        [$code, $log] = $this->runHelper(
            'mysql_compose_root_client docker-compose.prod.yml mysql mysqldump --all-databases > /dev/null',
            ['STUB_CONTAINER_ROOT_PASSWORD' => self::SECRET]
        );

        $this->assertSame(0, $code, $log);
        $this->assertStringNotContainsString('s3cr3t', $this->argvLines($log));
        $this->assertStringNotContainsString('root_password', $log);
        $this->assertStringContainsString('MODE 600', $log);
        $this->assertStringContainsString('CNF user=root', $log);
        $this->assertStringContainsString('CNF password="s3cr3t \\"quoted\\" \\\\ pass#word"', $log);
        $this->assertStringContainsString('ARGV docker-compose -f docker-compose.prod.yml exec -T mysql sh -c', $log);
    }

    public function test_compose_root_client_fails_closed_when_the_container_has_no_root_password(): void
    {
        [$code, $log] = $this->runHelper(
            'mysql_compose_root_client - db mysqldump --all-databases',
            ['STUB_CONTAINER_ROOT_PASSWORD' => '']
        );

        $this->assertNotSame(0, $code, $log);
        $this->assertStringNotContainsString('ARGV mysqldump', $log);
    }
}
