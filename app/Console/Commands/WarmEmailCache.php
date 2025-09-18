<?php

namespace App\Console\Commands;

use App\Services\EmailTemplateCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WarmEmailCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:warm-cache 
                            {--templates=* : Specific templates to warm up}
                            {--force : Force warm up even if cache is disabled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up email template cache for better performance';

    protected $templateCacheService;

    public function __construct(EmailTemplateCacheService $templateCacheService)
    {
        parent::__construct();
        $this->templateCacheService = $templateCacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting email template cache warm-up...');

        $templates = $this->option('templates');
        $force = $this->option('force');

        // Check if caching is enabled
        if (!$force && !config('mail.template_cache.enabled', true)) {
            $this->warn('Email template caching is disabled. Use --force to warm up anyway.');
            return 0;
        }

        try {
            if (empty($templates)) {
                // Warm up all common templates
                $this->warmUpCommonTemplates();
            } else {
                // Warm up specific templates
                $this->warmUpSpecificTemplates($templates);
            }

            $this->info('Email template cache warm-up completed successfully!');
            
            // Show cache statistics
            $this->showCacheStats();

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to warm up email template cache: ' . $e->getMessage());
            Log::error('Email cache warm-up failed', [
                'error' => $e->getMessage(),
                'templates' => $templates,
            ]);
            return 1;
        }
    }

    /**
     * Warm up common templates
     */
    private function warmUpCommonTemplates(): void
    {
        $this->info('Warming up common email templates...');

        $commonTemplates = [
            'invitation' => [
                'organizationName' => 'Demo Organization',
                'inviterName' => 'Admin',
                'roleDisplayName' => 'User',
                'projectName' => 'Sample Project',
                'expiresAt' => now()->addDays(7)->format('F d, Y \a\t g:i A'),
                'daysUntilExpiry' => 7,
            ],
            'welcome' => [
                'organizationName' => 'Demo Organization',
                'roleDisplayName' => 'User',
                'dashboardUrl' => config('app.url') . '/dashboard',
            ],
        ];

        foreach ($commonTemplates as $template => $data) {
            $this->warmUpTemplate($template, $data);
        }
    }

    /**
     * Warm up specific templates
     */
    private function warmUpSpecificTemplates(array $templates): void
    {
        $this->info('Warming up specific templates: ' . implode(', ', $templates));

        foreach ($templates as $template) {
            $data = $this->getDefaultDataForTemplate($template);
            $this->warmUpTemplate($template, $data);
        }
    }

    /**
     * Warm up individual template
     */
    private function warmUpTemplate(string $template, array $data): void
    {
        try {
            $this->line("  Warming up: {$template}");
            
            // For invitation template, create a mock invitation object
            if ($template === 'invitation') {
                $invitation = new \App\Models\Invitation([
                    'email' => 'test@example.com',
                    'first_name' => $data['inviterName'] ?? 'Test',
                    'last_name' => 'User',
                    'role' => 'user',
                    'organization_id' => 1,
                    'invited_by' => 1,
                    'expires_at' => now()->addDays(7),
                    'token' => 'test-token',
                ]);
                
                $organization = new \App\Models\Organization([
                    'id' => 1,
                    'name' => $data['organizationName'] ?? 'Test Organization',
                    'slug' => 'test-org',
                    'domain' => 'test.com',
                    'description' => 'Test organization',
                    'status' => 'active',
                ]);
                
                $invitation->setRelation('organization', $organization);
                $data['invitation'] = $invitation;
                $data['acceptUrl'] = config('app.url') . '/invitations/accept/test-token';
            }
            
            // For welcome template, create a mock user object
            if ($template === 'welcome') {
                $user = new \App\Models\User([
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'role' => 'user',
                    'organization_id' => 1,
                    'status' => 'active',
                    'joined_at' => now(),
                    'email_verified_at' => now(),
                ]);
                
                $organization = new \App\Models\Organization([
                    'id' => 1,
                    'name' => $data['organizationName'] ?? 'Test Organization',
                    'slug' => 'test-org',
                    'domain' => 'test.com',
                    'description' => 'Test organization',
                    'status' => 'active',
                ]);
                
                $user->setRelation('organization', $organization);
                $data['user'] = $user;
            }
            
            $renderedContent = $this->templateCacheService->renderTemplate($template, $data);
            
            $this->info("    ✓ {$template} cached successfully");
            
            Log::debug('Email template warmed up', [
                'template' => $template,
                'data_keys' => array_keys($data),
            ]);
        } catch (\Exception $e) {
            $this->error("    ✗ Failed to warm up {$template}: " . $e->getMessage());
            Log::warning('Failed to warm up email template', [
                'template' => $template,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get default data for template
     */
    private function getDefaultDataForTemplate(string $template): array
    {
        $defaultData = [
            'invitation' => [
                'organizationName' => 'Demo Organization',
                'inviterName' => 'Admin',
                'roleDisplayName' => 'User',
                'projectName' => 'Sample Project',
                'expiresAt' => now()->addDays(7)->format('F d, Y \a\t g:i A'),
                'daysUntilExpiry' => 7,
            ],
            'welcome' => [
                'organizationName' => 'Demo Organization',
                'roleDisplayName' => 'User',
                'dashboardUrl' => config('app.url') . '/dashboard',
            ],
        ];

        return $defaultData[$template] ?? [];
    }

    /**
     * Show cache statistics
     */
    private function showCacheStats(): void
    {
        $this->newLine();
        $this->info('Cache Statistics:');
        
        $stats = $this->templateCacheService->getCacheStats();
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Cache Enabled', $stats['enabled'] ? 'Yes' : 'No'],
                ['Cache Driver', $stats['driver'] ?? 'N/A'],
                ['TTL', $stats['ttl'] ?? 'N/A'],
                ['Total Cached', $stats['total_cached'] ?? 0],
                ['Templates', count($stats['templates'] ?? [])],
            ]
        );

        if (!empty($stats['templates'])) {
            $this->newLine();
            $this->info('Cached Templates:');
            
            $templateData = [];
            foreach ($stats['templates'] as $template => $info) {
                $templateData[] = [
                    $template,
                    $info['count'] ?? 0,
                    $info['last_cached'] ?? 'N/A',
                ];
            }
            
            $this->table(
                ['Template', 'Count', 'Last Cached'],
                $templateData
            );
        }
    }
}
