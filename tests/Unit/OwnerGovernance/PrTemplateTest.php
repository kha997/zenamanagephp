<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;

class PrTemplateTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_owner_summary_is_the_first_section(): void
    {
        $content = file_get_contents($this->repoRoot() . '/.github/PULL_REQUEST_TEMPLATE.md');
        $lines = explode("\n", trim($content));

        $this->assertSame('## Owner Summary (read this first — no code required)', $lines[0]);
    }

    public function test_ssot_story_reference_section_still_present_and_unmodified_below(): void
    {
        $content = file_get_contents($this->repoRoot() . '/.github/PULL_REQUEST_TEMPLATE.md');
        $this->assertStringContainsString('## SSOT Story Reference', $content);
        $this->assertStringContainsString('## Invariants Checklist (MUST)', $content);
        $this->assertStringContainsString('## SSOT Backlog Update (REQUIRED)', $content);
    }

    public function test_owner_summary_names_gate_status_and_technical_readiness(): void
    {
        $content = file_get_contents($this->repoRoot() . '/.github/PULL_REQUEST_TEMPLATE.md');
        $this->assertStringContainsString('Owner gate status', $content);
        $this->assertStringContainsString('Technical readiness', $content);
        $this->assertStringContainsString('Owner decision', $content);
    }
}
