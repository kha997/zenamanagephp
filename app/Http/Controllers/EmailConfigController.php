<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class EmailConfigController extends Controller
{
    /**
     * Display email configuration page
     */
    public function index(): View
    {
        $providers = $this->getAvailableProviders();
        $currentConfig = $this->getCurrentConfig();
        
        return view('admin.email-config', compact('providers', 'currentConfig'));
    }

    /**
     * Update email configuration
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|in:' . implode(',', array_keys($this->getAvailableProviders())),
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'encryption' => 'required|string|in:tls,ssl,none',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'from_address' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'test_email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update configuration
            $this->updateConfig($request->all());

            // Clear template cache
            $this->clearTemplateCache();

            // Test email if provided
            if ($request->test_email) {
                $testResult = $this->sendTestEmail($request->test_email, $request->all());
                
                return response()->json([
                    'success' => true,
                    'message' => 'Email configuration updated successfully!',
                    'test_result' => $testResult
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Email configuration updated successfully!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test email configuration
     */
    public function test(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'test_email' => 'required|email|max:255',
            'provider' => 'required|string',
            'host' => 'required|string',
            'port' => 'required|integer',
            'encryption' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->sendTestEmail($request->test_email, $request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully!',
                'result' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get email statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'queue_status' => $this->getQueueStatus(),
                'cache_status' => $this->getCacheStatus(),
                'provider_info' => $this->getProviderInfo(),
                'rate_limits' => $this->getRateLimits(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get email statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear email template cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->clearTemplateCache();

            return response()->json([
                'success' => true,
                'message' => 'Email template cache cleared successfully!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available email providers
     */
    private function getAvailableProviders(): array
    {
        return [
            'smtp' => [
                'name' => 'SMTP',
                'description' => 'Standard SMTP server',
                'icon' => 'fas fa-server',
                'color' => 'blue'
            ],
            'gmail' => [
                'name' => 'Gmail',
                'description' => 'Google Gmail SMTP',
                'icon' => 'fab fa-google',
                'color' => 'red'
            ],
            'sendgrid' => [
                'name' => 'SendGrid',
                'description' => 'SendGrid Email API',
                'icon' => 'fas fa-paper-plane',
                'color' => 'green'
            ],
            'mailgun' => [
                'name' => 'Mailgun',
                'description' => 'Mailgun Email Service',
                'icon' => 'fas fa-envelope',
                'color' => 'purple'
            ],
            'outlook' => [
                'name' => 'Outlook',
                'description' => 'Microsoft Outlook SMTP',
                'icon' => 'fab fa-microsoft',
                'color' => 'blue'
            ],
            'ses' => [
                'name' => 'Amazon SES',
                'description' => 'Amazon Simple Email Service',
                'icon' => 'fab fa-aws',
                'color' => 'orange'
            ],
            'postmark' => [
                'name' => 'Postmark',
                'description' => 'Postmark Email Service',
                'icon' => 'fas fa-stamp',
                'color' => 'indigo'
            ],
        ];
    }

    /**
     * Get current email configuration
     */
    private function getCurrentConfig(): array
    {
        return [
            'provider' => config('mail.default', 'smtp'),
            'host' => config('mail.mailers.smtp.host', ''),
            'port' => config('mail.mailers.smtp.port', 587),
            'encryption' => config('mail.mailers.smtp.encryption', 'tls'),
            'username' => config('mail.mailers.smtp.username', ''),
            'from_address' => config('mail.from.address', ''),
            'from_name' => config('mail.from.name', ''),
            'queue_enabled' => config('mail.queue.enabled', true),
            'cache_enabled' => config('mail.template_cache.enabled', true),
        ];
    }

    /**
     * Update email configuration
     */
    private function updateConfig(array $config): void
    {
        // Update mail configuration
        Config::set('mail.default', $config['provider']);
        Config::set('mail.mailers.smtp.host', $config['host']);
        Config::set('mail.mailers.smtp.port', $config['port']);
        Config::set('mail.mailers.smtp.encryption', $config['encryption']);
        Config::set('mail.mailers.smtp.username', $config['username']);
        Config::set('mail.mailers.smtp.password', $config['password']);
        Config::set('mail.from.address', $config['from_address']);
        Config::set('mail.from.name', $config['from_name']);

        // Update provider-specific configuration
        if ($config['provider'] === 'gmail') {
            Config::set('mail.mailers.gmail.host', 'smtp.gmail.com');
            Config::set('mail.mailers.gmail.username', $config['username']);
            Config::set('mail.mailers.gmail.password', $config['password']);
        } elseif ($config['provider'] === 'sendgrid') {
            Config::set('mail.mailers.sendgrid.username', 'apikey');
            Config::set('mail.mailers.sendgrid.password', $config['password']);
        }

        // Cache the configuration
        Cache::put('email_config', $config, 3600);
    }

    /**
     * Send test email
     */
    private function sendTestEmail(string $email, array $config): array
    {
        try {
            // Temporarily update config for testing
            $originalConfig = $this->getCurrentConfig();
            $this->updateConfig($config);

            // Send test email
            Mail::raw('This is a test email from ZenaManage. Your email configuration is working correctly!', function ($message) {
                $message->to($request->input('test_email'))
                        ->subject('ZenaManage Email Configuration Test');
            });

            // Restore original config
            $this->updateConfig($originalConfig);

            return [
                'success' => true,
                'message' => 'Test email sent successfully',
                'sent_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => class_basename($e)
            ];
        }
    }

    /**
     * Get queue status
     */
    private function getQueueStatus(): array
    {
        return [
            'enabled' => config('mail.queue.enabled', true),
            'connection' => config('mail.queue.connection', 'default'),
            'queue_name' => config('mail.queue.queue', 'emails'),
            'retry_after' => config('mail.queue.retry_after', 90),
            'max_tries' => config('mail.queue.max_tries', 3),
        ];
    }

    /**
     * Get cache status
     */
    private function getCacheStatus(): array
    {
        return [
            'enabled' => config('mail.template_cache.enabled', true),
            'ttl' => config('mail.template_cache.ttl', 3600),
            'driver' => config('mail.template_cache.driver', 'file'),
            'cached_templates' => $this->getCachedTemplatesCount(),
        ];
    }

    /**
     * Get provider information
     */
    private function getProviderInfo(): array
    {
        $provider = config('mail.default', 'smtp');
        $providers = $this->getAvailableProviders();
        
        return [
            'current' => $provider,
            'info' => $providers[$provider] ?? $providers['smtp'],
            'host' => config('mail.mailers.smtp.host', ''),
            'port' => config('mail.mailers.smtp.port', 587),
            'encryption' => config('mail.mailers.smtp.encryption', 'tls'),
        ];
    }

    /**
     * Get rate limits
     */
    private function getRateLimits(): array
    {
        return [
            'enabled' => config('mail.rate_limits.enabled', true),
            'max_per_minute' => config('mail.rate_limits.max_per_minute', 60),
            'max_per_hour' => config('mail.rate_limits.max_per_hour', 1000),
            'max_per_day' => config('mail.rate_limits.max_per_day', 10000),
        ];
    }

    /**
     * Get cached templates count
     */
    private function getCachedTemplatesCount(): int
    {
        try {
            $cacheKey = 'email_templates_*';
            $keys = Cache::getRedis()->keys($cacheKey);
            return count($keys);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Clear template cache
     */
    private function clearTemplateCache(): void
    {
        try {
            $cacheKey = 'email_templates_*';
            $keys = Cache::getRedis()->keys($cacheKey);
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        } catch (\Exception $e) {
            // Fallback to clearing all cache
            Cache::flush();
        }
    }
}