<?php

namespace Tests\Unit\Services\Backup;

use App\Services\Backup\MysqlClient;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class MysqlClientTest extends TestCase
{
    private const PASSWORD = 'p@ss w0rd;$(rm -rf /)"\'`&';

    /** @return array<string, mixed> */
    private function config(): array
    {
        return ['host' => 'db.internal', 'port' => 3307, 'username' => 'zena', 'password' => self::PASSWORD, 'database' => 'zena_prod'];
    }

    /** @param array<int, string>|string $command */
    private function optionFileFrom(array|string $command): string
    {
        $this->assertIsArray($command, 'command must be an argument array, never a shell string');
        $this->assertStringStartsWith('--defaults-extra-file=', $command[1], '--defaults-extra-file must be the first option');

        return substr($command[1], strlen('--defaults-extra-file='));
    }

    public function test_dump_keeps_password_out_of_arguments_and_in_a_private_option_file(): void
    {
        $seen = [];
        Process::fake(function (PendingProcess $process) use (&$seen) {
            $file = $this->optionFileFrom($process->command);
            $seen = [
                'command' => $process->command,
                'mode' => fileperms($file) & 0777,
                'contents' => (string) file_get_contents($file),
                'file' => $file,
            ];

            return Process::result();
        });

        (new MysqlClient())->dump($this->config(), '/tmp/out.sql', ['--single-transaction'], ['users']);

        $this->assertSame('mysqldump', $seen['command'][0]);
        $this->assertNotContains(self::PASSWORD, $seen['command']);
        foreach ($seen['command'] as $arg) {
            $this->assertStringNotContainsString(self::PASSWORD, $arg);
            $this->assertStringNotContainsString('--password', $arg);
        }
        $this->assertContains('--host=db.internal', $seen['command']);
        $this->assertContains('--port=3307', $seen['command']);
        $this->assertContains('--result-file=/tmp/out.sql', $seen['command']);
        $this->assertSame(['zena_prod', 'users'], array_slice($seen['command'], -2));
        $this->assertSame(0600, $seen['mode']);
        $this->assertStringContainsString('user="zena"', $seen['contents']);
        $this->assertStringContainsString('password=', $seen['contents']);
        $this->assertFileDoesNotExist($seen['file']);
    }

    public function test_option_file_is_removed_when_dump_fails(): void
    {
        $file = null;
        Process::fake(function (PendingProcess $process) use (&$file) {
            $file = $this->optionFileFrom($process->command);

            return Process::result(errorOutput: 'boom', exitCode: 2);
        });

        try {
            (new MysqlClient())->dump($this->config(), '/tmp/out.sql');
            $this->fail('dump must throw on a non-zero exit');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString(self::PASSWORD, $e->getMessage());
        }

        $this->assertNotNull($file);
        $this->assertFileDoesNotExist($file);
    }

    public function test_restore_streams_the_file_without_shell_redirection(): void
    {
        $input = tempnam(sys_get_temp_dir(), 'gap054-restore-');
        file_put_contents($input, 'SELECT 1;');
        $seen = [];
        Process::fake(function (PendingProcess $process) use (&$seen) {
            $this->optionFileFrom($process->command);
            $seen = $process->command;

            return Process::result();
        });

        (new MysqlClient())->restore($this->config(), $input);
        unlink($input);

        $this->assertSame('mysql', $seen[0]);
        $this->assertSame('zena_prod', end($seen));
        $this->assertNotContains('<', $seen);
    }
}
