<?php

/**
 * Analysis Script for Zena References
 * 
 * This script analyzes all zena_ references in the codebase
 * and provides a comprehensive report.
 */

class ZenaReferenceAnalyzer
{
    private $references = [];
    private $fileCounts = [];
    private $totalFiles = 0;

    public function analyze()
    {
        echo "🔍 Analyzing zena_ references in codebase...\n\n";
        
        $this->scanDirectory(__DIR__ . '/../app');
        $this->scanDirectory(__DIR__ . '/../resources');
        $this->scanDirectory(__DIR__ . '/../tests');
        $this->scanDirectory(__DIR__ . '/../config');
        $this->scanDirectory(__DIR__ . '/../routes');
        $this->scanDirectory(__DIR__ . '/../database');
        
        $this->generateReport();
    }

    private function scanDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && 
                ($file->getExtension() === 'php' || $file->getExtension() === 'blade.php')) {
                $this->analyzeFile($file->getPathname());
            }
        }
    }

    private function analyzeFile($filePath)
    {
        $this->totalFiles++;
        
        if (!file_exists($filePath)) {
            return;
        }
        
        $content = file_get_contents($filePath);
        $relativePath = str_replace(__DIR__ . '/../', '', $filePath);
        
        // Find all zena_ references
        $patterns = [
            'zena_users',
            'zena_components',
            'zena_task_assignments',
            'zena_documents',
            'zena_notifications',
            'zena_roles',
            'zena_permissions',
            'zena_role_permissions',
            'zena_user_roles',
            'zena_audit_logs',
            'zena_email_tracking',
            'zena_system_settings',
            'zena_work_templates',
            'zena_template_tasks',
            'zena_design_construction',
            'zena_change_requests',
            'zena_change_request_comments',
            'zena_change_request_approvals',
        ];
        
        $foundPatterns = [];
        
        foreach ($patterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                $foundPatterns[] = $pattern;
                
                if (!isset($this->references[$pattern])) {
                    $this->references[$pattern] = [];
                }
                
                $this->references[$pattern][] = $relativePath;
            }
        }
        
        if (!empty($foundPatterns)) {
            $this->fileCounts[$relativePath] = $foundPatterns;
        }
    }

    private function generateReport()
    {
        echo "📊 ANALYSIS REPORT\n";
        echo str_repeat("=", 60) . "\n\n";
        
        echo "📁 Total files scanned: " . $this->totalFiles . "\n";
        echo "📄 Files with zena_ references: " . count($this->fileCounts) . "\n\n";
        
        // Group by type
        $byType = [
            'Models' => [],
            'Controllers' => [],
            'Services' => [],
            'Views' => [],
            'Tests' => [],
            'Config' => [],
            'Routes' => [],
            'Migrations' => [],
            'Seeders' => [],
            'Other' => []
        ];
        
        foreach ($this->fileCounts as $file => $patterns) {
            if (strpos($file, 'app/Models/') === 0) {
                $byType['Models'][$file] = $patterns;
            } elseif (strpos($file, 'app/Http/Controllers/') === 0) {
                $byType['Controllers'][$file] = $patterns;
            } elseif (strpos($file, 'app/Services/') === 0) {
                $byType['Services'][$file] = $patterns;
            } elseif (strpos($file, 'resources/views/') === 0) {
                $byType['Views'][$file] = $patterns;
            } elseif (strpos($file, 'tests/') === 0) {
                $byType['Tests'][$file] = $patterns;
            } elseif (strpos($file, 'config/') === 0) {
                $byType['Config'][$file] = $patterns;
            } elseif (strpos($file, 'routes/') === 0) {
                $byType['Routes'][$file] = $patterns;
            } elseif (strpos($file, 'database/migrations/') === 0) {
                $byType['Migrations'][$file] = $patterns;
            } elseif (strpos($file, 'database/seeders/') === 0) {
                $byType['Seeders'][$file] = $patterns;
            } else {
                $byType['Other'][$file] = $patterns;
            }
        }
        
        // Report by type
        foreach ($byType as $type => $files) {
            if (!empty($files)) {
                echo "📂 $type (" . count($files) . " files):\n";
                foreach ($files as $file => $patterns) {
                    echo "   • $file\n";
                    foreach ($patterns as $pattern) {
                        echo "     - $pattern\n";
                    }
                }
                echo "\n";
            }
        }
        
        // Summary by pattern
        echo "📋 SUMMARY BY PATTERN:\n";
        foreach ($this->references as $pattern => $files) {
            echo "   $pattern: " . count($files) . " files\n";
        }
        
        echo "\n";
        
        // Priority recommendations
        echo "🎯 PRIORITY RECOMMENDATIONS:\n";
        
        $highPriority = [];
        $mediumPriority = [];
        $lowPriority = [];
        
        foreach ($this->references as $pattern => $files) {
            $count = count($files);
            if ($count > 10) {
                $highPriority[] = $pattern;
            } elseif ($count > 5) {
                $mediumPriority[] = $pattern;
            } else {
                $lowPriority[] = $pattern;
            }
        }
        
        if (!empty($highPriority)) {
            echo "   🔴 HIGH PRIORITY (10+ files):\n";
            foreach ($highPriority as $pattern) {
                echo "      • $pattern\n";
            }
            echo "\n";
        }
        
        if (!empty($mediumPriority)) {
            echo "   🟡 MEDIUM PRIORITY (5-10 files):\n";
            foreach ($mediumPriority as $pattern) {
                echo "      • $pattern\n";
            }
            echo "\n";
        }
        
        if (!empty($lowPriority)) {
            echo "   🟢 LOW PRIORITY (<5 files):\n";
            foreach ($lowPriority as $pattern) {
                echo "      • $pattern\n";
            }
            echo "\n";
        }
        
        // Save detailed report
        $this->saveDetailedReport();
    }

    private function saveDetailedReport()
    {
        $reportFile = __DIR__ . '/../storage/logs/zena_references_analysis_' . date('Y-m-d_H-i-s') . '.json';
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_files_scanned' => $this->totalFiles,
            'files_with_references' => count($this->fileCounts),
            'references_by_pattern' => $this->references,
            'files_by_type' => $this->fileCounts,
        ];
        
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        echo "📄 Detailed report saved to: $reportFile\n";
    }
}

// Usage
if (php_sapi_name() === 'cli') {
    $analyzer = new ZenaReferenceAnalyzer();
    $analyzer->analyze();
}
