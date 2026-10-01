<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\StreamableInputInterface;

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
                            {--password= : Refused (GAP-059): a password on the command line is visible in the process list; use --password-stdin or --interactive}
                            {--password-stdin : Read the SMTP password from STDIN (first line)}
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
    public function handle(): int
    {
        if ($this->option('password') !== null) {
            $this->error('Refusing --password: a password on the command line is visible to other users in the process list (GAP-059). '
                . 'Pipe it with --password-stdin or use --interactive.');

            return self::FAILURE;
        }

        $this->info('🚀 SMTP Configuration for Production');
        $this->newLine();

        $written = $this->option('interactive')
            ? $this->interactiveConfiguration()
            : $this->commandLineConfiguration();

        if (!$written) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✅ SMTP configuration completed!');
        
        // Test configuration
        if ($this->confirm('Would you like to test the SMTP configuration?')) {
            $this->testSMTPConfiguration();
        }

        return self::SUCCESS;
    }

    /**
     * Interactive configuration
     */
    private function interactiveConfiguration(): bool
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

        return $this->updateEnvironmentFile($config);
    }

    /**
     * Command line configuration
     */
    private function commandLineConfiguration(): bool
    {
        $provider = $this->option('provider') ?: 'gmail';
        
        $config = $this->getProviderConfig($provider);

        // Override with command line options
        if ($this->option('host')) $config['host'] = $this->option('host');
        if ($this->option('port')) $config['port'] = $this->option('port');
        if ($this->option('encryption')) $config['encryption'] = $this->option('encryption');
        if ($this->option('username')) $config['username'] = $this->option('username');
        if ($this->option('password-stdin')) $config['password'] = $this->readPasswordFromStdin();
        if ($this->option('from-address')) $config['from_address'] = $this->option('from-address');
        if ($this->option('from-name')) $config['from_name'] = $this->option('from-name');

        return $this->updateEnvironmentFile($config);
    }

    /**
     * Read the password from the first line of STDIN (GAP-059), so it never
     * appears on a process command line.
     */
    private function readPasswordFromStdin(): string
    {
        $stream = $this->input instanceof StreamableInputInterface ? $this->input->getStream() : null;
        $line = fgets($stream ?? STDIN);

        return $line === false ? '' : rtrim($line, "\r\n");
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
    private function updateEnvironmentFile(array $config): bool
    {
        $envPath = app()->environmentFilePath();

        if (!File::exists($envPath)) {
            $this->error('Environment file not found. Please create .env file first.');
            return false;
        }

        $envContent = File::get($envPath);

        // Update mail configuration
        $envContent = $this->updateEnvValue($envContent, 'MAIL_MAILER', 'smtp');
        $envContent = $this->updateEnvValue($envContent, 'MAIL_HOST', (string) ($config['host'] ?? ''));
        $envContent = $this->updateEnvValue($envContent, 'MAIL_PORT', (string) ($config['port'] ?? ''));
        $envContent = $this->updateEnvValue($envContent, 'MAIL_USERNAME', (string) ($config['username'] ?? ''));
        $envContent = $this->updateEnvValue($envContent, 'MAIL_PASSWORD', (string) ($config['password'] ?? ''));
        $envContent = $this->updateEnvValue($envContent, 'MAIL_ENCRYPTION', (string) ($config['encryption'] ?? ''));
        $envContent = $this->updateEnvValue($envContent, 'MAIL_FROM_ADDRESS', (string) ($config['from_address'] ?? ''));
        $envContent = $this->updateEnvValue($envContent, 'MAIL_FROM_NAME', (string) ($config['from_name'] ?? ''));

        // Write to a sibling temp file and rename, keeping the original mode.
        $mode = fileperms($envPath) & 0777;
        $tmp = $envPath . '.tmp-' . bin2hex(random_bytes(4));
        File::put($tmp, $envContent);
        chmod($tmp, $mode);
        rename($tmp, $envPath);

        $this->info('Environment file updated successfully!');

        return true;
    }

    /**
     * Update environment value (GAP-059): always double-quoted with \, " and
     * $ escaped as phpdotenv reads them, and replaced through a callback so
     * the value is never interpreted as regex back-references.
     */
    private function updateEnvValue(string $content, string $key, string $value): string
    {
        $line = $key . '="' . strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$']) . '"';
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        if (preg_match($pattern, $content)) {
            return (string) preg_replace_callback($pattern, static fn (): string => $line, $content, 1);
        }

        return rtrim($content, "\n") . "\n" . $line . "\n";
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