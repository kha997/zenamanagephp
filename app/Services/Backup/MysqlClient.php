<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Process;

/**
 * Runs the MySQL command-line clients without ever placing credentials in
 * process arguments (GAP-054): user and password go into a temporary option
 * file readable only by the current user, removed on every exit path.
 */
final class MysqlClient
{
    /**
     * @param array<string, mixed> $config
     * @param list<string> $options
     * @param list<string> $tables
     */
    public function dump(array $config, string $resultFile, array $options = [], array $tables = []): void
    {
        $this->withOptionFile($config, function (string $optionFile) use ($config, $resultFile, $options, $tables): void {
            $command = array_merge(
                ['mysqldump', '--defaults-extra-file=' . $optionFile],
                $this->connectionArguments($config),
                $options,
                ['--result-file=' . $resultFile, (string) $config['database']],
                $tables,
            );

            $result = Process::timeout(3600)->run($command);
            if (!$result->successful()) {
                throw new \RuntimeException('mysqldump failed with exit code ' . $result->exitCode());
            }
        });
    }

    /** @param array<string, mixed> $config */
    public function restore(array $config, string $inputFile): void
    {
        $this->withOptionFile($config, function (string $optionFile) use ($config, $inputFile): void {
            $input = fopen($inputFile, 'rb');
            if ($input === false) {
                throw new \RuntimeException('Cannot open restore input file');
            }

            try {
                $command = array_merge(
                    ['mysql', '--defaults-extra-file=' . $optionFile],
                    $this->connectionArguments($config),
                    [(string) $config['database']],
                );
                $result = Process::timeout(3600)->input($input)->run($command);
            } finally {
                fclose($input);
            }

            if (!$result->successful()) {
                throw new \RuntimeException('mysql restore failed with exit code ' . $result->exitCode());
            }
        });
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private function connectionArguments(array $config): array
    {
        return [
            '--host=' . (string) ($config['host'] ?? '127.0.0.1'),
            '--port=' . (string) ($config['port'] ?? 3306),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param callable(string): void $callback
     */
    private function withOptionFile(array $config, callable $callback): void
    {
        $path = tempnam(sys_get_temp_dir(), 'zena-mysql-');
        if ($path === false) {
            throw new \RuntimeException('Cannot create MySQL option file');
        }

        try {
            chmod($path, 0600);
            $contents = "[client]\n"
                . 'user=' . $this->quote((string) ($config['username'] ?? '')) . "\n"
                . 'password=' . $this->quote((string) ($config['password'] ?? '')) . "\n";
            if (file_put_contents($path, $contents) === false) {
                throw new \RuntimeException('Cannot write MySQL option file');
            }

            $callback($path);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    private function quote(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
