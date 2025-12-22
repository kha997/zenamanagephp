<?php

namespace App\Services\Security;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SecurityAuditService
{
    /**
     * Perform comprehensive security audit.
     */
    public function performSecurityAudit(): array
    {
        $auditResults = [
            'timestamp' => now()->toISOString(),
            'overall_score' => 0,
            'checks' => []
        ];

        // Run security checks
        $auditResults['checks']['user_security'] = $this->auditUserSecurity();
        $auditResults['checks']['tenant_isolation'] = $this->auditTenantIsolation();
        $auditResults['checks']['password_security'] = $this->auditPasswordSecurity();
        $auditResults['checks']['session_security'] = $this->auditSessionSecurity();
        $auditResults['checks']['api_security'] = $this->auditApiSecurity();
        $auditResults['checks']['file_upload_security'] = $this->auditFileUploadSecurity();
        $auditResults['checks']['database_security'] = $this->auditDatabaseSecurity();
        $auditResults['checks']['middleware_security'] = $this->auditMiddlewareSecurity();

        // Calculate overall score
        $auditResults['overall_score'] = $this->calculateOverallScore($auditResults['checks']);

        // Log audit results
        Log::channel('security')->info('Security audit completed', $auditResults);

        return $auditResults;
    }

    /**
     * Audit user security.
     */
    protected function auditUserSecurity(): array
    {
        $checks = [];
        $score = 0;

        // Check for users without roles
        $usersWithoutRoles = User::doesntHave('roles')->count();
        $checks['users_without_roles'] = [
            'count' => $usersWithoutRoles,
            'status' => $usersWithoutRoles === 0 ? 'pass' : 'fail',
            'message' => $usersWithoutRoles === 0 ? 'All users have roles assigned' : "{$usersWithoutRoles} users without roles"
        ];
        $score += $usersWithoutRoles === 0 ? 10 : 0;

        // Check for inactive users
        try {
            $inactiveUsers = User::where('last_login_at', '<', now()->subDays(90))->count();
        } catch (\Exception $e) {
            // Column doesn't exist, skip this check
            $inactiveUsers = 0;
        }
        $checks['inactive_users'] = [
            'count' => $inactiveUsers,
            'status' => $inactiveUsers < 10 ? 'pass' : 'warning',
            'message' => $inactiveUsers < 10 ? 'Few inactive users' : "{$inactiveUsers} inactive users"
        ];
        $score += $inactiveUsers < 10 ? 10 : 5;

        // Check for users with weak passwords
        $weakPasswordUsers = User::where('password', 'like', '%123456%')
            ->orWhere('password', 'like', '%password%')
            ->orWhere('password', 'like', '%admin%')
            ->count();
        $checks['weak_passwords'] = [
            'count' => $weakPasswordUsers,
            'status' => $weakPasswordUsers === 0 ? 'pass' : 'fail',
            'message' => $weakPasswordUsers === 0 ? 'No weak passwords detected' : "{$weakPasswordUsers} users with weak passwords"
        ];
        $score += $weakPasswordUsers === 0 ? 15 : 0;

        return [
            'score' => $score,
            'max_score' => 35,
            'checks' => $checks
        ];
    }

    /**
     * Audit tenant isolation.
     */
    protected function auditTenantIsolation(): array
    {
        $checks = [];
        $score = 0;

        // Check for cross-tenant data access
        $crossTenantAccess = DB::table('users')
            ->join('tenants', 'users.tenant_id', '!=', 'tenants.id')
            ->count();
        $checks['cross_tenant_access'] = [
            'count' => $crossTenantAccess,
            'status' => $crossTenantAccess === 0 ? 'pass' : 'fail',
            'message' => $crossTenantAccess === 0 ? 'No cross-tenant access detected' : "{$crossTenantAccess} cross-tenant access violations"
        ];
        $score += $crossTenantAccess === 0 ? 20 : 0;

        // Check for orphaned records
        $orphanedRecords = DB::table('projects')
            ->whereNotIn('tenant_id', DB::table('tenants')->select('id'))
            ->count();
        $checks['orphaned_records'] = [
            'count' => $orphanedRecords,
            'status' => $orphanedRecords === 0 ? 'pass' : 'fail',
            'message' => $orphanedRecords === 0 ? 'No orphaned records' : "{$orphanedRecords} orphaned records"
        ];
        $score += $orphanedRecords === 0 ? 15 : 0;

        return [
            'score' => $score,
            'max_score' => 35,
            'checks' => $checks
        ];
    }

    /**
     * Audit password security.
     */
    protected function auditPasswordSecurity(): array
    {
        $checks = [];
        $score = 0;

        // Check password hashing
        $unhashedPasswords = User::where('password', 'not like', '$2y$%')->count();
        $checks['password_hashing'] = [
            'count' => $unhashedPasswords,
            'status' => $unhashedPasswords === 0 ? 'pass' : 'fail',
            'message' => $unhashedPasswords === 0 ? 'All passwords are hashed' : "{$unhashedPasswords} unhashed passwords"
        ];
        $score += $unhashedPasswords === 0 ? 20 : 0;

        // Check password expiration
        try {
            $expiredPasswords = User::where('password_updated_at', '<', now()->subDays(90))->count();
        } catch (\Exception $e) {
            // Column doesn't exist, skip this check
            $expiredPasswords = 0;
        }
        $checks['password_expiration'] = [
            'count' => $expiredPasswords,
            'status' => $expiredPasswords < 5 ? 'pass' : 'warning',
            'message' => $expiredPasswords < 5 ? 'Few expired passwords' : "{$expiredPasswords} expired passwords"
        ];
        $score += $expiredPasswords < 5 ? 15 : 5;

        return [
            'score' => $score,
            'max_score' => 35,
            'checks' => $checks
        ];
    }

    /**
     * Audit session security.
     */
    protected function auditSessionSecurity(): array
    {
        $checks = [];
        $score = 0;

        // Check session configuration
        $sessionLifetime = config('session.lifetime');
        $checks['session_lifetime'] = [
            'value' => $sessionLifetime,
            'status' => $sessionLifetime <= 120 ? 'pass' : 'warning',
            'message' => $sessionLifetime <= 120 ? 'Session lifetime is secure' : 'Session lifetime is too long'
        ];
        $score += $sessionLifetime <= 120 ? 15 : 5;

        // Check session encryption
        $sessionEncryption = config('session.encrypt');
        $checks['session_encryption'] = [
            'enabled' => $sessionEncryption,
            'status' => $sessionEncryption ? 'pass' : 'fail',
            'message' => $sessionEncryption ? 'Session encryption enabled' : 'Session encryption disabled'
        ];
        $score += $sessionEncryption ? 20 : 0;

        return [
            'score' => $score,
            'max_score' => 35,
            'checks' => $checks
        ];
    }

    /**
     * Audit API security.
     */
    protected function auditApiSecurity(): array
    {
        $checks = [];
        $score = 0;

        // Check API rate limiting
        $rateLimitingEnabled = config('api.rate_limiting.enabled', false);
        $checks['rate_limiting'] = [
            'enabled' => $rateLimitingEnabled,
            'status' => $rateLimitingEnabled ? 'pass' : 'fail',
            'message' => $rateLimitingEnabled ? 'API rate limiting enabled' : 'API rate limiting disabled'
        ];
        $score += $rateLimitingEnabled ? 15 : 0;

        // Check API authentication
        $apiAuthRequired = config('api.auth_required', true);
        $checks['api_authentication'] = [
            'required' => $apiAuthRequired,
            'status' => $apiAuthRequired ? 'pass' : 'fail',
            'message' => $apiAuthRequired ? 'API authentication required' : 'API authentication not required'
        ];
        $score += $apiAuthRequired ? 20 : 0;

        return [
            'score' => $score,
            'max_score' => 35,
            'checks' => $checks
        ];
    }

    /**
     * Audit file upload security.
     */
    protected function auditFileUploadSecurity(): array
    {
        $checks = [];
        $score = 0;

        // Check file upload restrictions
        $maxFileSize = config('filesystems.max_file_size', 10240); // 10MB default
        $checks['file_size_limit'] = [
            'max_size_mb' => $maxFileSize / 1024,
            'status' => $maxFileSize <= 10240 ? 'pass' : 'warning',
            'message' => $maxFileSize <= 10240 ? 'File size limit is secure' : 'File size limit is too high'
        ];
        $score += $maxFileSize <= 10240 ? 15 : 5;

        // Check allowed file types
        $allowedTypes = config('filesystems.allowed_types', []);
        $checks['allowed_file_types'] = [
            'types' => $allowedTypes,
            'status' => count($allowedTypes) > 0 ? 'pass' : 'fail',
            'message' => count($allowedTypes) > 0 ? 'File types restricted' : 'No file type restrictions'
        ];
        $score += count($allowedTypes) > 0 ? 20 : 0;

        return [
            'score' => $score,
            'max_score' => 35,
            'checks' => $checks
        ];
    }

    /**
     * Audit database security.
     */
    protected function auditDatabaseSecurity(): array
    {
        $checks = [];
        $score = 0;

        // Check database encryption
        $dbEncryption = config('database.encryption', false);
        $checks['database_encryption'] = [
            'enabled' => $dbEncryption,
            'status' => $dbEncryption ? 'pass' : 'warning',
            'message' => $dbEncryption ? 'Database encryption enabled' : 'Database encryption disabled'
        ];
        $score += $dbEncryption ? 20 : 5;

        // Check database backups
        $backupEnabled = config('backup.enabled', false);
        $checks['database_backups'] = [
            'enabled' => $backupEnabled,
            'status' => $backupEnabled ? 'pass' : 'warning',
            'message' => $backupEnabled ? 'Database backups enabled' : 'Database backups disabled'
        ];
        $score += $backupEnabled ? 15 : 5;

        return [
            'score' => $score,
            'max_score' => 35,
            'checks' => $checks
        ];
    }

    /**
     * Audit middleware security.
     */
    protected function auditMiddlewareSecurity(): array
    {
        $checks = [];
        $score = 0;

        // Check CSRF protection
        $csrfEnabled = config('session.csrf_protection', true);
        $checks['csrf_protection'] = [
            'enabled' => $csrfEnabled,
            'status' => $csrfEnabled ? 'pass' : 'fail',
            'message' => $csrfEnabled ? 'CSRF protection enabled' : 'CSRF protection disabled'
        ];
        $score += $csrfEnabled ? 15 : 0;

        // Check security headers
        $securityHeaders = config('security.headers', []);
        $checks['security_headers'] = [
            'count' => count($securityHeaders),
            'status' => count($securityHeaders) >= 3 ? 'pass' : 'warning',
            'message' => count($securityHeaders) >= 3 ? 'Security headers configured' : 'Few security headers configured'
        ];
        $score += count($securityHeaders) >= 3 ? 20 : 5;

        return [
            'score' => $score,
            'max_score' => 35,
            'checks' => $checks
        ];
    }

    /**
     * Calculate overall security score.
     */
    protected function calculateOverallScore(array $checks): int
    {
        $totalScore = 0;
        $maxScore = 0;

        foreach ($checks as $check) {
            $totalScore += $check['score'];
            $maxScore += $check['max_score'];
        }

        return $maxScore > 0 ? round(($totalScore / $maxScore) * 100) : 0;
    }

    /**
     * Generate security report.
     */
    public function generateSecurityReport(): array
    {
        $auditResults = $this->performSecurityAudit();
        
        $report = [
            'title' => 'Security Audit Report',
            'generated_at' => now()->toISOString(),
            'overall_score' => $auditResults['overall_score'],
            'status' => $this->getSecurityStatus($auditResults['overall_score']),
            'recommendations' => $this->generateRecommendations($auditResults['checks']),
            'details' => $auditResults
        ];

        // Cache the report
        Cache::put('security_audit_report', $report, 3600); // 1 hour

        return $report;
    }

    /**
     * Get security status based on score.
     */
    protected function getSecurityStatus(int $score): string
    {
        if ($score >= 90) return 'excellent';
        if ($score >= 80) return 'good';
        if ($score >= 70) return 'fair';
        if ($score >= 60) return 'poor';
        return 'critical';
    }

    /**
     * Generate security recommendations.
     */
    protected function generateRecommendations(array $checks): array
    {
        $recommendations = [];

        foreach ($checks as $checkName => $check) {
            foreach ($check['checks'] as $subCheckName => $subCheck) {
                if ($subCheck['status'] === 'fail') {
                    $recommendations[] = [
                        'category' => $checkName,
                        'check' => $subCheckName,
                        'priority' => 'high',
                        'recommendation' => $this->getRecommendation($checkName, $subCheckName, $subCheck)
                    ];
                } elseif ($subCheck['status'] === 'warning') {
                    $recommendations[] = [
                        'category' => $checkName,
                        'check' => $subCheckName,
                        'priority' => 'medium',
                        'recommendation' => $this->getRecommendation($checkName, $subCheckName, $subCheck)
                    ];
                }
            }
        }

        return $recommendations;
    }

    /**
     * Get specific recommendation for a check.
     */
    protected function getRecommendation(string $category, string $check, array $data): string
    {
        $recommendations = [
            'user_security.users_without_roles' => 'Assign roles to all users to ensure proper access control.',
            'user_security.weak_passwords' => 'Enforce strong password policies and require password changes.',
            'tenant_isolation.cross_tenant_access' => 'Review and fix cross-tenant access violations.',
            'password_security.password_hashing' => 'Ensure all passwords are properly hashed using bcrypt.',
            'session_security.session_encryption' => 'Enable session encryption for better security.',
            'api_security.rate_limiting' => 'Enable API rate limiting to prevent abuse.',
            'file_upload_security.allowed_file_types' => 'Restrict file upload types to prevent malicious uploads.',
            'database_security.database_encryption' => 'Enable database encryption for sensitive data.',
            'middleware_security.csrf_protection' => 'Enable CSRF protection to prevent cross-site request forgery.'
        ];

        $key = "{$category}.{$check}";
        return $recommendations[$key] ?? 'Review and improve this security aspect.';
    }
}
