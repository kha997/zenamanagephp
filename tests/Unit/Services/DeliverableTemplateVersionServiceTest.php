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

        $spec = $service->normalizePlaceholdersSpec(null, '<div>{{ project.name }} {{project.code}}</div>');

        $this->assertSame('1.0.0', $spec['schema_version']);
        $this->assertSame('project.code', $spec['placeholders'][0]['key']);
        $this->assertSame('project.name', $spec['placeholders'][1]['key']);
        $this->assertSame(['project.code', 'project.name'], $spec['found']['all']);
        $this->assertSame(['project.code', 'project.name'], $spec['found']['builtins']);
        $this->assertSame([], $spec['warnings']);
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

    public function test_it_renders_html_with_missing_placeholders_as_empty_and_escapes_values(): void
    {
        $service = new DeliverableTemplateVersionService();

        $rendered = $service->renderHtml(
            '<div>{{project.name}}|{{fields.remark}}|{{fields.missing}}</div>',
            [
                'project.name' => 'Tower A',
                'fields.remark' => 'Checked & signed',
            ]
        );

        $this->assertSame('<div>Tower A|Checked &amp; signed|</div>', $rendered);
    }

    public function test_it_records_unknown_and_unmapped_placeholder_warnings(): void
    {
        $service = new DeliverableTemplateVersionService();

        $spec = $service->normalizePlaceholdersSpec([
            'schema_version' => '1.0.0',
            'placeholders' => [
                ['key' => 'project.name', 'type' => 'string'],
                ['key' => 'fields.remark', 'type' => 'string'],
            ],
        ], '<div>{{ project.name }} {{ fields.remark }} {{ fields.quantity }} {{ custom.token }}</div>');

        $this->assertSame(['project.name'], $spec['found']['builtins']);
        $this->assertSame(['fields.quantity', 'fields.remark'], $spec['found']['fields']);
        $this->assertSame(['custom.token'], $spec['found']['unknown']);
        $this->assertCount(2, $spec['warnings']);
        $this->assertSame('unknown_placeholder', $spec['warnings'][0]['type']);
        $this->assertSame('custom.token', $spec['warnings'][0]['key']);
        $this->assertSame('unmapped_field', $spec['warnings'][1]['type']);
        $this->assertSame('fields.quantity', $spec['warnings'][1]['key']);
    }

    public function test_it_stringifies_complex_values_for_html_rendering(): void
    {
        $service = new DeliverableTemplateVersionService();

        $this->assertSame('true', $service->stringifyForHtml(true));
        $this->assertSame('12.5', $service->stringifyForHtml(12.5));
        $this->assertSame('{&quot;status&quot;:&quot;ready&quot;}', $service->stringifyForHtml(['status' => 'ready']));
    }
}
