<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Token Manager Service
 * 
 * Handles token management for API Gateway with session-based reuse
 */
class TokenManager
{
    protected int $tokenTtl = 3600; // 1 hour
    protected int $maxTokensPerUser = 5;

    /**
     * Get or create token for user
     */
    public function getTokenForUser(User $user, string $ability = 'tenant'): string
    {
        $cacheKey = "api_token_{$user->id}_{$ability}";
        
        // Try to get existing token from cache
        $token = Cache::get($cacheKey);
        
        if ($token && $this->validateToken($token, $user)) {
            Log::debug('Reusing existing API token', [
                'user_id' => $user->id,
                'ability' => $ability
            ]);
            return $token;
        }

        // Create new token
        $token = $this->createNewToken($user, $ability);
        
        // Cache the token
        Cache::put($cacheKey, $token, $this->tokenTtl);
        
        // Clean up old tokens
        $this->cleanupOldTokens($user);
        
        Log::info('Created new API token', [
            'user_id' => $user->id,
            'ability' => $ability
        ]);
        
        return $token;
    }

    /**
     * Create new token for user
     */
    private function createNewToken(User $user, string $ability): string
    {
        $tokenName = "api-gateway-{$ability}-" . Str::random(8);
        
        return $user->createToken($tokenName, [$ability])->plainTextToken;
    }

    /**
     * Validate if token is still valid
     */
    private function validateToken(string $token, User $user): bool
    {
        try {
            // Check if token exists in database
            $tokenRecord = $user->tokens()
                ->where('token', hash('sha256', $token))
                ->where('expires_at', '>', now())
                ->first();
                
            return $tokenRecord !== null;
        } catch (\Exception $e) {
            Log::error('Token validation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up old tokens for user
     */
    private function cleanupOldTokens(User $user): void
    {
        try {
            $tokens = $user->tokens()
                ->where('name', 'like', 'api-gateway-%')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($tokens->count() > $this->maxTokensPerUser) {
                $tokensToDelete = $tokens->skip($this->maxTokensPerUser);
                
                foreach ($tokensToDelete as $token) {
                    $token->delete();
                }
                
                Log::info('Cleaned up old API tokens', [
                    'user_id' => $user->id,
                    'deleted_count' => $tokensToDelete->count()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Token cleanup error: ' . $e->getMessage(), [
                'user_id' => $user->id
            ]);
        }
    }

    /**
     * Revoke token for user
     */
    public function revokeToken(User $user, string $ability = 'tenant'): void
    {
        $cacheKey = "api_token_{$user->id}_{$ability}";
        Cache::forget($cacheKey);
        
        // Revoke all API gateway tokens for this ability
        $user->tokens()
            ->where('name', 'like', "api-gateway-{$ability}-%")
            ->delete();
            
        Log::info('Revoked API tokens', [
            'user_id' => $user->id,
            'ability' => $ability
        ]);
    }

    /**
     * Revoke all tokens for user
     */
    public function revokeAllTokens(User $user): void
    {
        // Clear cache
        $abilities = ['tenant', 'admin'];
        foreach ($abilities as $ability) {
            $cacheKey = "api_token_{$user->id}_{$ability}";
            Cache::forget($cacheKey);
        }
        
        // Revoke all API gateway tokens
        $user->tokens()
            ->where('name', 'like', 'api-gateway-%')
            ->delete();
            
        Log::info('Revoked all API tokens', [
            'user_id' => $user->id
        ]);
    }

    /**
     * Get token statistics
     */
    public function getTokenStats(User $user): array
    {
        $tokens = $user->tokens()
            ->where('name', 'like', 'api-gateway-%')
            ->get();

        return [
            'total_tokens' => $tokens->count(),
            'active_tokens' => $tokens->where('expires_at', '>', now())->count(),
            'expired_tokens' => $tokens->where('expires_at', '<=', now())->count(),
            'max_tokens_per_user' => $this->maxTokensPerUser
        ];
    }

    /**
     * Set token TTL
     */
    public function setTokenTtl(int $ttl): void
    {
        $this->tokenTtl = $ttl;
    }

    /**
     * Set max tokens per user
     */
    public function setMaxTokensPerUser(int $max): void
    {
        $this->maxTokensPerUser = $max;
    }
}
