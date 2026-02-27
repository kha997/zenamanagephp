<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DeliverableTemplateVersionService;
use InvalidArgumentException;
use Tests\TestCase;

class DeliverableTemplateVersionServiceTest extends TestCase
{
    public function test_it_computes_sha256_checksum_for_html_payload(): void
    {
        $service = new DeliverableTemplateVersionService();
        $html = '<html><body><h1>Hello</h1></body></html>';

        $this->assertSame(hash('sha256', $html), $service->computeChecksum($html));
    }

    public function test_it_infers_placeholders_from_html_when_spec_is_missing(): void
    {
        $service = new DeliverableTemplateVersionService();

        $spec = $service->normalizePlaceholdersSpec(null, '<div>{{project.name}} {{project.code}}</div>');

        $this->assertSame('1.0.0', $spec['schema_version']);
        $this->assertSame('project.code', $spec['placeholders'][0]['key']);
        $this->assertSame('project.name', $spec['placeholders'][1]['key']);
    }

    public function test_it_validates_and_normalizes_placeholders_spec(): void
    {
        $service = new DeliverableTemplateVersionService();

        $spec = $service->normalizePlaceholdersSpec([
            'schema_version' => '1.1.0',
            'placeholders' => [
                ['key' => 'project.name', 'type' => 'string', 'required' => true],
                ['key' => 'project.budget', 'type' => 'number', 'required' => false],
            ],
        ], '<div>{{project.name}}</div>');

        $this->assertSame('1.1.0', $spec['schema_version']);
        $this->assertCount(2, $spec['placeholders']);
        $this->assertSame('project.budget', $spec['placeholders'][0]['key']);
        $this->assertSame('number', $spec['placeholders'][0]['type']);
    }

    public function test_it_rejects_invalid_placeholder_schema(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $service = new DeliverableTemplateVersionService();
        $service->normalizePlaceholdersSpec([
            'placeholders' => [
                ['key' => 'project name', 'type' => 'string'],
            ],
        ], '<div>{{project.name}}</div>');
    }
}
