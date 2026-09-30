<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * GAP-059: no tracked shell script passes a password option to artisan on the
 * command line (smtp:configure takes it with --password-stdin).
 */
class NoCommandLineSmtpPasswordTest extends TestCase
{
    public function test_no_tracked_shell_script_passes_a_password_option_to_artisan(): void
    {
        $root = dirname(__DIR__, 2);
        exec('git -C ' . escapeshellarg($root) . ' ls-files -z -- "*.sh"', $out, $code);
        $this->assertSame(0, $code, 'git ls-files failed');

        $offenders = [];
        foreach (array_filter(explode("\0", implode("\n", $out))) as $relative) {
            foreach (file($root . '/' . $relative) ?: [] as $index => $line) {
                if (str_contains($line, 'artisan') && preg_match('/--password=/', $line) === 1) {
                    $offenders[] = $relative . ':' . ($index + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'Pass passwords to artisan on STDIN, never as an argument (GAP-059)');
    }
}
