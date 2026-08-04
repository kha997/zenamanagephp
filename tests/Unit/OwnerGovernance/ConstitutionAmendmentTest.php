<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;

class ConstitutionAmendmentTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_constitution_has_owner_gates_section_between_3_and_4(): void
    {
        $content = file_get_contents($this->repoRoot() . '/PROJECT_CONSTITUTION.md');

        $pos3a = strpos($content, '## 3a. Owner Gates');
        $pos4 = strpos($content, '## 4. Operational Gap Detection');

        $this->assertNotFalse($pos3a, '§3a must exist.');
        $this->assertNotFalse($pos4, '§4 must still exist (unmodified heading).');
        $this->assertLessThan($pos4, $pos3a, '§3a must come before §4.');
    }

    public function test_constitution_states_gate_3_does_not_block_preparation(): void
    {
        $content = file_get_contents($this->repoRoot() . '/PROJECT_CONSTITUTION.md');
        $this->assertStringContainsString('không chặn việc triển khai', $content);
    }

    public function test_constitution_governance_map_references_operating_model(): void
    {
        $content = file_get_contents($this->repoRoot() . '/PROJECT_CONSTITUTION.md');
        $this->assertStringContainsString('docs/owner-governance/OWNER_OPERATING_MODEL.md', $content);
    }

    public function test_agent_ssot_rules_has_rule_9(): void
    {
        $content = file_get_contents($this->repoRoot() . '/docs/agent-ssot-rules.md');
        $this->assertStringContainsString('## 9) Owner-facing content is a distinct artifact', $content);
    }

    public function test_agent_ssot_rules_9_names_all_five_stop_report_sections(): void
    {
        $content = file_get_contents($this->repoRoot() . '/docs/agent-ssot-rules.md');
        foreach (['Owner Summary', 'Technical Evidence Appendix', 'Decision Needed', 'Residual Risk'] as $section) {
            $this->assertStringContainsString($section, $content);
        }
    }
}
