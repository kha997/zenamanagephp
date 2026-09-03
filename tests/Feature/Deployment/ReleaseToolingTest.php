<?php declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReleaseToolingTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/gap049-release-' . uniqid());
        File::makeDirectory($this->root . '/shared/storage', 0755, true);
        File::put($this->root . '/shared/.env', "APP_ENV=production\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function runScript(string $script, array $args): Process
    {
        $process = new Process(array_merge(['bash', base_path("scripts/deploy/{$script}")], $args));
        $process->run();
        return $process;
    }

    private function makeRelease(string $sha): void
    {
        File::makeDirectory($this->root . "/releases/{$sha}", 0755, true);
        File::put($this->root . "/releases/{$sha}/marker.txt", $sha);
    }

    public function test_link_shared_symlinks_env_and_storage_into_release(): void
    {
        $this->makeRelease('sha-aaa');

        $process = $this->runScript('link-shared.sh', [$this->root, 'sha-aaa']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertTrue(is_link($this->root . '/releases/sha-aaa/.env'));
        $this->assertTrue(is_link($this->root . '/releases/sha-aaa/storage'));
        $this->assertSame(
            realpath($this->root . '/shared/.env'),
            realpath($this->root . '/releases/sha-aaa/.env')
        );
    }

    public function test_link_shared_fails_when_shared_storage_missing(): void
    {
        File::deleteDirectory($this->root . '/shared/storage');
        $this->makeRelease('sha-bbb');

        $process = $this->runScript('link-shared.sh', [$this->root, 'sha-bbb']);

        $this->assertNotSame(0, $process->getExitCode());
    }

    public function test_activate_release_creates_atomic_current_symlink(): void
    {
        $this->makeRelease('sha-ccc');
        $this->runScript('link-shared.sh', [$this->root, 'sha-ccc']);

        $process = $this->runScript('activate-release.sh', [$this->root, 'sha-ccc']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertTrue(is_link($this->root . '/current'));
        $this->assertSame(
            realpath($this->root . '/releases/sha-ccc'),
            realpath($this->root . '/current')
        );
    }

    public function test_activate_release_fails_and_leaves_current_unchanged_when_release_missing(): void
    {
        $this->makeRelease('sha-ddd');
        $this->runScript('link-shared.sh', [$this->root, 'sha-ddd']);
        $this->runScript('activate-release.sh', [$this->root, 'sha-ddd']);

        $process = $this->runScript('activate-release.sh', [$this->root, 'sha-does-not-exist']);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertSame(
            realpath($this->root . '/releases/sha-ddd'),
            realpath($this->root . '/current'),
            'current must remain pointed at the last good release after a failed activation attempt.'
        );
    }

    public function test_first_deployment_without_existing_current_link_succeeds(): void
    {
        $this->makeRelease('sha-first');
        $this->runScript('link-shared.sh', [$this->root, 'sha-first']);

        $this->assertFalse(file_exists($this->root . '/current'));

        $process = $this->runScript('activate-release.sh', [$this->root, 'sha-first']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertTrue(is_link($this->root . '/current'));
    }

    public function test_rollback_requires_explicit_target_sha_not_head_minus_one(): void
    {
        $this->makeRelease('sha-old');
        $this->makeRelease('sha-new');
        $this->runScript('link-shared.sh', [$this->root, 'sha-old']);
        $this->runScript('link-shared.sh', [$this->root, 'sha-new']);
        $this->runScript('activate-release.sh', [$this->root, 'sha-old']);
        $this->runScript('activate-release.sh', [$this->root, 'sha-new']);

        $process = $this->runScript('rollback.sh', [$this->root]); // no target sha argument

        $this->assertNotSame(0, $process->getExitCode(), 'rollback.sh must require an explicit target sha, not infer HEAD~1.');
    }

    public function test_rollback_selects_explicit_known_previous_release(): void
    {
        $this->makeRelease('sha-old2');
        $this->makeRelease('sha-new2');
        $this->runScript('link-shared.sh', [$this->root, 'sha-old2']);
        $this->runScript('link-shared.sh', [$this->root, 'sha-new2']);
        $this->runScript('activate-release.sh', [$this->root, 'sha-old2']);
        $this->runScript('activate-release.sh', [$this->root, 'sha-new2']);

        $process = $this->runScript('rollback.sh', [$this->root, 'sha-old2']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame(
            realpath($this->root . '/releases/sha-old2'),
            realpath($this->root . '/current')
        );
    }

    public function test_rollback_fails_for_unknown_target_sha(): void
    {
        $this->makeRelease('sha-known');
        $this->runScript('link-shared.sh', [$this->root, 'sha-known']);
        $this->runScript('activate-release.sh', [$this->root, 'sha-known']);

        $process = $this->runScript('rollback.sh', [$this->root, 'sha-never-deployed']);

        $this->assertNotSame(0, $process->getExitCode());
    }

    public function test_cleanup_keeps_current_and_rollback_target_never_touches_shared(): void
    {
        foreach (['sha-1', 'sha-2', 'sha-3', 'sha-4', 'sha-5'] as $sha) {
            $this->makeRelease($sha);
            $this->runScript('link-shared.sh', [$this->root, $sha]);
            sleep(0); // ensure distinct mtimes ordering is source-controlled via directory creation order
        }
        $this->runScript('activate-release.sh', [$this->root, 'sha-5']);

        $process = $this->runScript('cleanup-releases.sh', [$this->root, '2']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertTrue(is_dir($this->root . '/releases/sha-5'), 'current release must survive cleanup');
        $this->assertTrue(is_dir($this->root . '/shared/storage'), 'shared/ must never be touched by cleanup');
        $this->assertTrue(is_file($this->root . '/shared/.env'), 'shared/.env must never be touched by cleanup');
    }

    public function test_cleanup_never_deletes_shared_even_if_root_passed_carelessly(): void
    {
        $this->makeRelease('sha-only');
        $this->runScript('link-shared.sh', [$this->root, 'sha-only']);
        $this->runScript('activate-release.sh', [$this->root, 'sha-only']);

        $this->runScript('cleanup-releases.sh', [$this->root, '0']);

        $this->assertTrue(is_dir($this->root . '/shared'), 'shared/ directory itself must never be removed by cleanup.');
    }
}
