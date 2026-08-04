<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class OwnerOperatingDocsTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_all_three_operating_documents_exist(): void
    {
        $root = $this->repoRoot();
        $this->assertFileExists($root . '/docs/owner-governance/OWNER_OPERATING_MODEL.md');
        $this->assertFileExists($root . '/docs/owner-governance/OWNER_DECISION_RULES.md');
        $this->assertFileExists($root . '/docs/owner-governance/OWNER_LANGUAGE_GUIDE.md');
    }

    public function test_operating_model_gate_status_values_match_schema(): void
    {
        $root = $this->repoRoot();
        $schema = Yaml::parseFile($root . '/docs/owner-governance/packet-schema.yml');
        $modelContent = file_get_contents($root . '/docs/owner-governance/OWNER_OPERATING_MODEL.md');

        foreach ($schema['gates'][3]['gate_status_values'] as $value) {
            $this->assertStringContainsString(
                $value,
                $modelContent,
                "OWNER_OPERATING_MODEL.md must mention gate_status value '{$value}' (sourced from packet-schema.yml)."
            );
        }
    }

    public function test_decision_rules_enums_match_schema_exactly(): void
    {
        $root = $this->repoRoot();
        $schema = Yaml::parseFile($root . '/docs/owner-governance/packet-schema.yml');
        $rulesContent = file_get_contents($root . '/docs/owner-governance/OWNER_DECISION_RULES.md');

        foreach ([1, 2, 3] as $gate) {
            foreach ($schema['gates'][$gate]['owner_decision_values'] as $value) {
                $this->assertStringContainsString(
                    $value,
                    $rulesContent,
                    "OWNER_DECISION_RULES.md gate {$gate} enum listing must contain '{$value}'."
                );
            }
        }
    }

    public function test_language_guide_states_growth_rule(): void
    {
        $root = $this->repoRoot();
        $content = file_get_contents($root . '/docs/owner-governance/OWNER_LANGUAGE_GUIDE.md');
        $this->assertStringContainsString('grows only when', $content);
    }
}
