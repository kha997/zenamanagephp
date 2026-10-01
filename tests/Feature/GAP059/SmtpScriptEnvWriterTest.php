<?php

namespace Tests\Feature\GAP059;

use Dotenv\Parser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * GAP-059: scripts/configure-production-smtp.sh must write .env values that
 * parse back exactly, never put them on a child process's command line, and
 * never leave .env.bak copies. update_env_var() is extracted and run in real
 * bash; awk/sed are wrapped to record their argv.
 */
final class SmtpScriptEnvWriterTest extends TestCase
{
    private string $dir;

    private string $script;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/gap059-sh-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/bin', 0700, true);
        foreach (['awk', 'sed'] as $tool) {
            $real = trim((string) shell_exec('command -v ' . $tool));
            file_put_contents($this->dir . "/bin/$tool", "#!/usr/bin/env bash\nprintf 'ARGV %s\\n' \"\$*\" >> \"\$STUB_LOG\"\nexec " . escapeshellarg($real) . " \"\$@\"\n");
            chmod($this->dir . "/bin/$tool", 0755);
        }
        $this->script = (string) file_get_contents(dirname(__DIR__, 3) . '/scripts/configure-production-smtp.sh');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_update_env_var_writes_values_that_parse_back_exactly(): void
    {
        $this->assertSame(1, preg_match('/^update_env_var\(\) \{\n.*?^\}\n/ms', $this->script, $m));
        $env = $this->dir . '/.env';
        file_put_contents($env, "APP_NAME=Zena\nMONITORING_ALERT_EMAIL=old\n");
        chmod($env, 0640);

        $values = [
            'MONITORING_ALERT_EMAIL' => 'ops&alerts/team@example.test',
            'MAIL_QUEUE_CONNECTION' => 'redis',
            'TRICKY' => 'p$1w "q" back\\slash a b#c x${HOME}y',
        ];
        $harness = "set -e\nENV_FILE=" . escapeshellarg($env) . "\n" . $m[0];
        foreach ($values as $k => $v) {
            $harness .= 'update_env_var ' . escapeshellarg($k) . ' "$' . 'V_' . $k . "\"\n";
        }
        $cmd = 'env -i PATH=' . escapeshellarg($this->dir . '/bin:/usr/bin:/bin') . ' STUB_LOG=' . escapeshellarg($this->dir . '/argv.log');
        foreach ($values as $k => $v) {
            $cmd .= ' V_' . $k . '=' . escapeshellarg($v);
        }
        exec($cmd . ' bash -c ' . escapeshellarg($harness) . ' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));

        $parsed = [];
        foreach ((new Parser())->parse((string) file_get_contents($env)) as $entry) {
            $parsed[$entry->getName()] = $entry->getValue()->get()->getChars();
        }
        foreach ($values as $k => $v) {
            $this->assertSame($v, $parsed[$k], $k);
        }
        $this->assertSame('Zena', $parsed['APP_NAME']);
        $this->assertFileDoesNotExist($env . '.bak');
        $this->assertSame('0640', substr(sprintf('%o', fileperms($env)), -4));
        $argv = is_file($this->dir . '/argv.log') ? (string) file_get_contents($this->dir . '/argv.log') : '';
        $this->assertStringNotContainsString('p$1w', $argv, 'values must not appear on a child command line');
        $this->assertStringNotContainsString('ops&alerts', $argv);
    }

    public function test_password_reaches_smtp_configure_on_stdin_only(): void
    {
        $this->assertStringNotContainsString('--password=', $this->script);
        $this->assertMatchesRegularExpression('/printf [\'"]%s\\\\n[\'"] "\$SMTP_PASSWORD" \| php artisan smtp:configure [^\n]*--password-stdin/', $this->script);
        $this->assertStringNotContainsString('update_env_var "MAIL_PASSWORD"', $this->script);
    }

    public function test_env_backup_is_owner_only(): void
    {
        $this->assertMatchesRegularExpression('/\(umask 077 && cp "\$ENV_FILE" "\$BACKUP_ENV_FILE"\)/', $this->script);
    }
}
