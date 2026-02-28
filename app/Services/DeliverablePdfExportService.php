<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DeliverablePdfExportUnavailableException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class DeliverablePdfExportService
{
    public function render(string $html): string
    {
        $htmlPath = $this->tempPath('html');
        $pdfPath = $this->tempPath('pdf');
        $environment = $this->buildEnvironment();
        $nodeBinary = $this->resolveNodeBinary();

        try {
            if (file_put_contents($htmlPath, $html) === false) {
                throw new RuntimeException('Failed to write deliverable export HTML to a temporary file.');
            }

            if ($nodeBinary === null) {
                $this->warnUnavailable('Node.js binary is missing for deliverable PDF export.');

                throw new DeliverablePdfExportUnavailableException();
            }

            $this->ensureDependenciesAvailable($nodeBinary, $environment);
            $this->runCommand($this->buildCommand($nodeBinary, $htmlPath, $pdfPath), $environment);

            if (!is_file($pdfPath)) {
                throw new RuntimeException('Deliverable PDF export did not produce an output file.');
            }

            $pdf = file_get_contents($pdfPath);
            if ($pdf === false || $pdf === '') {
                throw new RuntimeException('Deliverable PDF export returned an empty file.');
            }

            return $pdf;
        } finally {
            @unlink($htmlPath);
            @unlink($pdfPath);
        }
    }

    /**
     * @param array<int, string> $command
     * @param array<string, string> $environment
     */
    protected function runCommand(array $command, array $environment): void
    {
        $timeout = (int) env('DELIVERABLE_PDF_PROCESS_TIMEOUT', 60);
        $result = Process::timeout($timeout)
            ->env($environment)
            ->run($command);

        if ($result->failed()) {
            $message = trim($result->errorOutput());
            if ($message === '') {
                $message = trim($result->output());
            }

            throw new RuntimeException($message !== '' ? $message : 'Deliverable PDF export failed.');
        }
    }

    /**
     * @return array<int, string>
     */
    protected function buildCommand(string $nodeBinary, string $htmlPath, string $pdfPath): array
    {
        return [
            $nodeBinary,
            $this->scriptPath(),
            $htmlPath,
            $pdfPath,
        ];
    }

    /**
     * @param array<string, string> $environment
     */
    protected function ensureDependenciesAvailable(string $nodeBinary, array $environment): void
    {
        $timeout = (int) env('DELIVERABLE_PDF_PROCESS_TIMEOUT', 60);
        $result = Process::timeout(min($timeout, 15))
            ->env($environment)
            ->run([
                $nodeBinary,
                $this->scriptPath(),
                '--check-deps',
            ]);

        if ($result->successful()) {
            return;
        }

        $message = trim($result->errorOutput());
        if ($message === '') {
            $message = trim($result->output());
        }

        $this->warnUnavailable(
            $message !== '' ? $message : 'Playwright Chromium is unavailable for deliverable PDF export.'
        );

        throw new DeliverablePdfExportUnavailableException();
    }

    protected function scriptPath(): string
    {
        return base_path('scripts/render_deliverable_pdf.mjs');
    }

    /**
     * @return array<string, string>
     */
    protected function buildEnvironment(): array
    {
        $environment = [];
        $browserPath = trim((string) env('DELIVERABLE_PDF_BROWSER_PATH', ''));

        if ($browserPath !== '') {
            $environment['DELIVERABLE_PDF_BROWSER_PATH'] = $browserPath;
        }

        return $environment;
    }

    protected function resolveNodeBinary(): ?string
    {
        $configuredBinary = trim((string) env('DELIVERABLE_PDF_NODE_BINARY', 'node'));
        if ($configuredBinary === '') {
            $configuredBinary = 'node';
        }

        if (str_contains($configuredBinary, DIRECTORY_SEPARATOR)) {
            return is_file($configuredBinary) && is_executable($configuredBinary) ? $configuredBinary : null;
        }

        $path = getenv('PATH');
        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            $directory = trim($directory);
            if ($directory === '') {
                continue;
            }

            $candidate = $directory . DIRECTORY_SEPARATOR . $configuredBinary;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function warnUnavailable(string $reason): void
    {
        Log::warning($reason, [
            'service' => self::class,
            'script' => $this->scriptPath(),
        ]);
    }

    private function tempPath(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'deliverable-export-');
        if ($path === false) {
            throw new RuntimeException('Failed to allocate a temporary file for deliverable PDF export.');
        }

        $target = $path . '.' . $extension;
        if (!@rename($path, $target)) {
            @unlink($path);

            throw new RuntimeException('Failed to prepare a temporary file for deliverable PDF export.');
        }

        return $target;
    }
}
