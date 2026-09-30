<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * GAP-060: a workflow that calls a script which does not exist fails forever,
 * and one that selects a PHPUnit group no test carries passes while testing
 * nothing. Both kept the nightly a11y/perf workflow red (or would have made it
 * false-green) for months.
 */
class WorkflowReferencesExistTest extends TestCase
{
    /** @return array<string, string> workflow path => contents */
    private function workflows(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [];
        foreach (glob($root . '/.github/workflows/*.{yml,yaml}', GLOB_BRACE) ?: [] as $path) {
            $files[substr($path, strlen($root) + 1)] = (string) file_get_contents($path);
        }
        $this->assertNotEmpty($files);

        return $files;
    }

    public function test_every_repository_script_a_workflow_runs_exists(): void
    {
        $root = dirname(__DIR__, 2);
        $missing = [];
        foreach ($this->workflows() as $workflow => $source) {
            preg_match_all('#(?<![\w/.-])(?:\./)?((?:\.github/scripts|scripts)/[\w./-]+\.(?:sh|php|py|js|mjs))#', $source, $m);
            foreach (array_unique($m[1]) as $script) {
                if (!is_file($root . '/' . $script)) {
                    $missing[] = $workflow . ' -> ' . $script;
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_every_phpunit_group_a_workflow_selects_is_carried_by_a_test(): void
    {
        $root = dirname(__DIR__, 2);
        $testSources = '';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/tests', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $testSources .= (string) file_get_contents($file->getPathname()) . "\n";
            }
        }

        $empty = [];
        foreach ($this->workflows() as $workflow => $source) {
            preg_match_all('/--group[ =]([\w,-]+)/', $source, $m);
            foreach ($m[1] as $list) {
                foreach (explode(',', $list) as $group) {
                    $quoted = preg_quote($group, '/');
                    $carried = preg_match('/@group\s+' . $quoted . '\b/', $testSources) === 1
                        || preg_match('/#\[Group\(\s*[\'"]' . $quoted . '[\'"]\s*\)\]/', $testSources) === 1;
                    if (!$carried) {
                        $empty[] = $workflow . ' -> --group ' . $group;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($empty)));
    }
}
