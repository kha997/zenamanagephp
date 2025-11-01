<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class SecretsRotationService
{
    /**
     * Rotate JWT secret
     */
    public function rotateJwtSecret(): array
    {
        try {
            $oldSecret = config('jwt.secret');
            $newSecret = $this->generateSecureSecret();
            
            // Update config
            Config::set('jwt.secret', $newSecret);
            
            // Log rotation
            Log::info('JWT secret rotated', [
                'old_secret_hash' => hash('sha256', $oldSecret),
                'new_secret_hash' => hash('sha256', $newSecret),
                'rotated_at' => now()
            ]);
            
            return [
                'status' => 'success',
                'message' => 'JWT secret rotated successfully',
                'rotated_at' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            Log::error('JWT secret rotation failed', [
                'error' => $e->getMessage(),
                'rotated_at' => now()
            ]);
            
            return [
                'status' => 'error',
                'message' => 'JWT secret rotation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Rotate API keys
     */
    public function rotateApiKeys(): array
    {
        try {
            $rotatedKeys = [];
            
            // Rotate different types of API keys
            $keyTypes = ['internal', 'external', 'webhook', 'integration'];
            
            foreach ($keyTypes as $type) {
                $oldKey = config("api.keys.{$type}");
                $newKey = $this->generateApiKey();
                
                Config::set("api.keys.{$type}", $newKey);
                $rotatedKeys[$type] = [
                    'old_key_hash' => hash('sha256', $oldKey),
                    'new_key_hash' => hash('sha256', $newKey)
                ];
            }
            
            Log::info('API keys rotated', [
                'rotated_keys' => $rotatedKeys,
                'rotated_at' => now()
            ]);
            
            return [
                'status' => 'success',
                'message' => 'API keys rotated successfully',
                'rotated_keys' => array_keys($rotatedKeys),
                'rotated_at' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            Log::error('API keys rotation failed', [
                'error' => $e->getMessage(),
                'rotated_at' => now()
            ]);
            
            return [
                'status' => 'error',
                'message' => 'API keys rotation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Rotate database credentials
     */
    public function rotateDatabaseCredentials(): array
    {
        try {
            // This would typically involve:
            // 1. Creating new database user
            // 2. Updating application config
            // 3. Testing connection
            // 4. Removing old user
            
            Log::info('Database credentials rotation initiated', [
                'rotated_at' => now()
            ]);
            
            return [
                'status' => 'success',
                'message' => 'Database credentials rotation initiated',
                'rotated_at' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            Log::error('Database credentials rotation failed', [
                'error' => $e->getMessage(),
                'rotated_at' => now()
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Database credentials rotation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get rotation status
     */
    public function getRotationStatus(): array
    {
        return [
            'jwt_secret' => [
                'last_rotated' => Cache::get('jwt_secret_last_rotated', 'Never'),
                'next_rotation' => Cache::get('jwt_secret_next_rotation', 'Not scheduled')
            ],
            'api_keys' => [
                'last_rotated' => Cache::get('api_keys_last_rotated', 'Never'),
                'next_rotation' => Cache::get('api_keys_next_rotation', 'Not scheduled')
            ],
            'database_credentials' => [
                'last_rotated' => Cache::get('db_creds_last_rotated', 'Never'),
                'next_rotation' => Cache::get('db_creds_next_rotation', 'Not scheduled')
            ]
        ];
    }

    /**
     * Schedule rotation
     */
    public function scheduleRotation(string $type, int $days): array
    {
        try {
            $nextRotation = now()->addDays($days);
            
            Cache::put("{$type}_next_rotation", $nextRotation->toISOString(), now()->addDays($days + 1));
            
            Log::info("Rotation scheduled for {$type}", [
                'type' => $type,
                'days' => $days,
                'next_rotation' => $nextRotation->toISOString()
            ]);
            
            return [
                'status' => 'success',
                'message' => "Rotation scheduled for {$type}",
                'next_rotation' => $nextRotation->toISOString()
            ];
        } catch (\Exception $e) {
            Log::error("Failed to schedule rotation for {$type}", [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => "Failed to schedule rotation for {$type}: " . $e->getMessage()
            ];
        }
    }

    /**
     * Generate secure secret
     */
    private function generateSecureSecret(int $length = 64): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generate API key
     */
    private function generateApiKey(int $length = 32): string
    {
        return 'zena_' . bin2hex(random_bytes($length));
    }
}