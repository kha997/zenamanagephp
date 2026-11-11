<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Tenant;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GAP-124 (Gate 2 S-1) — a company's Slack channel for business notifications. The incoming-webhook URL lives in
 * tenants.settings['notifications']['slack'], encrypted; only https://hooks.slack.com/services/… is accepted, so the app
 * never posts to an arbitrary or internal address. Posting never throws: a failure is logged and reported as false.
 */
class SlackChannelService
{
    private const HOST = 'hooks.slack.com';

    public function isValidUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === self::HOST
            && !isset($parts['port'], $parts['user'], $parts['pass'])
            && str_starts_with((string) ($parts['path'] ?? ''), '/services/');
    }

    /**
     * The URL when Slack is set up and switched on for the company, else null.
     */
    public function webhookUrl(string $tenantId): ?string
    {
        $url = $this->storedUrl($tenantId);

        return $url !== null && (bool) ($this->settings($tenantId)['enabled'] ?? false) ? $url : null;
    }

    /**
     * The saved URL (on or off), decrypted, or null when none is saved or it cannot be read.
     */
    public function storedUrl(string $tenantId): ?string
    {
        $stored = $this->settings($tenantId)['webhook'] ?? null;
        if (!is_string($stored)) {
            return null;
        }

        try {
            $url = Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null;
        }

        return $this->isValidUrl($url) ? $url : null;
    }

    /**
     * @return array{configured: bool, enabled: bool, masked: ?string}
     */
    public function status(string $tenantId): array
    {
        $url = $this->storedUrl($tenantId);

        return [
            'configured' => $url !== null,
            'enabled' => $url !== null && (bool) ($this->settings($tenantId)['enabled'] ?? false),
            'masked' => $url !== null ? '…' . mb_substr($url, -6) : null,
        ];
    }

    /**
     * Saves the URL (null or '' removes it) and the on / off switch. The caller validates the URL first.
     */
    public function save(string $tenantId, ?string $url, bool $enabled): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $url = $url !== null ? trim($url) : '';
        $settings['notifications']['slack'] = $url === ''
            ? ['webhook' => null, 'enabled' => false]
            : ['webhook' => Crypt::encryptString($url), 'enabled' => $enabled];
        $tenant->settings = $settings;
        $tenant->save();
    }

    /**
     * Posts a plain-text message to the company's channel. Returns false when Slack is off or the post failed.
     */
    public function post(string $tenantId, string $text): bool
    {
        $url = $this->webhookUrl($tenantId);
        if ($url === null) {
            return false;
        }

        try {
            $response = Http::timeout(10)->acceptJson()->post($url, ['text' => $text]);
            if ($response->successful()) {
                return true;
            }
            Log::warning('slack.post_failed', ['tenant_id' => $tenantId, 'status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::warning('slack.post_failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(string $tenantId): array
    {
        $tenant = Tenant::query()->whereKey($tenantId)->first(['id', 'settings']);
        $settings = $tenant instanceof Tenant && is_array($tenant->settings) ? $tenant->settings : [];
        $slack = $settings['notifications']['slack'] ?? [];

        return is_array($slack) ? $slack : [];
    }
}
