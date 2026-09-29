<?php

namespace Tests\Feature\GAP056;

use PHPUnit\Framework\TestCase;

/**
 * GAP-056: scripts/setup-production.sh::create_backup_script() must keep the
 * database password out of the generated cron script and off the command
 * line. The function is extracted and run with stub sudo/crontab in a temp dir.
 */
final class SetupProductionBackupScriptTest extends TestCase
{
    private const SECRET = 'pr0d "db" \\ pass#1';

    public function test_generated_backup_script_holds_no_secret_and_is_root_only(): void
    {
        $dir = sys_get_temp_dir() . '/gap056-setup-' . bin2hex(random_bytes(6));
        mkdir($dir . '/bin', 0700, true);
        file_put_contents($dir . '/bin/sudo', "#!/usr/bin/env bash\nprintf 'SUDO %s\\n' \"\$*\" >> \"\$STUB_LOG\"\n\"\$@\"\n");
        file_put_contents($dir . '/bin/crontab', "#!/usr/bin/env bash\nif [ \"\$1\" = \"-\" ]; then cat > \"\$STUB_CRON\"; fi\nexit 0\n");
        chmod($dir . '/bin/sudo', 0755);
        chmod($dir . '/bin/crontab', 0755);

        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/scripts/setup-production.sh');
        $this->assertSame(1, preg_match('/^create_backup_script\(\) \{\n.*?^\}\n/ms', $source, $m));
        $harness = "set -e\nlog() { :; }\nsuccess() { :; }\n" . $m[0] . "\ncreate_backup_script\n";

        $env = [
            'PATH' => $dir . '/bin:/usr/bin:/bin',
            'STUB_LOG' => $dir . '/sudo.log',
            'STUB_CRON' => $dir . '/cron',
            'BACKUP_PATH' => $dir . '/backups',
            'PROJECT_PATH' => $dir . '/project',
            'DB_USER' => 'zena_user',
            'DB_PASS' => self::SECRET,
            'DB_NAME' => 'zena_prod',
            'BACKUP_CNF_PATH' => $dir . '/etc/backup.cnf',
            'BACKUP_SCRIPT_PATH' => $dir . '/usr-local-bin/zenamanage-backup',
        ];
        mkdir($dir . '/usr-local-bin');
        $prefix = 'env -i';
        foreach ($env as $k => $v) {
            $prefix .= ' ' . $k . '=' . escapeshellarg($v);
        }
        exec($prefix . ' bash -c ' . escapeshellarg($harness) . ' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));

        $script = (string) file_get_contents($env['BACKUP_SCRIPT_PATH']);
        $cnf = (string) file_get_contents($env['BACKUP_CNF_PATH']);
        $sudoLog = (string) file_get_contents($env['STUB_LOG']);

        $this->assertStringNotContainsString('pr0d', $script, 'generated script must not contain the password');
        $this->assertStringNotContainsString('pr0d', $sudoLog, 'password must never be a process argument');
        $this->assertStringContainsString('--defaults-extra-file="$CNF"', $script);
        $this->assertStringContainsString("--exclude='.env'", $script);
        $this->assertSame('0700', substr(sprintf('%o', fileperms($env['BACKUP_SCRIPT_PATH'])), -4));
        $this->assertSame('0600', substr(sprintf('%o', fileperms($env['BACKUP_CNF_PATH'])), -4));
        $this->assertSame('0700', substr(sprintf('%o', fileperms($env['BACKUP_PATH'])), -4));
        $this->assertStringContainsString('password="pr0d \\"db\\" \\\\ pass#1"', $cnf);
        $this->assertStringContainsString('SUDO crontab -', $sudoLog);
        $this->assertStringContainsString('0 2 * * * ' . $env['BACKUP_SCRIPT_PATH'], (string) file_get_contents($env['STUB_CRON']));

        exec('bash -n ' . escapeshellarg($env['BACKUP_SCRIPT_PATH']), $o2, $syntax);
        $this->assertSame(0, $syntax, 'generated script must be valid bash');

        exec('rm -rf ' . escapeshellarg($dir));
    }
}
