#!/usr/bin/env php
<?php

/**
 * Auth Helper Auto-Fix Script
 * 
 * This script automatically fixes common auth() helper usage issues
 * by replacing them with proper Auth facade calls.
 * 
 * Usage: php auth-auto-fix.php [path]
 * Example: php auth-auto-fix.php app/
 */

class AuthAutoFix
{
    private array $fixes = [];
    private array $skipped = [];
    
    public function __construct(private string $path = 'app/')
    {
    }
    
    public function run(): void
    {
        echo "🔧 Auth Auto-Fix - Fixing common auth() usage issues...\n\n";
        
        $this->scanDirectory($this->path);
        $this->displayResults();
        
        echo "\n✅ Auto-fix completed!\n";
        echo "Fixed: " . count($this->fixes) . " files\n";
        echo "Skipped: " . count($this->skipped) . " files\n";
    }
    
    private function scanDirectory(string $path): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->fixFile($file->getPathname());
            }
        }
    }
    
    private function fixFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        $originalContent = $content;
        
        // Skip if file doesn't contain auth() usage
        if (strpos($content, 'auth()') === false) {
            return;
        }
        
        // Common replacements
        $replacements = [
            // Basic auth() calls
            'auth()->check()' => 'Auth::check()',
            'auth()->user()' => 'Auth::user()',
            'auth()->id()' => 'Auth::id()',
            'auth()->login(' => 'Auth::login(',
            'auth()->logout()' => 'Auth::logout()',
            'auth()->guest()' => 'Auth::guest()',
            
            // With spaces
            'auth() -> check()' => 'Auth::check()',
            'auth() -> user()' => 'Auth::user()',
            'auth() -> id()' => 'Auth::id()',
            'auth() -> login(' => 'Auth::login(',
            'auth() -> logout()' => 'Auth::logout()',
            'auth() -> guest()' => 'Auth::guest()',
            
            // Conditional checks
            '!auth()->check()' => '!Auth::check()',
            '!auth() -> check()' => '!Auth::check()',
            
            // Method chaining
            'auth()->user()->' => 'Auth::user()->',
            'auth() -> user() ->' => 'Auth::user()->',
        ];
        
        $fixed = false;
        foreach ($replacements as $search => $replace) {
            if (strpos($content, $search) !== false) {
                $content = str_replace($search, $replace, $content);
                $fixed = true;
            }
        }
        
        // Add Auth facade import if needed
        if ($fixed && strpos($content, 'use Illuminate\Support\Facades\Auth;') === false) {
            $content = $this->addAuthImport($content);
        }
        
        if ($fixed) {
            file_put_contents($filePath, $content);
            $this->fixes[] = $filePath;
        } else {
            $this->skipped[] = $filePath;
        }
    }
    
    private function addAuthImport(string $content): string
    {
        // Find the namespace declaration
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[0];
            
            // Find the use statements section
            $lines = explode("\n", $content);
            $insertIndex = 0;
            
            for ($i = 0; $i < count($lines); $i++) {
                if (strpos($lines[$i], 'namespace') !== false) {
                    $insertIndex = $i + 1;
                    break;
                }
            }
            
            // Insert Auth import
            array_splice($lines, $insertIndex, 0, ['use Illuminate\Support\Facades\Auth;', '']);
            
            return implode("\n", $lines);
        }
        
        return $content;
    }
    
    private function displayResults(): void
    {
        if (!empty($this->fixes)) {
            echo "✅ FIXED FILES:\n";
            echo str_repeat("=", 80) . "\n";
            foreach ($this->fixes as $file) {
                echo "Fixed: {$file}\n";
            }
            echo str_repeat("-", 80) . "\n";
        }
        
        if (!empty($this->skipped)) {
            echo "\n⚠️  SKIPPED FILES:\n";
            echo str_repeat("=", 80) . "\n";
            foreach ($this->skipped as $file) {
                echo "Skipped: {$file}\n";
            }
            echo str_repeat("-", 80) . "\n";
        }
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    $path = $argv[1] ?? 'app/';
    $fix = new AuthAutoFix($path);
    $fix->run();
}
