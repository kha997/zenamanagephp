<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DeliverablePdfExportUnavailableException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class DeliverablePdfExportService
{
    private const DEFAULT_OPTIONS = [
        'preset' => 'a4_clean',
        'orientation' => 'portrait',
        'header_footer' => true,
        'margin_mm' => [
            'top' => 18,
            'right' => 14,
            'bottom' => 18,
            'left' => 14,
        ],
    ];

    private const ALLOWED_PRESETS = ['a4_clean'];

    private const ALLOWED_ORIENTATIONS = ['portrait', 'landscape'];

    public function render(string $html, array $options = [], array $documentMeta = []): string
    {
        $htmlPath = $this->tempPath('html');
        $pdfPath = $this->tempPath('pdf');
        $environment = $this->buildEnvironment();
        $nodeBinary = $this->resolveNodeBinary();
        $normalizedOptions = $this->normalizeOptions($options);

        try {
            if (file_put_contents($htmlPath, $html) === false) {
                throw new RuntimeException('Failed to write deliverable export HTML to a temporary file.');
            }

            if ($nodeBinary === null) {
                $this->warnUnavailable('Node.js binary is missing for deliverable PDF export.');

                throw new DeliverablePdfExportUnavailableException();
            }

            $this->ensureDependenciesAvailable($nodeBinary, $environment);
            $this->runCommand(
                $this->buildCommand($nodeBinary, $htmlPath, $pdfPath, $normalizedOptions, $documentMeta),
                $environment
            );

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
        $result = new Process($command, null, $environment, null, $timeout);
        $result->run();

        if (!$result->isSuccessful()) {
            $message = trim($result->getErrorOutput());
            if ($message === '') {
                $message = trim($result->getOutput());
            }

            throw new RuntimeException($message !== '' ? $message : 'Deliverable PDF export failed.');
        }
    }

    /**
     * @return array<int, string>
     */
    protected function buildCommand(
        string $nodeBinary,
        string $htmlPath,
        string $pdfPath,
        array $options,
        array $documentMeta = []
    ): array {
        $normalizedOptions = $this->normalizeOptions($options);

        $command = [
            $nodeBinary,
            $this->scriptPath(),
            $htmlPath,
            $pdfPath,
            '--preset',
            (string) $normalizedOptions['preset'],
            '--orientation',
            (string) $normalizedOptions['orientation'],
            '--header-footer',
            $normalizedOptions['header_footer'] ? 'true' : 'false',
            '--margins',
            implode(',', [
                (string) $normalizedOptions['margin_mm']['top'],
                (string) $normalizedOptions['margin_mm']['right'],
                (string) $normalizedOptions['margin_mm']['bottom'],
                (string) $normalizedOptions['margin_mm']['left'],
            ]),
        ];

        $projectName = trim((string) ($documentMeta['project_name'] ?? ''));
        if ($projectName !== '') {
            $command[] = '--project-name';
            $command[] = $projectName;
        }

        $templateSemver = trim((string) ($documentMeta['template_semver'] ?? ''));
        if ($templateSemver !== '') {
            $command[] = '--template-semver';
            $command[] = $templateSemver;
        }

        $generatedAt = trim((string) ($documentMeta['generated_at'] ?? ''));
        if ($generatedAt !== '') {
            $command[] = '--generated-at';
            $command[] = $generatedAt;
        }

        return $command;
    }

    public function normalizeOptions(array $options = []): array
    {
        $defaults = self::DEFAULT_OPTIONS;
        $normalized = $defaults;

        $preset = $options['preset'] ?? null;
        if (is_string($preset) && in_array($preset, self::ALLOWED_PRESETS, true)) {
            $normalized['preset'] = $preset;
        }

        $orientation = $options['orientation'] ?? null;
        if (is_string($orientation) && in_array($orientation, self::ALLOWED_ORIENTATIONS, true)) {
            $normalized['orientation'] = $orientation;
        }

        if (array_key_exists('header_footer', $options)) {
            $headerFooter = filter_var($options['header_footer'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (is_bool($headerFooter)) {
                $normalized['header_footer'] = $headerFooter;
            }
        }

        $marginInput = $options['margin_mm'] ?? null;
        $normalized['margin_mm'] = $this->normalizeMargins(is_array($marginInput) ? $marginInput : []);

        return $normalized;
    }

    /**
     * @param array<string, string> $environment
     */
    protected function ensureDependenciesAvailable(string $nodeBinary, array $environment): void
    {
        $timeout = (int) env('DELIVERABLE_PDF_PROCESS_TIMEOUT', 60);
        $result = new Process([
            $nodeBinary,
            $this->scriptPath(),
            '--check-deps',
        ], null, $environment, null, min($timeout, 15));
        $result->run();

        if ($result->isSuccessful()) {
            return;
        }

        $message = trim($result->getErrorOutput());
        if ($message === '') {
            $message = trim($result->getOutput());
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

    /**
     * @param array<string, mixed> $margins
     * @return array{top:int,right:int,bottom:int,left:int}
     */
    private function normalizeMargins(array $margins): array
    {
        $defaults = self::DEFAULT_OPTIONS['margin_mm'];
        $normalized = $defaults;

        foreach ($defaults as $side => $defaultValue) {
            $rawValue = $margins[$side] ?? null;

            if (!is_int($rawValue) && !is_float($rawValue) && !(is_string($rawValue) && is_numeric($rawValue))) {
                continue;
            }

            $value = (int) round((float) $rawValue);
            if ($value < 0 || $value > 50) {
                continue;
            }

            $normalized[$side] = $value;
        }

        return $normalized;
    }
}
