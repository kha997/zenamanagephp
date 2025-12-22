<?php

namespace Tests\Feature\Routes;

use Tests\TestCase;

class LegacyRedirectsTest extends TestCase
{
    /** @test */
    public function legacy_redirects_have_no_duplicates(): void
    {
        $legacyFile = base_path('routes/legacy.php');
        $this->assertFileExists($legacyFile, 'Legacy routes file should exist');
        
        $content = file_get_contents($legacyFile);
        
        // Parse all permanentRedirect calls
        preg_match_all('/Route::permanentRedirect\([\'"]([^\'"]+)[\'"],\s*[\'"]([^\'"]+)[\'"]\)/', $content, $matches, PREG_SET_ORDER);
        
        $redirects = [];
        $duplicates = [];
        
        foreach ($matches as $match) {
            $from = $match[1];
            $to = $match[2];
            $key = $from . ' -> ' . $to;
            
            if (isset($redirects[$key])) {
                $duplicates[] = $key;
            } else {
                $redirects[$key] = true;
            }
        }
        
        $this->assertEmpty($duplicates, 'Found duplicate legacy redirects: ' . implode(', ', $duplicates));
    }

    /** @test */
    public function legacy_redirects_have_proper_format(): void
    {
        $legacyFile = base_path('routes/legacy.php');
        $content = file_get_contents($legacyFile);
        
        // Check that all redirects use permanentRedirect (301)
        $this->assertStringContainsString('Route::permanentRedirect', $content, 'Legacy redirects should use permanentRedirect (301)');
        
        // Check that there are no temporary redirects
        $this->assertStringNotContainsString('Route::redirect', $content, 'Legacy redirects should not use temporary redirects');
        
        // Check that all redirects have proper comments
        $this->assertStringContainsString('// Legacy redirects (301) - Single source of truth', $content, 'Legacy file should have proper documentation');
    }

    /** @test */
    public function legacy_redirects_cover_essential_paths(): void
    {
        $legacyFile = base_path('routes/legacy.php');
        $content = file_get_contents($legacyFile);
        
        $essentialRedirects = [
            '/dashboard' => '/app/dashboard',
            '/projects' => '/app/projects',
            '/tasks' => '/app/tasks',
            '/clients' => '/app/clients',
            '/quotes' => '/app/quotes',
        ];
        
        foreach ($essentialRedirects as $from => $to) {
            $this->assertStringContainsString(
                "Route::permanentRedirect('{$from}', '{$to}')",
                $content,
                "Missing essential redirect: {$from} -> {$to}"
            );
        }
    }
}
