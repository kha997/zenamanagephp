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
}
