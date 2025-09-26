<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * CDN Service
 * 
 * Provides CDN integration for static assets including:
 * - Asset URL generation with CDN prefix
 * - Asset versioning and cache busting
 * - Asset optimization and compression
 * - CDN health monitoring
 * - Fallback to local assets
 */
class CDNService
{
    private string $cdnUrl;
    private string $cdnProvider;
    private bool $enabled;
    private array $assetTypes;
    private array $cdnConfig;

    public function __construct()
    {
        $this->enabled = config('cdn.enabled', false);
        $this->cdnUrl = config('cdn.url', '');
        $this->cdnProvider = config('cdn.provider', 'cloudflare');
        $this->assetTypes = config('cdn.asset_types', ['css', 'js', 'images', 'fonts']);
        $this->cdnConfig = config('cdn', []);
    }

    /**
     * Get CDN URL for asset
     */
    public function url(string $path, bool $versioned = true): string
    {
        if (!$this->enabled || empty($this->cdnUrl)) {
            return asset($path);
        }

        $cdnPath = $this->buildCdnPath($path, $versioned);
        
        return $this->cdnUrl . '/' . ltrim($cdnPath, '/');
    }

    /**
     * Get CDN URL for multiple assets
     */
    public function urls(array $paths, bool $versioned = true): array
    {
        return array_map(function ($path) use ($versioned) {
            return $this->url($path, $versioned);
        }, $paths);
    }

    /**
     * Upload asset to CDN
     */
    public function upload(string $localPath, string $cdnPath = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $cdnPath = $cdnPath ?: $localPath;
            $content = Storage::disk('local')->get($localPath);
            
            if ($content === null) {
                Log::warning("Failed to read local file: {$localPath}");
                return false;
            }

            $success = Storage::disk('cdn')->put($cdnPath, $content);
            
            if ($success) {
                Log::info("Asset uploaded to CDN: {$cdnPath}");
            }

            return $success;

        } catch (\Exception $e) {
            Log::error("CDN upload failed: " . $e->getMessage(), [
                'local_path' => $localPath,
                'cdn_path' => $cdnPath
            ]);
            return false;
        }
    }

    /**
     * Upload multiple assets to CDN
     */
    public function uploadBatch(array $assets): array
    {
        $results = [];
        
        foreach ($assets as $localPath => $cdnPath) {
            $results[$localPath] = $this->upload($localPath, $cdnPath);
        }

        return $results;
    }

    /**
     * Purge CDN cache for specific assets
     */
    public function purge(array $paths): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            return match ($this->cdnProvider) {
                'cloudflare' => $this->purgeCloudflare($paths),
                'aws' => $this->purgeAWS($paths),
                'fastly' => $this->purgeFastly($paths),
                default => $this->purgeGeneric($paths)
            };

        } catch (\Exception $e) {
            Log::error("CDN purge failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Purge all CDN cache
     */
    public function purgeAll(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            return match ($this->cdnProvider) {
                'cloudflare' => $this->purgeCloudflareAll(),
                'aws' => $this->purgeAWSAll(),
                'fastly' => $this->purgeFastlyAll(),
                default => $this->purgeGenericAll()
            };

        } catch (\Exception $e) {
            Log::error("CDN purge all failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check CDN health
     */
    public function healthCheck(): array
    {
        $health = [
            'enabled' => $this->enabled,
            'provider' => $this->cdnProvider,
            'url' => $this->cdnUrl,
            'status' => 'unknown',
            'response_time' => null,
            'last_check' => now()->toISOString()
        ];

        if (!$this->enabled) {
            $health['status'] = 'disabled';
            return $health;
        }

        try {
            $startTime = microtime(true);
            $response = $this->testCdnConnection();
            $endTime = microtime(true);

            $health['status'] = $response ? 'healthy' : 'unhealthy';
            $health['response_time'] = ($endTime - $startTime) * 1000; // milliseconds

        } catch (\Exception $e) {
            $health['status'] = 'error';
            $health['error'] = $e->getMessage();
        }

        return $health;
    }

    /**
     * Get CDN statistics
     */
    public function getStats(): array
    {
        return [
            'enabled' => $this->enabled,
            'provider' => $this->cdnProvider,
            'url' => $this->cdnUrl,
            'asset_types' => $this->assetTypes,
            'config' => $this->cdnConfig,
            'health' => $this->healthCheck()
        ];
    }

    /**
     * Build CDN path with versioning
     */
    private function buildCdnPath(string $path, bool $versioned): string
    {
        $cdnPath = ltrim($path, '/');

        if ($versioned && $this->shouldVersionAsset($path)) {
            $version = $this->getAssetVersion($path);
            $cdnPath = $this->addVersionToPath($cdnPath, $version);
        }

        return $cdnPath;
    }

    /**
     * Check if asset should be versioned
     */
    private function shouldVersionAsset(string $path): bool
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return in_array($extension, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg']);
    }

    /**
     * Get asset version (file modification time)
     */
    private function getAssetVersion(string $path): string
    {
        $fullPath = public_path($path);
        
        if (file_exists($fullPath)) {
            return (string) filemtime($fullPath);
        }

        return '1';
    }

    /**
     * Add version to asset path
     */
    private function addVersionToPath(string $path, string $version): string
    {
        $pathInfo = pathinfo($path);
        $extension = $pathInfo['extension'] ?? '';
        $filename = $pathInfo['filename'] ?? '';
        $dirname = $pathInfo['dirname'] ?? '';

        $versionedFilename = $filename . '.v' . $version . '.' . $extension;
        
        return $dirname !== '.' ? $dirname . '/' . $versionedFilename : $versionedFilename;
    }

    /**
     * Test CDN connection
     */
    private function testCdnConnection(): bool
    {
        $testUrl = $this->cdnUrl . '/test.txt';
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'HEAD'
            ]
        ]);

        $headers = @get_headers($testUrl, 1, $context);
        return $headers && strpos($headers[0], '200') !== false;
    }

    /**
     * Purge Cloudflare cache
     */
    private function purgeCloudflare(array $paths): bool
    {
        $apiKey = config('cdn.cloudflare.api_key');
        $zoneId = config('cdn.cloudflare.zone_id');

        if (!$apiKey || !$zoneId) {
            Log::warning('Cloudflare API credentials not configured');
            return false;
        }

        $urls = array_map(function ($path) {
            return $this->cdnUrl . '/' . ltrim($path, '/');
        }, $paths);

        $data = ['files' => $urls];
        
        return $this->makeCloudflareRequest('purge_cache', $data);
    }

    /**
     * Purge all Cloudflare cache
     */
    private function purgeCloudflareAll(): bool
    {
        $data = ['purge_everything' => true];
        return $this->makeCloudflareRequest('purge_cache', $data);
    }

    /**
     * Make Cloudflare API request
     */
    private function makeCloudflareRequest(string $endpoint, array $data): bool
    {
        $apiKey = config('cdn.cloudflare.api_key');
        $zoneId = config('cdn.cloudflare.zone_id');
        
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/{$endpoint}";
        
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $success = $httpCode === 200;
        
        if (!$success) {
            Log::error("Cloudflare API request failed", [
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'response' => $response
            ]);
        }

        return $success;
    }

    /**
     * Purge AWS CloudFront cache
     */
    private function purgeAWS(array $paths): bool
    {
        // Implementation for AWS CloudFront
        Log::info('AWS CloudFront purge not implemented yet');
        return false;
    }

    /**
     * Purge all AWS CloudFront cache
     */
    private function purgeAWSAll(): bool
    {
        // Implementation for AWS CloudFront
        Log::info('AWS CloudFront purge all not implemented yet');
        return false;
    }

    /**
     * Purge Fastly cache
     */
    private function purgeFastly(array $paths): bool
    {
        // Implementation for Fastly
        Log::info('Fastly purge not implemented yet');
        return false;
    }

    /**
     * Purge all Fastly cache
     */
    private function purgeFastlyAll(): bool
    {
        // Implementation for Fastly
        Log::info('Fastly purge all not implemented yet');
        return false;
    }

    /**
     * Generic purge implementation
     */
    private function purgeGeneric(array $paths): bool
    {
        Log::info('Generic CDN purge not implemented yet');
        return false;
    }

    /**
     * Generic purge all implementation
     */
    private function purgeGenericAll(): bool
    {
        Log::info('Generic CDN purge all not implemented yet');
        return false;
    }
}
