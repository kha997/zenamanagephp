<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class NoCommandLineDatabasePasswordTest extends TestCase
{
    public function test_no_application_code_passes_a_password_option_to_mysql_clients(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app', \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            // Scope the check to MySQL-client contexts: a bare "--password="
            // also matches unrelated artisan option signatures (e.g. an SMTP
            // password option), which are not database credentials on a
            // process command line. The exact --password=/-p check itself is
            // unchanged; only the false-positive surface is narrowed, and no
            // file path is ever exempted.
            $mentionsMysqlClient = stripos($source, 'mysql') !== false;
            if ($mentionsMysqlClient && preg_match('/--password=|\s-p\{?\$|\s-p%s/', $source) === 1) {
                $offenders[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        $this->assertSame([], $offenders, 'MySQL passwords must never be passed as process arguments (GAP-054)');
    }

    /**
     * GAP-056: tracked shell scripts must not pass a MySQL password as a
     * process argument, and must not fall back to a hard-coded root password.
     * Line-based and without any path exemption.
     */
    public function test_no_tracked_shell_script_passes_a_mysql_password_on_the_command_line(): void
    {
        $root = dirname(__DIR__, 2);
        exec('git -C ' . escapeshellarg($root) . ' ls-files -z -- "*.sh"', $out, $code);
        $this->assertSame(0, $code, 'git ls-files failed');
        $files = array_filter(explode("\0", implode("\n", $out)));
        $this->assertNotEmpty($files);

        $offenders = [];
        foreach ($files as $relative) {
            $lines = file($root . '/' . $relative) ?: [];
            $mysqlFile = preg_match('/\bmysql(dump|admin)?\b/', implode('', $lines)) === 1;
            foreach ($lines as $index => $line) {
                $mysqlContext = preg_match('/\bmysql(dump|admin)?\b/', $line) === 1;
                $passwordArg = preg_match('/(^|\s)-p"?\$|(^|\s)-p\$\{|--password=/', $line) === 1;
                $rootFallback = str_contains($line, ':-root_password');
                // A database password variable defaulted to a non-empty
                // literal, e.g. DB_PASSWORD=${DB_PASSWORD:-"password"}.
                $defaultedPassword = $mysqlFile
                    && preg_match('/[A-Z_]*PASS(WORD)?=\$\{[A-Z_]+:-[^}]/', $line) === 1;
                if (($mysqlContext && $passwordArg) || $rootFallback || $defaultedPassword) {
                    $offenders[] = $relative . ':' . ($index + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'MySQL passwords must never be passed as process arguments or defaulted to a literal (GAP-056)');
    }
}
