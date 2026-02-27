<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\WorkTemplatePackageService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WorkTemplatePackageServiceTest extends TestCase
{
    public function test_assert_supported_schema_version_accepts_current_version(): void
    {
        $service = new WorkTemplatePackageService();

        $service->assertSupportedSchemaVersion(WorkTemplatePackageService::SCHEMA_VERSION);

        $this->assertTrue(true);
    }

    public function test_assert_supported_schema_version_rejects_unsupported_version(): void
    {
        $service = new WorkTemplatePackageService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported schema_version');

        $service->assertSupportedSchemaVersion('0.9.0');
    }
}
