<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Security Scan Command
 * 
 * Automated security scanning for the application
 */
class SecurityScanCommand extends Command
{
    protected $signature = 'security:scan {--fix : Fix security issues automatically} {--report : Generate detailed report}';
    protected $description = 'Perform comprehensive security scan of the application';

    private array $issues = [];
    private array $fixes = [];

    public function handle()
    {
        $this->info('🔒 Starting Security Scan...');
        
        $this->scanConfiguration();
        $this->scanDatabase();
        $this->scanFiles();
        $this->scanDependencies();
        $this->scanPermissions();
        $this->scanSecrets();
        $this->scanVulnerabilities();
        
        $this->displayResults();
        
        if ($this->option('fix')) {
            $this->fixIssues();
        }
        
        if ($this->option('report')) {
            $this->generateReport();
        }
        
        return 0;
    }

    /**
     * Scan configuration files for security issues
     */
    private function scanConfiguration(): void
    {
        $this->info('📋 Scanning configuration...');
        
        // Check .env file
        $envFile = base_path('.env');
        if (File::exists($envFile)) {
            $envContent = File::get($envFile);
            
            // Check for weak secrets
            if (str_contains($envContent, 'APP_KEY=')) {
                $appKey = env('APP_KEY');
                if (strlen($appKey) < 32) {
                    $this->addIssue('WEAK_APP_KEY', 'Application key is too short', 'CRITICAL');
                }
            }
            
            // Check for debug mode in production
            if (env('APP_DEBUG') === true && env('APP_ENV') === 'production') {
                $this->addIssue('DEBUG_IN_PRODUCTION', 'Debug mode enabled in production', 'CRITICAL');
            }
            
            // Check for weak database passwords
            $dbPassword = env('DB_PASSWORD');
            if ($dbPassword && strlen($dbPassword) < 8) {
                $this->addIssue('WEAK_DB_PASSWORD', 'Database password is too weak', 'HIGH');
            }
            
            // Check for exposed secrets
            $exposedSecrets = [
                'JWT_SECRET' => 'JWT secret exposed',
                'MAIL_PASSWORD' => 'Mail password exposed',
                'REDIS_PASSWORD' => 'Redis password exposed',
            ];
            
            foreach ($exposedSecrets as $secret => $message) {
                if (env($secret) && strlen(env($secret)) < 16) {
                    $this->addIssue('EXPOSED_SECRET', $message, 'HIGH');
                }
            }
        }
        
        // Check config files
        $configFiles = [
            'app.php' => 'Application configuration',
            'auth.php' => 'Authentication configuration',
            'database.php' => 'Database configuration',
            'mail.php' => 'Mail configuration',
        ];
        
        foreach ($configFiles as $file => $description) {
            $configPath = config_path($file);
            if (File::exists($configPath)) {
                $content = File::get($configPath);
                
                // Check for hardcoded secrets
                if (preg_match('/["\']([a-zA-Z0-9]{20,})["\']/', $content, $matches)) {
                    $this->addIssue('HARDCODED_SECRET', "Potential hardcoded secret in {$file}", 'MEDIUM');
                }
            }
        }
    }

    /**
     * Scan database for security issues
     */
    private function scanDatabase(): void
    {
        $this->info('🗄️ Scanning database...');
        
        try {
            // Check for users with weak passwords
            $users = DB::table('users')->get();
            foreach ($users as $user) {
                if (Hash::needsRehash($user->password)) {
                    $this->addIssue('WEAK_PASSWORD_HASH', "User {$user->email} has weak password hash", 'HIGH');
                }
            }
            
            // Check for users without email verification
            $unverifiedUsers = DB::table('users')
                ->where('email_verified', false)
                ->where('created_at', '<', now()->subDays(7))
                ->count();
            
            if ($unverifiedUsers > 0) {
                $this->addIssue('UNVERIFIED_USERS', "{$unverifiedUsers} users with unverified emails", 'MEDIUM');
            }
            
            // Check for inactive users
            $inactiveUsers = DB::table('users')
                ->where('is_active', false)
                ->where('updated_at', '<', now()->subDays(30))
                ->count();
            
            if ($inactiveUsers > 0) {
                $this->addIssue('INACTIVE_USERS', "{$inactiveUsers} inactive users", 'LOW');
            }
            
            // Check for users without MFA
            $usersWithoutMFA = DB::table('users')
                ->where('mfa_enabled', false)
                ->where('role', 'admin')
                ->count();
            
            if ($usersWithoutMFA > 0) {
                $this->addIssue('ADMIN_WITHOUT_MFA', "{$usersWithoutMFA} admin users without MFA", 'HIGH');
            }
            
        } catch (\Exception $e) {
            $this->addIssue('DATABASE_ERROR', "Database scan failed: {$e->getMessage()}", 'CRITICAL');
        }
    }

    /**
     * Scan files for security issues
     */
    private function scanFiles(): void
    {
        $this->info('📁 Scanning files...');
        
        // Check for sensitive files
        $sensitiveFiles = [
            '.env',
            '.env.production',
            'composer.lock',
            'package-lock.json',
            'yarn.lock',
            'config/database.php',
            'config/mail.php',
        ];
        
        foreach ($sensitiveFiles as $file) {
            $filePath = base_path($file);
            if (File::exists($filePath)) {
                $permissions = fileperms($filePath);
                if ($permissions & 0x0004) { // World readable
                    $this->addIssue('WORLD_READABLE_FILE', "File {$file} is world readable", 'HIGH');
                }
            }
        }
        
        // Check for backup files
        $backupPatterns = [
            '*.bak',
            '*.backup',
            '*.old',
            '*.tmp',
            '*.swp',
            '*.~',
        ];
        
        foreach ($backupPatterns as $pattern) {
            $files = File::glob(base_path($pattern));
            foreach ($files as $file) {
                $this->addIssue('BACKUP_FILE', "Backup file found: {$file}", 'MEDIUM');
            }
        }
        
        // Check for debug files
        $debugFiles = [
            'debug.log',
            'error.log',
            'laravel.log',
            'php_errors.log',
        ];
        
        foreach ($debugFiles as $file) {
            $filePath = storage_path("logs/{$file}");
            if (File::exists($filePath)) {
                $size = File::size($filePath);
                if ($size > 100 * 1024 * 1024) { // 100MB
                    $this->addIssue('LARGE_LOG_FILE', "Large log file: {$file} ({$size} bytes)", 'MEDIUM');
                }
            }
        }
    }

    /**
     * Scan dependencies for vulnerabilities
     */
    private function scanDependencies(): void
    {
        $this->info('📦 Scanning dependencies...');
        
        // Check composer.lock for known vulnerabilities
        $composerLockPath = base_path('composer.lock');
        if (File::exists($composerLockPath)) {
            $composerLock = json_decode(File::get($composerLockPath), true);
            
            if (isset($composerLock['packages'])) {
                foreach ($composerLock['packages'] as $package) {
                    $name = $package['name'];
                    $version = $package['version'];
                    
                    // Check for known vulnerable packages
                    $vulnerablePackages = [
                        'laravel/framework' => '5.8.0',
                        'symfony/http-foundation' => '4.4.0',
                        'guzzlehttp/guzzle' => '6.5.0',
                    ];
                    
                    if (isset($vulnerablePackages[$name])) {
                        $minVersion = $vulnerablePackages[$name];
                        if (version_compare($version, $minVersion, '<')) {
                            $this->addIssue('VULNERABLE_PACKAGE', "Package {$name} version {$version} may have vulnerabilities", 'HIGH');
                        }
                    }
                }
            }
        }
        
        // Check package.json for vulnerabilities
        $packageJsonPath = base_path('package.json');
        if (File::exists($packageJsonPath)) {
            $packageJson = json_decode(File::get($packageJsonPath), true);
            
            if (isset($packageJson['dependencies'])) {
                foreach ($packageJson['dependencies'] as $package => $version) {
                    // Check for known vulnerable packages
                    $vulnerablePackages = [
                        'lodash' => '4.17.0',
                        'jquery' => '3.5.0',
                        'axios' => '0.21.0',
                    ];
                    
                    if (isset($vulnerablePackages[$package])) {
                        $minVersion = $vulnerablePackages[$package];
                        if (version_compare($version, $minVersion, '<')) {
                            $this->addIssue('VULNERABLE_NPM_PACKAGE', "NPM package {$package} version {$version} may have vulnerabilities", 'HIGH');
                        }
                    }
                }
            }
        }
    }

    /**
     * Scan file permissions
     */
    private function scanPermissions(): void
    {
        $this->info('🔐 Scanning permissions...');
        
        $directories = [
            'storage' => 0755,
            'bootstrap/cache' => 0755,
            'public' => 0755,
        ];
        
        foreach ($directories as $dir => $expectedPermissions) {
            $dirPath = base_path($dir);
            if (is_dir($dirPath)) {
                $permissions = fileperms($dirPath) & 0777;
                if ($permissions !== $expectedPermissions) {
                    $this->addIssue('INCORRECT_PERMISSIONS', "Directory {$dir} has incorrect permissions", 'MEDIUM');
                }
            }
        }
        
        // Check for world-writable files
        $files = File::allFiles(base_path());
        foreach ($files as $file) {
            $permissions = fileperms($file->getPathname()) & 0777;
            if ($permissions & 0x0002) { // World writable
                $this->addIssue('WORLD_WRITABLE_FILE', "File {$file->getRelativePathname()} is world writable", 'HIGH');
            }
        }
    }

    /**
     * Scan for exposed secrets
     */
    private function scanSecrets(): void
    {
        $this->info('🔍 Scanning for secrets...');
        
        $secretPatterns = [
            '/password\s*=\s*["\']([^"\']+)["\']/' => 'Password',
            '/secret\s*=\s*["\']([^"\']+)["\']/' => 'Secret',
            '/key\s*=\s*["\']([^"\']+)["\']/' => 'Key',
            '/token\s*=\s*["\']([^"\']+)["\']/' => 'Token',
            '/api[_-]?key\s*=\s*["\']([^"\']+)["\']/' => 'API Key',
        ];
        
        $filesToScan = [
            base_path('.env'),
            base_path('config'),
            base_path('app'),
        ];
        
        foreach ($filesToScan as $path) {
            if (File::exists($path)) {
                $files = File::allFiles($path);
                foreach ($files as $file) {
                    $content = File::get($file->getPathname());
                    foreach ($secretPatterns as $pattern => $type) {
                        if (preg_match($pattern, $content, $matches)) {
                            $this->addIssue('EXPOSED_SECRET', "Potential {$type} exposed in {$file->getRelativePathname()}", 'HIGH');
                        }
                    }
                }
            }
        }
    }

    /**
     * Scan for known vulnerabilities
     */
    private function scanVulnerabilities(): void
    {
        $this->info('🛡️ Scanning for vulnerabilities...');
        
        // Check for common Laravel vulnerabilities
        $vulnerabilities = [
            'Laravel Debug Mode' => env('APP_DEBUG') === true,
            'Weak Session Configuration' => config('session.secure') === false,
            'Insecure Cookies' => config('session.http_only') === false,
            'Missing CSRF Protection' => !config('session.csrf_protection'),
            'Weak Password Hashing' => config('hashing.driver') !== 'bcrypt',
        ];
        
        foreach ($vulnerabilities as $vulnerability => $isVulnerable) {
            if ($isVulnerable) {
                $this->addIssue('VULNERABILITY', $vulnerability, 'HIGH');
            }
        }
        
        // Check for missing security headers
        $securityHeaders = [
            'X-Content-Type-Options',
            'X-Frame-Options',
            'X-XSS-Protection',
            'Strict-Transport-Security',
            'Content-Security-Policy',
        ];
        
        foreach ($securityHeaders as $header) {
            if (!config("security.headers.{$header}")) {
                $this->addIssue('MISSING_SECURITY_HEADER', "Missing security header: {$header}", 'MEDIUM');
            }
        }
    }

    /**
     * Add security issue
     */
    private function addIssue(string $type, string $message, string $severity): void
    {
        $this->issues[] = [
            'type' => $type,
            'message' => $message,
            'severity' => $severity,
            'timestamp' => now(),
        ];
    }

    /**
     * Display scan results
     */
    private function displayResults(): void
    {
        $this->info("\n📊 Security Scan Results:");
        $this->info("========================\n");
        
        $severityCounts = [
            'CRITICAL' => 0,
            'HIGH' => 0,
            'MEDIUM' => 0,
            'LOW' => 0,
        ];
        
        foreach ($this->issues as $issue) {
            $severityCounts[$issue['severity']]++;
        }
        
        $this->table(
            ['Severity', 'Count'],
            [
                ['CRITICAL', $severityCounts['CRITICAL']],
                ['HIGH', $severityCounts['HIGH']],
                ['MEDIUM', $severityCounts['MEDIUM']],
                ['LOW', $severityCounts['LOW']],
            ]
        );
        
        if (!empty($this->issues)) {
            $this->info("\n🚨 Issues Found:");
            foreach ($this->issues as $issue) {
                $severityColor = match($issue['severity']) {
                    'CRITICAL' => 'red',
                    'HIGH' => 'yellow',
                    'MEDIUM' => 'blue',
                    'LOW' => 'green',
                    default => 'white'
                };
                
                $this->line("<fg={$severityColor}>[{$issue['severity']}]</> {$issue['message']}");
            }
        } else {
            $this->info("\n✅ No security issues found!");
        }
    }

    /**
     * Fix security issues
     */
    private function fixIssues(): void
    {
        $this->info("\n🔧 Fixing security issues...");
        
        foreach ($this->issues as $issue) {
            switch ($issue['type']) {
                case 'DEBUG_IN_PRODUCTION':
                    $this->fixDebugInProduction();
                    break;
                case 'WEAK_APP_KEY':
                    $this->fixWeakAppKey();
                    break;
                case 'WORLD_READABLE_FILE':
                    $this->fixWorldReadableFile($issue['message']);
                    break;
                case 'BACKUP_FILE':
                    $this->fixBackupFile($issue['message']);
                    break;
            }
        }
        
        $this->info("✅ Security fixes applied!");
    }

    /**
     * Generate detailed security report
     */
    private function generateReport(): void
    {
        $this->info("\n📄 Generating security report...");
        
        $report = [
            'scan_date' => now()->toISOString(),
            'total_issues' => count($this->issues),
            'issues_by_severity' => [
                'CRITICAL' => 0,
                'HIGH' => 0,
                'MEDIUM' => 0,
                'LOW' => 0,
            ],
            'issues' => $this->issues,
            'recommendations' => $this->getRecommendations(),
        ];
        
        foreach ($this->issues as $issue) {
            $report['issues_by_severity'][$issue['severity']]++;
        }
        
        $reportPath = storage_path('app/security-report-' . now()->format('Y-m-d-H-i-s') . '.json');
        File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        
        $this->info("📄 Security report generated: {$reportPath}");
    }

    /**
     * Get security recommendations
     */
    private function getRecommendations(): array
    {
        return [
            'Enable HTTPS in production',
            'Implement rate limiting',
            'Use strong passwords',
            'Enable MFA for admin users',
            'Regular security updates',
            'Monitor security logs',
            'Implement WAF',
            'Regular security audits',
        ];
    }

    /**
     * Fix debug in production
     */
    private function fixDebugInProduction(): void
    {
        $envFile = base_path('.env');
        if (File::exists($envFile)) {
            $content = File::get($envFile);
            $content = preg_replace('/APP_DEBUG=true/', 'APP_DEBUG=false', $content);
            File::put($envFile, $content);
            $this->fixes[] = 'Disabled debug mode in production';
        }
    }

    /**
     * Fix weak app key
     */
    private function fixWeakAppKey(): void
    {
        $this->call('key:generate');
        $this->fixes[] = 'Generated new application key';
    }

    /**
     * Fix world readable file
     */
    private function fixWorldReadableFile(string $message): void
    {
        preg_match('/File (.+) is world readable/', $message, $matches);
        if (isset($matches[1])) {
            $filePath = base_path($matches[1]);
            if (File::exists($filePath)) {
                chmod($filePath, 0600);
                $this->fixes[] = "Fixed permissions for {$matches[1]}";
            }
        }
    }

    /**
     * Fix backup file
     */
    private function fixBackupFile(string $message): void
    {
        preg_match('/Backup file found: (.+)/', $message, $matches);
        if (isset($matches[1])) {
            if (File::exists($matches[1])) {
                File::delete($matches[1]);
                $this->fixes[] = "Deleted backup file: {$matches[1]}";
            }
        }
    }
}
