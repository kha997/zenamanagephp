<?php declare(strict_types=1);

namespace App\Support;

/**
 * Helper for building tenant invitation URLs
 */
class TenantInvitationUrl
{
    /**
     * Build the full URL for an invitation landing page
     * 
     * @param string $token The invitation token
     * @return string Full URL to /invite/{token}
     */
    public static function buildUrl(string $token): string
    {
        $baseUrl = rtrim(config('app.url'), '/');
        return "{$baseUrl}/invite/{$token}";
    }
}

