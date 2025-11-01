#!/usr/bin/env php
<?php

/**
 * Guard Lint - Static Analysis Tool for Laravel Auth Usage
 * 
 * This script checks for incorrect usage of auth() helper and suggests
 * proper alternatives using Auth facade.
 * 
 * Usage: php guard-lint.php [path]
 * Example: php guard-lint.php app/
 */

class GuardLint
{
    private array $errors = [];
    private array $warnings = [];
    private array $suggestions = [];
    
    public function __construct(private string $path = 'app/')
    {
    }
    
    public function run(): void
    {
        echo "🔍 Guard Lint - Checking for incorrect auth() usage...\n\n";
        
        $this->scanDirectory($this->path);
        $this->displayResults();
        
        if (!empty($this->errors)) {
            echo "\n❌ Found " . count($this->errors) . " errors that need to be fixed.\n";
            exit(1);
        } elseif (!empty($this->warnings)) {
            echo "\n⚠️  Found " . count($this->warnings) . " warnings to review.\n";
            exit(0);
        } else {
            echo "\n✅ No auth() usage issues found!\n";
            exit(0);
        }
    }
    
    private function scanDirectory(string $path): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->scanFile($file->getPathname());
            }
        }
    }
    
    private function scanFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNumber => $line) {
            $this->checkLine($filePath, $lineNumber + 1, $line);
        }
    }
    
    private function checkLine(string $filePath, int $lineNumber, string $line): void
    {
        // Skip comments and strings
        if (strpos(trim($line), '//') === 0 || strpos(trim($line), '*') === 0) {
            return;
        }
        
        // Check for problematic auth() usage patterns
        $patterns = [
            // Direct auth() calls
            '/auth\(\)(?!\s*->)/' => 'Use Auth::check() or Auth::user() instead',
            '/auth\(\)\s*->/' => 'Use Auth::check() or Auth::user() instead',
            
            // auth() with parameters (should use Auth::guard())
            '/auth\([^)]+\)/' => 'Use Auth::guard(\'guard_name\') instead',
            
            // Common problematic patterns
            '/auth\(\)\s*->\s*check\(\)/' => 'Use Auth::check() instead',
            '/auth\(\)\s*->\s*user\(\)/' => 'Use Auth::user() instead',
            '/auth\(\)\s*->\s*id\(\)/' => 'Use Auth::id() instead',
            '/auth\(\)\s*->\s*login\(/' => 'Use Auth::login() instead',
            '/auth\(\)\s*->\s*logout\(/' => 'Use Auth::logout() instead',
        ];
        
        foreach ($patterns as $pattern => $suggestion) {
            if (preg_match($pattern, $line)) {
                $this->addError($filePath, $lineNumber, $line, $suggestion);
            }
        }
        
        // Check for correct Auth facade usage (good examples)
        if (preg_match('/Auth::/', $line)) {
            $this->addSuggestion($filePath, $lineNumber, $line, 'Good: Using Auth facade');
        }
    }
    
    private function addError(string $filePath, int $lineNumber, string $line, string $suggestion): void
    {
        $this->errors[] = [
            'file' => $filePath,
            'line' => $lineNumber,
            'content' => trim($line),
            'suggestion' => $suggestion
        ];
    }
    
    private function addWarning(string $filePath, int $lineNumber, string $line, string $suggestion): void
    {
        $this->warnings[] = [
            'file' => $filePath,
            'line' => $lineNumber,
            'content' => trim($line),
            'suggestion' => $suggestion
        ];
    }
    
    private function addSuggestion(string $filePath, int $lineNumber, string $line, string $suggestion): void
    {
        $this->suggestions[] = [
            'file' => $filePath,
            'line' => $lineNumber,
            'content' => trim($line),
            'suggestion' => $suggestion
        ];
    }
    
    private function displayResults(): void
    {
        if (!empty($this->errors)) {
            echo "❌ ERRORS:\n";
            echo str_repeat("=", 80) . "\n";
            foreach ($this->errors as $error) {
                echo "File: {$error['file']}:{$error['line']}\n";
                echo "Code: {$error['content']}\n";
                echo "Fix:  {$error['suggestion']}\n";
                echo str_repeat("-", 80) . "\n";
            }
        }
        
        if (!empty($this->warnings)) {
            echo "\n⚠️  WARNINGS:\n";
            echo str_repeat("=", 80) . "\n";
            foreach ($this->warnings as $warning) {
                echo "File: {$warning['file']}:{$warning['line']}\n";
                echo "Code: {$warning['content']}\n";
                echo "Note: {$warning['suggestion']}\n";
                echo str_repeat("-", 80) . "\n";
            }
        }
        
        if (!empty($this->suggestions)) {
            echo "\n✅ GOOD EXAMPLES:\n";
            echo str_repeat("=", 80) . "\n";
            $count = 0;
            foreach ($this->suggestions as $suggestion) {
                if ($count >= 5) break; // Limit to 5 examples
                echo "File: {$suggestion['file']}:{$suggestion['line']}\n";
                echo "Code: {$suggestion['content']}\n";
                echo "Note: {$suggestion['suggestion']}\n";
                echo str_repeat("-", 80) . "\n";
                $count++;
            }
        }
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    $path = $argv[1] ?? 'app/';
    $lint = new GuardLint($path);
    $lint->run();
}
