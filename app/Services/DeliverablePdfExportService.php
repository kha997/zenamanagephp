<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class DeliverablePdfExportService
{
    public function render(string $html): string
    {
        $htmlPath = $this->tempPath('html');
        $pdfPath = $this->tempPath('pdf');

        try {
            if (file_put_contents($htmlPath, $html) === false) {
                throw new RuntimeException('Failed to write deliverable export HTML to a temporary file.');
            }

            $this->runCommand($this->buildCommand($htmlPath, $pdfPath), $this->buildEnvironment());

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
    protected function buildCommand(string $htmlPath, string $pdfPath): array
    {
        return [
            (string) env('DELIVERABLE_PDF_NODE_BINARY', 'node'),
            base_path('scripts/render_deliverable_pdf.mjs'),
            $htmlPath,
            $pdfPath,
        ];
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
