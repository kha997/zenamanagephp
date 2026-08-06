<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;

class PacketTemplateTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_all_three_templates_exist(): void
    {
        $root = $this->repoRoot();
        $this->assertFileExists($root . '/docs/owner-governance/templates/gate-1-business-request.md');
        $this->assertFileExists($root . '/docs/owner-governance/templates/gate-2-business-design.md');
        $this->assertFileExists($root . '/docs/owner-governance/templates/gate-3-release-decision.md');
    }

    /** @dataProvider requiredSectionsProvider */
    public function test_templates_contain_required_sections(string $file, array $requiredSections): void
    {
        $content = file_get_contents($this->repoRoot() . '/docs/owner-governance/templates/' . $file);
        foreach ($requiredSections as $section) {
            $this->assertStringContainsString($section, $content, "{$file} must contain section '{$section}'.");
        }
    }

    public static function requiredSectionsProvider(): array
    {
        $common = ['## Decision Needed', 'What the owner is NOT being asked to decide'];

        return [
            'gate 1' => ['gate-1-business-request.md', array_merge($common, ['## Vấn đề vận hành', '## Loại trừ rõ ràng'])],
            'gate 2' => ['gate-2-business-design.md', array_merge($common, ['## Trước / Sau', '## Kịch bản chấp nhận'])],
            'gate 3' => ['gate-3-release-decision.md', ['BLOCKED — OWNER ACTION NOT REQUIRED', 'Gói quyết định phát hành', 'What the owner is NOT being asked to decide']],
        ];
    }

    public function test_gate_3_template_documents_both_blocked_and_awaiting_variants(): void
    {
        $content = file_get_contents($this->repoRoot() . '/docs/owner-governance/templates/gate-3-release-decision.md');
        $this->assertStringContainsString('gate_status: blocked_technical', $content);
        $this->assertStringContainsString('gate_status: awaiting_owner', $content);
    }
}
