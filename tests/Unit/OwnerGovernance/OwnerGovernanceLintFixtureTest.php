<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

require_once dirname(__DIR__, 3) . '/scripts/ssot/owner_governance_lint.php';

class OwnerGovernanceLintFixtureTest extends TestCase
{
    private array $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schema = Yaml::parseFile(dirname(__DIR__, 3) . '/docs/owner-governance/packet-schema.yml');
    }

    private function contentOf(string $fixture): string
    {
        return file_get_contents(__DIR__ . '/fixtures/' . $fixture);
    }

    /** @dataProvider validFixtures */
    public function test_valid_fixtures_produce_zero_violations(string $fixture): void
    {
        $violations = \owner_governance_validate_packet($fixture, $this->contentOf($fixture), $this->schema);
        $this->assertCount(0, $violations, "{$fixture} should be valid but got: " . json_encode(array_map(fn ($v) => $v->message, $violations)));
    }

    public static function validFixtures(): array
    {
        return [
            ['valid-gate-1.md'],
            ['valid-gate-2.md'],
            ['valid-gate-3-blocked.md'],
            ['valid-gate-3-awaiting.md'],
        ];
    }

    /** @dataProvider invalidFixtures */
    public function test_invalid_fixtures_produce_at_least_one_violation_of_the_expected_rule(string $fixture, string $expectedRule): void
    {
        $violations = \owner_governance_validate_packet($fixture, $this->contentOf($fixture), $this->schema);
        $rules = array_map(fn ($v) => $v->rule, $violations);

        $this->assertNotEmpty($violations, "{$fixture} should produce at least one violation.");
        $this->assertContains($expectedRule, $rules, "{$fixture} should trigger rule '{$expectedRule}', got: " . implode(', ', $rules));
    }

    public static function invalidFixtures(): array
    {
        return [
            ['invalid-missing-frontmatter.md', 'frontmatter'],
            ['invalid-bad-enum.md', 'gate-status-enum'],
            ['invalid-status-decision-contradiction.md', 'status-decision-contradiction'],
            ['invalid-blocked-requests-decision.md', 'decision-requested-leaked'],
            ['invalid-todo-placeholder.md', 'placeholder-token'],
            ['invalid-missing-provenance.md', 'dishonest-provenance'],
            ['invalid-not-ready-but-awaiting.md', 'not-ready-but-decision-eligible'],
            ['invalid-stale-decision-digest-mismatch.md', 'stale-decision-digest-mismatch'],
        ];
    }

    public function test_evidence_digest_is_deterministic_regardless_of_check_input_order(): void
    {
        $checksInOrderA = [
            ['name' => 'Unit Tests', 'conclusion' => 'success'],
            ['name' => 'Feature Tests', 'conclusion' => 'success'],
        ];
        $checksInOrderB = [
            ['name' => 'Feature Tests', 'conclusion' => 'success'],
            ['name' => 'Unit Tests', 'conclusion' => 'success'],
        ];

        $digestA = \owner_governance_compute_evidence_digest('abc123', $checksInOrderA);
        $digestB = \owner_governance_compute_evidence_digest('abc123', $checksInOrderB);

        $this->assertSame($digestA, $digestB, 'Digest must not depend on input array order.');
        $this->assertSame(64, strlen($digestA), 'Expected a sha256 hex digest (64 chars).');
    }

    public function test_evidence_digest_changes_when_a_check_conclusion_changes(): void
    {
        $before = \owner_governance_compute_evidence_digest('abc123', [['name' => 'Unit Tests', 'conclusion' => 'success']]);
        $after = \owner_governance_compute_evidence_digest('abc123', [['name' => 'Unit Tests', 'conclusion' => 'failure']]);

        $this->assertNotSame($before, $after);
    }
}
