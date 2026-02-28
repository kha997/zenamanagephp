<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\DeliverablePdfExportUnavailableException;
use App\Services\DeliverablePdfExportService;
use Tests\TestCase;

class DeliverablePdfExportServiceTest extends TestCase
{
    public function test_it_renders_pdf_bytes_via_the_configured_node_script(): void
    {
        $service = new class extends DeliverablePdfExportService
        {
            /** @var array<int, string> */
            public array $capturedCommand = [];

            /** @var array<string, string> */
            public array $capturedEnvironment = [];

            protected function ensureDependenciesAvailable(string $nodeBinary, array $environment): void
            {
            }

            protected function runCommand(array $command, array $environment): void
            {
                $this->capturedCommand = $command;
                $this->capturedEnvironment = $environment;

                file_put_contents($command[3], "%PDF-1.7\nfake pdf\n");
            }
        };

        $pdf = $service->render('<html><body><h1>North Tower</h1></body></html>');

        $this->assertSame("%PDF-1.7\nfake pdf\n", $pdf);
        $this->assertStringEndsWith('/node', $service->capturedCommand[0]);
        $this->assertSame(base_path('scripts/render_deliverable_pdf.mjs'), $service->capturedCommand[1]);
        $this->assertSame('--preset', $service->capturedCommand[4]);
        $this->assertSame('a4_clean', $service->capturedCommand[5]);
        $this->assertSame('--orientation', $service->capturedCommand[6]);
        $this->assertSame('portrait', $service->capturedCommand[7]);
        $this->assertSame('--header-footer', $service->capturedCommand[8]);
        $this->assertSame('true', $service->capturedCommand[9]);
        $this->assertSame('--margins', $service->capturedCommand[10]);
        $this->assertSame('18,14,18,14', $service->capturedCommand[11]);
        $this->assertSame([], $service->capturedEnvironment);
    }

    public function test_it_passes_browser_path_when_configured(): void
    {
        $previous = getenv('DELIVERABLE_PDF_BROWSER_PATH');
        putenv('DELIVERABLE_PDF_BROWSER_PATH=/tmp/chromium');

        try {
            $service = new class extends DeliverablePdfExportService
            {
                /** @var array<string, string> */
                public array $capturedEnvironment = [];

                protected function ensureDependenciesAvailable(string $nodeBinary, array $environment): void
                {
                }

                protected function runCommand(array $command, array $environment): void
                {
                    $this->capturedEnvironment = $environment;

                    file_put_contents($command[3], "%PDF-1.7\nfake pdf\n");
                }
            };

            $service->render('<html><body>Export</body></html>');

            $this->assertSame('/tmp/chromium', $service->capturedEnvironment['DELIVERABLE_PDF_BROWSER_PATH'] ?? null);
        } finally {
            if ($previous === false) {
                putenv('DELIVERABLE_PDF_BROWSER_PATH');
            } else {
                putenv('DELIVERABLE_PDF_BROWSER_PATH=' . $previous);
            }
        }
    }

    public function test_it_throws_a_specific_exception_when_node_is_unavailable(): void
    {
        $service = new class extends DeliverablePdfExportService
        {
            protected function resolveNodeBinary(): ?string
            {
                return null;
            }

            protected function warnUnavailable(string $reason): void
            {
            }
        };

        $this->expectException(DeliverablePdfExportUnavailableException::class);
        $this->expectExceptionMessage(DeliverablePdfExportUnavailableException::MESSAGE);

        $service->render('<html><body>Export</body></html>');
    }

    public function test_it_normalizes_invalid_pdf_options_to_safe_defaults(): void
    {
        $service = new DeliverablePdfExportService();

        $normalized = $service->normalizeOptions([
            'preset' => 'bad',
            'orientation' => 'sideways',
            'header_footer' => 'not-bool',
            'margin_mm' => [
                'top' => -3,
                'right' => 55,
                'bottom' => '18.4',
                'left' => 'oops',
            ],
        ]);

        $this->assertSame('a4_clean', $normalized['preset']);
        $this->assertSame('portrait', $normalized['orientation']);
        $this->assertTrue($normalized['header_footer']);
        $this->assertSame([
            'top' => 18,
            'right' => 14,
            'bottom' => 18,
            'left' => 14,
        ], $normalized['margin_mm']);
    }

    public function test_it_builds_expected_node_arguments_for_pdf_options(): void
    {
        $service = new class extends DeliverablePdfExportService
        {
            public function exposeBuildCommand(string $nodeBinary, string $htmlPath, string $pdfPath, array $options, array $documentMeta): array
            {
                return $this->buildCommand($nodeBinary, $htmlPath, $pdfPath, $options, $documentMeta);
            }
        };

        $command = $service->exposeBuildCommand('/usr/local/bin/node', '/tmp/input.html', '/tmp/output.pdf', [
            'preset' => 'a4_clean',
            'orientation' => 'landscape',
            'header_footer' => false,
            'margin_mm' => [
                'top' => 20,
                'right' => 12,
                'bottom' => 22,
                'left' => 16,
            ],
        ], [
            'project_name' => 'North Tower',
            'template_semver' => '1.2.3',
            'generated_at' => '2026-02-28T12:34:56+00:00',
        ]);

        $this->assertSame([
            '/usr/local/bin/node',
            base_path('scripts/render_deliverable_pdf.mjs'),
            '/tmp/input.html',
            '/tmp/output.pdf',
            '--preset',
            'a4_clean',
            '--orientation',
            'landscape',
            '--header-footer',
            'false',
            '--margins',
            '20,12,22,16',
            '--project-name',
            'North Tower',
            '--template-semver',
            '1.2.3',
            '--generated-at',
            '2026-02-28T12:34:56+00:00',
        ], $command);
    }
}
