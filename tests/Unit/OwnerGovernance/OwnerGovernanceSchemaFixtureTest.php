<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class OwnerGovernanceSchemaFixtureTest extends TestCase
{
    private function schema(): array
    {
        $path = dirname(__DIR__, 3) . '/docs/owner-governance/packet-schema.yml';
        $this->assertFileExists($path, 'packet-schema.yml must exist (Task 1, Step 1).');

        return Yaml::parseFile($path);
    }

    private function frontmatterOf(string $fixtureRelativePath): array
    {
        $path = __DIR__ . '/fixtures/' . $fixtureRelativePath;
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertNotFalse($content);

        preg_match('/^---\n(.*?)\n---\n/s', $content, $matches);
        $this->assertArrayHasKey(1, $matches, "Fixture {$fixtureRelativePath} must start with a --- frontmatter block.");

        return Yaml::parse($matches[1]);
    }

    public function test_schema_defines_all_three_gates(): void
    {
        $schema = $this->schema();
        $this->assertSame([1, 2, 3], array_map('intval', array_keys($schema['gates'])));
    }

    public function test_valid_gate_1_fixture_matches_schema_enum(): void
    {
        $schema = $this->schema();
        $fm = $this->frontmatterOf('valid-gate-1.md');

        $this->assertSame(1, $fm['gate']);
        $this->assertContains($fm['gate_status'], $schema['gates'][1]['gate_status_values']);
        $this->assertContains($fm['owner_decision']['value'], $schema['gates'][1]['owner_decision_values']);
        $this->assertMatchesRegularExpression('/' . $schema['work_id_pattern'] . '/', $fm['work_id']);
    }

    public function test_valid_gate_2_fixture_matches_schema_enum(): void
    {
        $schema = $this->schema();
        $fm = $this->frontmatterOf('valid-gate-2.md');

        $this->assertSame(2, $fm['gate']);
        $this->assertContains($fm['gate_status'], $schema['gates'][2]['gate_status_values']);
        $this->assertContains($fm['owner_decision']['value'], $schema['gates'][2]['owner_decision_values']);
    }

    public function test_valid_gate_3_blocked_fixture_has_no_decision_requested(): void
    {
        $fm = $this->frontmatterOf('valid-gate-3-blocked.md');

        $this->assertSame('blocked_technical', $fm['gate_status']);
        $this->assertSame('none', $fm['owner_decision']['value']);
        $this->assertNull($fm['decision_requested']);
        $this->assertSame('blocked', $fm['technical_readiness']['value']);
    }

    public function test_valid_gate_3_awaiting_fixture_exposes_decision_requested(): void
    {
        $fm = $this->frontmatterOf('valid-gate-3-awaiting.md');

        $this->assertSame('awaiting_owner', $fm['gate_status']);
        $this->assertSame('ready', $fm['technical_readiness']['value']);
        $this->assertNotNull($fm['decision_requested']);
        $this->assertSame('docs/owner-decisions/GAP-031/03-release.md', $fm['supersedes']);
    }

    public function test_valid_gate_3_fixtures_carry_the_evidence_binding_contract(): void
    {
        $blocked = $this->frontmatterOf('valid-gate-3-blocked.md');
        $this->assertArrayHasKey('technical_evidence', $blocked);
        $this->assertArrayHasKey('owner_decision_binding', $blocked);
        $this->assertNull($blocked['owner_decision_binding']['implementation_tree_digest'], 'No decision recorded yet — binding must stay null.');

        $awaiting = $this->frontmatterOf('valid-gate-3-awaiting.md');
        $this->assertSame(64, strlen($awaiting['technical_evidence']['implementation_tree_digest']), 'Expected a sha256 hex digest.');
        $this->assertNull($awaiting['owner_decision_binding']['implementation_tree_digest'], 'owner_decision.value is still none — binding must stay null until a decision is recorded.');
    }
}
