<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Carbon\Carbon;

/**
 * Refresh Token Service
 * 
 * Manages refresh token rotation and blacklisting for enhanced security
 */
class RefreshTokenService
{
    private string $jwtSecret;
    private int $refreshTtl;
    private bool $rotationEnabled;
    private bool $blacklistEnabled;

    public function __construct()
    {
        $this->jwtSecret = config('jwt.secret');
        $this->refreshTtl = config('jwt.refresh_ttl');
        $this->rotationEnabled = config('jwt.rotation_enabled', true);
        $this->blacklistEnabled = config('jwt.blacklist_enabled', true);
    }

    /**
     * Generate refresh token for user
     */
    public function generateRefreshToken(User $user): string
    {
        $payload = [
            'user_id' => $user->id,
            'type' => 'refresh',
            'iat' => time(),
            'exp' => time() + $this->refreshTtl,
            'jti' => uniqid('refresh_', true), // Unique token ID
            'iss' => config('jwt.issuer'),
            'aud' => config('jwt.audience'),
        ];

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    /**
     * Validate refresh token and return user
     */
    public function validateRefreshToken(string $token): ?User
    {
        try {
            // Check if token is blacklisted
            if ($this->blacklistEnabled && $this->isTokenBlacklisted($token)) {
                return null;
            }

            $payload = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            // Verify token type
            if ($payload->type !== 'refresh') {
                return null;
            }

            // Check expiration
            if ($payload->exp < time()) {
                return null;
            }

            return User::find($payload->user_id);

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Rotate refresh token (generate new one and blacklist old)
     */
    public function rotateRefreshToken(string $oldToken, User $user): string
    {
        if (!$this->rotationEnabled) {
            return $oldToken; // Return same token if rotation disabled
        }

        // Blacklist old token
        if ($this->blacklistEnabled) {
            $this->blacklistToken($oldToken);
        }

        // Generate new refresh token
        return $this->generateRefreshToken($user);
    }

    /**
     * Blacklist a token
     */
    public function blacklistToken(string $token): void
    {
        if (!$this->blacklistEnabled) {
            return;
        }

        try {
            $payload = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            $jti = $payload->jti ?? $token; // Use JTI or token as identifier
            
            // Store in cache with TTL
            Cache::put("blacklist_token_{$jti}", true, $this->refreshTtl);
            
        } catch (\Exception $e) {
            // If token is invalid, still blacklist it
            Cache::put("blacklist_token_{$token}", true, $this->refreshTtl);
        }
    }

    /**
     * Check if token is blacklisted
     */
    public function isTokenBlacklisted(string $token): bool
    {
        if (!$this->blacklistEnabled) {
            return false;
        }

        try {
            $payload = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            $jti = $payload->jti ?? $token;
            
            return Cache::has("blacklist_token_{$jti}");
            
        } catch (\Exception $e) {
            return Cache::has("blacklist_token_{$token}");
        }
    }

    /**
     * Blacklist all tokens for a user
     */
    public function blacklistUserTokens(User $user): void
    {
        if (!$this->blacklistEnabled) {
            return;
        }

        // Store user blacklist timestamp
        Cache::put("blacklist_user_{$user->id}", time(), $this->refreshTtl);
    }

    /**
     * Check if user tokens are blacklisted
     */
    public function isUserBlacklisted(User $user): bool
    {
        if (!$this->blacklistEnabled) {
            return false;
        }

        return Cache::has("blacklist_user_{$user->id}");
    }

    /**
     * Clean expired blacklist entries
     */
    public function cleanExpiredBlacklist(): void
    {
        // Cache will automatically clean expired entries
        // This method is for future database-based blacklist implementation
    }
}
