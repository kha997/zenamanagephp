<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ConfigureSMTP extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smtp:configure 
                            {--provider= : SMTP provider (gmail, sendgrid, mailgun, outlook, custom)}
                            {--host= : SMTP host}
                            {--port= : SMTP port}
                            {--username= : SMTP username}
                            {--password= : SMTP password}
                            {--encryption= : Encryption (tls, ssl, none)}
                            {--from-address= : From email address}
                            {--from-name= : From name}
                            {--interactive : Interactive configuration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure SMTP settings for production';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 SMTP Configuration for Production');
        $this->newLine();

        if ($this->option('interactive')) {
            $this->interactiveConfiguration();
        } else {
            $this->commandLineConfiguration();
        }

        $this->newLine();
        $this->info('✅ SMTP configuration completed!');
        
        // Test configuration
        if ($this->confirm('Would you like to test the SMTP configuration?')) {
            $this->testSMTPConfiguration();
        }
    }

    /**
     * Interactive configuration
     */
    private function interactiveConfiguration(): void
    {
        $this->info('📧 Interactive SMTP Configuration');
        $this->newLine();

        // Provider selection
        $provider = $this->choice(
            'Select SMTP Provider',
            ['gmail', 'sendgrid', 'mailgun', 'outlook', 'custom'],
            'gmail'
        );

        $config = $this->getProviderConfig($provider);

        // Custom configuration
        if ($provider === 'custom') {
            $config['host'] = $this->ask('SMTP Host', 'smtp.example.com');
            $config['port'] = $this->ask('SMTP Port', '587');
            $config['encryption'] = $this->choice('Encryption', ['tls', 'ssl', 'none'], 'tls');
        }

        $config['username'] = $this->ask('SMTP Username/Email') ?: '';
        $config['password'] = $this->secret('SMTP Password/API Key') ?: '';
        $config['from_address'] = $this->ask('From Email Address', $config['username']) ?: '';
        $config['from_name'] = $this->ask('From Name', 'ZenaManage') ?: 'ZenaManage';

        $this->updateEnvironmentFile($config);
    }

    /**
     * Command line configuration
     */
    private function commandLineConfiguration(): void
    {
        $provider = $this->option('provider') ?: 'gmail';
        
        $config = $this->getProviderConfig($provider);

        // Override with command line options
        if ($this->option('host')) $config['host'] = $this->option('host');
        if ($this->option('port')) $config['port'] = $this->option('port');
        if ($this->option('encryption')) $config['encryption'] = $this->option('encryption');
        if ($this->option('username')) $config['username'] = $this->option('username');
        if ($this->option('password')) $config['password'] = $this->option('password');
        if ($this->option('from-address')) $config['from_address'] = $this->option('from-address');
        if ($this->option('from-name')) $config['from_name'] = $this->option('from-name');

        $this->updateEnvironmentFile($config);
    }

    /**
     * Get provider configuration
     */
    private function getProviderConfig(string $provider): array
    {
        $configs = [
            'gmail' => [
                'host' => 'smtp.gmail.com',
                'port' => '587',
                'encryption' => 'tls',
            ],
            'sendgrid' => [
                'host' => 'smtp.sendgrid.net',
                'port' => '587',
                'encryption' => 'tls',
            ],
            'mailgun' => [
                'host' => 'smtp.mailgun.org',
                'port' => '587',
                'encryption' => 'tls',
            ],
            'outlook' => [
                'host' => 'smtp-mail.outlook.com',
                'port' => '587',
                'encryption' => 'tls',
            ],
            'custom' => [
                'host' => '',
                'port' => '587',
                'encryption' => 'tls',
            ],
        ];

        return $configs[$provider] ?? $configs['custom'];
    }

    /**
     * Update environment file
     */
    private function updateEnvironmentFile(array $config): void
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            $this->error('Environment file not found. Please create .env file first.');
            return;
        }

        $envContent = File::get($envPath);
        
        // Update mail configuration
        $envContent = $this->updateEnvValue($envContent, 'MAIL_MAILER', 'smtp');
        $envContent = $this->updateEnvValue($envContent, 'MAIL_HOST', $config['host']);
        $envContent = $this->updateEnvValue($envContent, 'MAIL_PORT', $config['port']);
        $envContent = $this->updateEnvValue($envContent, 'MAIL_USERNAME', $config['username']);
        $envContent = $this->updateEnvValue($envContent, 'MAIL_PASSWORD', $config['password']);
        $envContent = $this->updateEnvValue($envContent, 'MAIL_ENCRYPTION', $config['encryption']);
        $envContent = $this->updateEnvValue($envContent, 'MAIL_FROM_ADDRESS', $config['from_address']);
        $envContent = $this->updateEnvValue($envContent, 'MAIL_FROM_NAME', $config['from_name']);

        File::put($envPath, $envContent);
        
        $this->info('Environment file updated successfully!');
    }

    /**
     * Update environment value
     */
    private function updateEnvValue(string $content, string $key, string $value): string
    {
        $pattern = "/^{$key}=.*$/m";
        $replacement = "{$key}={$value}";
        
        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, $replacement, $content);
        } else {
            return $content . "\n{$replacement}";
        }
    }

    /**
     * Test SMTP configuration
     */
    private function testSMTPConfiguration(): void
    {
        $this->info('🧪 Testing SMTP Configuration...');
        
        $testEmail = $this->ask('Enter test email address');
        
        if (!$testEmail) {
            $this->warn('No test email provided. Skipping test.');
            return;
        }

        try {
            Artisan::call('email:test', [
                'email' => $testEmail,
                '--sync' => true
            ]);

            $output = Artisan::output();
            $this->line($output);

            if (strpos($output, '✅') !== false) {
                $this->info('🎉 SMTP configuration test successful!');
            } else {
                $this->error('❌ SMTP configuration test failed!');
            }
        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
        }
    }
}