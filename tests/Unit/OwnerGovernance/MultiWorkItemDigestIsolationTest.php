<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/scripts/ssot/owner_governance_lint.php';

final class MultiWorkItemDigestIsolationTest extends TestCase
{
    /** @var list<string> */
    private array $tempRepos = [];

    protected function tearDown(): void
    {
        foreach ($this->tempRepos as $repo) {
            exec('rm -rf ' . escapeshellarg($repo));
        }

        parent::tearDown();
    }

    public function test_single_work_without_gate_3_packet_remains_digest_sensitive(): void
    {
        $repo = $this->makeTempRepo();
        $before = $this->commitFile($repo, 'app/Foo.php', "<?php // v1\n", 'seed');
        $after = $this->commitFile($repo, 'app/Foo.php', "<?php // v2\n", 'runtime change');

        $this->assertNotSame($this->digest($repo, $before, 'WORK-A'), $this->digest($repo, $after, 'WORK-A'));
    }

    public function test_own_active_gate_3_packet_remains_excluded(): void
    {
        $repo = $this->makeTempRepo();
        $this->commitFile($repo, 'app/Foo.php', "<?php // v1\n", 'seed');
        $before = $this->commitFile($repo, 'docs/owner-decisions/WORK-A/03-release.md', "packet a1\n", 'packet a1');
        $after = $this->commitFile($repo, 'docs/owner-decisions/WORK-A/03-release.md', "packet a2\n", 'packet a2');

        $this->assertSame($this->digest($repo, $before, 'WORK-A'), $this->digest($repo, $after, 'WORK-A'));
    }

    public function test_own_active_v2_is_excluded_but_superseded_v1_remains_sensitive(): void
    {
        $repo = $this->makeTempRepo();
        $this->commitFile($repo, 'app/Foo.php', "<?php // v1\n", 'seed');
        $this->commitFile($repo, 'docs/owner-decisions/WORK-A/03-release.md', "superseded a1\n", 'packet a1');
        $withV2 = $this->commitFile($repo, 'docs/owner-decisions/WORK-A/03-release-v2.md', "active a2\n", 'packet a2');
        $activeEdited = $this->commitFile($repo, 'docs/owner-decisions/WORK-A/03-release-v2.md', "active a2 edited\n", 'edit active');
        $supersededEdited = $this->commitFile($repo, 'docs/owner-decisions/WORK-A/03-release.md', "superseded a1 edited\n", 'edit superseded');

        $this->assertSame($this->digest($repo, $withV2, 'WORK-A'), $this->digest($repo, $activeEdited, 'WORK-A'));
        $this->assertNotSame($this->digest($repo, $activeEdited, 'WORK-A'), $this->digest($repo, $supersededEdited, 'WORK-A'));
    }

    public function test_other_work_active_gate_3_packet_does_not_affect_target_digest(): void
    {
        $repo = $this->twoWorkRepo();
        $before = $this->head($repo);
        $after = $this->commitFile($repo, 'docs/owner-decisions/WORK-B/03-release.md', "packet b edited\n", 'edit B packet');

        $this->assertSame($this->digest($repo, $before, 'WORK-A'), $this->digest($repo, $after, 'WORK-A'));
    }

    public function test_cross_work_packet_isolation_is_symmetric(): void
    {
        $repo = $this->twoWorkRepo();
        $before = $this->head($repo);
        $after = $this->commitFile($repo, 'docs/owner-decisions/WORK-A/03-release.md', "packet a edited\n", 'edit A packet');

        $this->assertSame($this->digest($repo, $before, 'WORK-B'), $this->digest($repo, $after, 'WORK-B'));
    }

    public function test_all_recognized_versions_for_other_work_are_excluded(): void
    {
        $repo = $this->twoWorkRepo();
        $this->commitFile($repo, 'docs/owner-decisions/WORK-B/03-release-v2.md', "packet b2\n", 'add B v2');
        $before = $this->commitFile($repo, 'docs/owner-decisions/WORK-B/03-release-v10.md', "packet b10\n", 'add B v10');
        $this->commitFile($repo, 'docs/owner-decisions/WORK-B/03-release.md', "packet b1 edited\n", 'edit B v1');
        $this->commitFile($repo, 'docs/owner-decisions/WORK-B/03-release-v2.md', "packet b2 edited\n", 'edit B v2');
        $after = $this->commitFile($repo, 'docs/owner-decisions/WORK-B/03-release-v10.md', "packet b10 edited\n", 'edit B v10');

        $this->assertSame($this->digest($repo, $before, 'WORK-A'), $this->digest($repo, $after, 'WORK-A'));
    }

    public function test_other_work_gate_1_remains_sensitive(): void
    {
        $repo = $this->twoWorkRepo();
        $before = $this->commitFile($repo, 'docs/owner-decisions/WORK-B/01-request.md', "gate 1 b1\n", 'add B gate 1');
        $after = $this->commitFile($repo, 'docs/owner-decisions/WORK-B/01-request.md', "gate 1 b2\n", 'edit B gate 1');

        $this->assertNotSame($this->digest($repo, $before, 'WORK-A'), $this->digest($repo, $after, 'WORK-A'));
    }

    public function test_other_work_gate_2_remains_sensitive(): void
    {
        $repo = $this->twoWorkRepo();
        $before = $this->commitFile($repo, 'docs/owner-decisions/WORK-B/02-design.md', "gate 2 b1\n", 'add B gate 2');
        $after = $this->commitFile($repo, 'docs/owner-decisions/WORK-B/02-design.md', "gate 2 b2\n", 'edit B gate 2');

        $this->assertNotSame($this->digest($repo, $before, 'WORK-A'), $this->digest($repo, $after, 'WORK-A'));
    }

    public function test_spec_and_plan_remain_sensitive(): void
    {
        $repo = $this->twoWorkRepo();
        $beforeSpec = $this->commitFile($repo, 'docs/superpowers/specs/work-b.md', "spec b1\n", 'add spec');
        $afterSpec = $this->commitFile($repo, 'docs/superpowers/specs/work-b.md', "spec b2\n", 'edit spec');
        $beforePlan = $this->commitFile($repo, 'docs/superpowers/plans/work-b.md', "plan b1\n", 'add plan');
        $afterPlan = $this->commitFile($repo, 'docs/superpowers/plans/work-b.md', "plan b2\n", 'edit plan');

        $this->assertNotSame($this->digest($repo, $beforeSpec, 'WORK-A'), $this->digest($repo, $afterSpec, 'WORK-A'));
        $this->assertNotSame($this->digest($repo, $beforePlan, 'WORK-A'), $this->digest($repo, $afterPlan, 'WORK-A'));
    }

    public function test_governance_script_schema_and_ci_remain_sensitive(): void
    {
        $repo = $this->twoWorkRepo();
        $beforeScript = $this->commitFile($repo, 'scripts/ssot/owner_governance_lint.php', "<?php // v1\n", 'script v1');
        $afterScript = $this->commitFile($repo, 'scripts/ssot/owner_governance_lint.php', "<?php // v2\n", 'script v2');
        $beforeSchema = $this->commitFile($repo, 'docs/owner-governance/packet-schema.yml', "version: 1\n", 'schema v1');
        $afterSchema = $this->commitFile($repo, 'docs/owner-governance/packet-schema.yml', "version: 2\n", 'schema v2');
        $beforeCi = $this->commitFile($repo, '.github/workflows/owner-governance-lint.yml', "name: v1\n", 'ci v1');
        $afterCi = $this->commitFile($repo, '.github/workflows/owner-governance-lint.yml', "name: v2\n", 'ci v2');

        $this->assertNotSame($this->digest($repo, $beforeScript, 'WORK-A'), $this->digest($repo, $afterScript, 'WORK-A'));
        $this->assertNotSame($this->digest($repo, $beforeSchema, 'WORK-A'), $this->digest($repo, $afterSchema, 'WORK-A'));
        $this->assertNotSame($this->digest($repo, $beforeCi, 'WORK-A'), $this->digest($repo, $afterCi, 'WORK-A'));
    }

    public function test_runtime_change_invalidates_both_work_digests(): void
    {
        $repo = $this->twoWorkRepo();
        $before = $this->head($repo);
        $after = $this->commitFile($repo, 'app/Foo.php', "<?php // v2\n", 'runtime change');

        $this->assertNotSame($this->digest($repo, $before, 'WORK-A'), $this->digest($repo, $after, 'WORK-A'));
        $this->assertNotSame($this->digest($repo, $before, 'WORK-B'), $this->digest($repo, $after, 'WORK-B'));
    }

    private function twoWorkRepo(): string
    {
        $repo = $this->makeTempRepo();
        $this->commitFile($repo, 'app/Foo.php', "<?php // v1\n", 'seed');
        $this->commitFile($repo, 'docs/owner-decisions/WORK-A/03-release.md', "packet a\n", 'packet A');
        $this->commitFile($repo, 'docs/owner-decisions/WORK-B/03-release.md', "packet b\n", 'packet B');

        return $repo;
    }

    private function makeTempRepo(): string
    {
        $repo = sys_get_temp_dir() . '/owner-gov-multi-work-' . bin2hex(random_bytes(6));
        mkdir($repo, 0777, true);
        exec('git -C ' . escapeshellarg($repo) . ' init -q');
        exec('git -C ' . escapeshellarg($repo) . ' config user.email test@example.com');
        exec('git -C ' . escapeshellarg($repo) . ' config user.name Test');
        $this->tempRepos[] = $repo;

        return $repo;
    }

    private function commitFile(string $repo, string $path, string $content, string $message): string
    {
        $fullPath = $repo . '/' . $path;
        @mkdir(dirname($fullPath), 0777, true);
        file_put_contents($fullPath, $content);
        exec('git -C ' . escapeshellarg($repo) . ' add ' . escapeshellarg($path));
        exec('git -C ' . escapeshellarg($repo) . ' commit -q -m ' . escapeshellarg($message));

        return $this->head($repo);
    }

    private function head(string $repo): string
    {
        return trim((string) shell_exec('git -C ' . escapeshellarg($repo) . ' rev-parse HEAD'));
    }

    private function digest(string $repo, string $sha, string $workId): string
    {
        return \owner_governance_compute_implementation_tree_digest($sha, $workId, $repo);
    }
}
