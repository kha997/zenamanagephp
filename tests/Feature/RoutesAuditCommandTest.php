<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RoutesAuditCommandTest extends TestCase
{
    public function test_routes_audit_command_generates_markdown_report(): void
    {
        $output = storage_path('app/routes-audit-test.md');
        if (File::exists($output)) {
            File::delete($output);
        }

        Artisan::call('routes:audit', [
            '--format' => 'md',
            '--output' => $output,
        ]);

        $this->assertFileExists($output);
        $content = File::get($output);
        $this->assertStringContainsString('Duplicates', $content);

        File::delete($output);
    }
}
