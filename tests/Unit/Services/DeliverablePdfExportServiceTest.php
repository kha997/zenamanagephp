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
}
