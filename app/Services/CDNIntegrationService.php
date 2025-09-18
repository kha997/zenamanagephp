<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * CDNIntegrationService - Service cho CDN integration
 */
class CDNIntegrationService
{
    private array $cdnConfig;

    public function __construct()
    {
        $this->cdnConfig = [
            'enabled' => config('cdn.enabled', false),
            'providers' => [
                'cloudflare' => [
                    'enabled' => config('cdn.cloudflare.enabled', false),
                    'api_token' => config('cdn.cloudflare.api_token'),
                    'zone_id' => config('cdn.cloudflare.zone_id'),
                    'base_url' => config('cdn.cloudflare.base_url')
                ],
                'aws_cloudfront' => [
                    'enabled' => config('cdn.aws_cloudfront.enabled', false),
                    'distribution_id' => config('cdn.aws_cloudfront.distribution_id'),
                    'access_key' => config('cdn.aws_cloudfront.access_key'),
                    'secret_key' => config('cdn.aws_cloudfront.secret_key'),
                    'region' => config('cdn.aws_cloudfront.region')
                ],
                'keycdn' => [
                    'enabled' => config('cdn.keycdn.enabled', false),
                    'api_key' => config('cdn.keycdn.api_key'),
                    'zone_id' => config('cdn.keycdn.zone_id'),
                    'base_url' => config('cdn.keycdn.base_url')
                ]
            ],
            'default_provider' => config('cdn.default_provider', 'cloudflare'),
            'cache_ttl' => config('cdn.cache_ttl', 3600),
            'purge_on_update' => config('cdn.purge_on_update', true)
        ];
    }

    /**
     * Purge CDN cache
     */
    public function purgeCache(array $urls = [], string $provider = null): array
    {
        $provider = $provider ?? $this->cdnConfig['default_provider'];
        $results = [];

        try {
            switch ($provider) {
                case 'cloudflare':
                    $results = $this->purgeCloudflareCache($urls);
                    break;
                case 'aws_cloudfront':
                    $results = $this->purgeCloudFrontCache($urls);
                    break;
                case 'keycdn':
                    $results = $this->purgeKeyCDNCache($urls);
                    break;
                default:
                    $results = ['error' => 'Unsupported CDN provider'];
            }
        } catch (\Exception $e) {
            Log::error('Failed to purge CDN cache', [
                'provider' => $provider,
                'urls' => $urls,
                'error' => $e->getMessage()
            ]);
            $results = ['error' => $e->getMessage()];
        }

        return $results;
    }

    /**
     * Purge Cloudflare cache
     */
    private function purgeCloudflareCache(array $urls): array
    {
        $config = $this->cdnConfig['providers']['cloudflare'];
        
        if (!$config['enabled'] || !$config['api_token'] || !$config['zone_id']) {
            return ['error' => 'Cloudflare CDN not configured'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_token'],
            'Content-Type' => 'application/json'
        ])->post("https://api.cloudflare.com/client/v4/zones/{$config['zone_id']}/purge_cache", [
            'purge_everything' => empty($urls),
            'files' => $urls
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'provider' => 'cloudflare',
                'purged_urls' => count($urls),
                'response' => $response->json()
            ];
        }

        return [
            'success' => false,
            'provider' => 'cloudflare',
            'error' => $response->body()
        ];
    }

    /**
     * Purge AWS CloudFront cache
     */
    private function purgeCloudFrontCache(array $urls): array
    {
        $config = $this->cdnConfig['providers']['aws_cloudfront'];
        
        if (!$config['enabled'] || !$config['distribution_id']) {
            return ['error' => 'AWS CloudFront not configured'];
        }

        // This would require AWS SDK implementation
        // For now, return placeholder
        return [
            'success' => false,
            'provider' => 'aws_cloudfront',
            'error' => 'AWS CloudFront integration requires AWS SDK'
        ];
    }

    /**
     * Purge KeyCDN cache
     */
    private function purgeKeyCDNCache(array $urls): array
    {
        $config = $this->cdnConfig['providers']['keycdn'];
        
        if (!$config['enabled'] || !$config['api_key'] || !$config['zone_id']) {
            return ['error' => 'KeyCDN not configured'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($config['api_key'] . ':'),
            'Content-Type' => 'application/json'
        ])->post("https://api.keycdn.com/zones/{$config['zone_id']}/purge", [
            'urls' => $urls
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'provider' => 'keycdn',
                'purged_urls' => count($urls),
                'response' => $response->json()
            ];
        }

        return [
            'success' => false,
            'provider' => 'keycdn',
            'error' => $response->body()
        ];
    }

    /**
     * Upload file to CDN
     */
    public function uploadToCDN(string $filePath, string $cdnPath = null, string $provider = null): array
    {
        $provider = $provider ?? $this->cdnConfig['default_provider'];
        $cdnPath = $cdnPath ?? basename($filePath);

        try {
            switch ($provider) {
                case 'cloudflare':
                    return $this->uploadToCloudflare($filePath, $cdnPath);
                case 'aws_cloudfront':
                    return $this->uploadToCloudFront($filePath, $cdnPath);
                case 'keycdn':
                    return $this->uploadToKeyCDN($filePath, $cdnPath);
                default:
                    return ['error' => 'Unsupported CDN provider'];
            }
        } catch (\Exception $e) {
            Log::error('Failed to upload to CDN', [
                'provider' => $provider,
                'file_path' => $filePath,
                'cdn_path' => $cdnPath,
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Upload to Cloudflare
     */
    private function uploadToCloudflare(string $filePath, string $cdnPath): array
    {
        $config = $this->cdnConfig['providers']['cloudflare'];
        
        if (!$config['enabled'] || !$config['api_token'] || !$config['zone_id']) {
            return ['error' => 'Cloudflare CDN not configured'];
        }

        // Read file content
        $fileContent = Storage::get($filePath);
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_token'],
            'Content-Type' => 'application/octet-stream'
        ])->post("https://api.cloudflare.com/client/v4/zones/{$config['zone_id']}/assets", [
            'file' => base64_encode($fileContent),
            'path' => $cdnPath
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'provider' => 'cloudflare',
                'cdn_url' => $config['base_url'] . '/' . $cdnPath,
                'response' => $response->json()
            ];
        }

        return [
            'success' => false,
            'provider' => 'cloudflare',
            'error' => $response->body()
        ];
    }

    /**
     * Upload to AWS CloudFront
     */
    private function uploadToCloudFront(string $filePath, string $cdnPath): array
    {
        // This would require AWS SDK implementation
        return [
            'success' => false,
            'provider' => 'aws_cloudfront',
            'error' => 'AWS CloudFront integration requires AWS SDK'
        ];
    }

    /**
     * Upload to KeyCDN
     */
    private function uploadToKeyCDN(string $filePath, string $cdnPath): array
    {
        $config = $this->cdnConfig['providers']['keycdn'];
        
        if (!$config['enabled'] || !$config['api_key'] || !$config['zone_id']) {
            return ['error' => 'KeyCDN not configured'];
        }

        // Read file content
        $fileContent = Storage::get($filePath);
        
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($config['api_key'] . ':'),
            'Content-Type' => 'application/octet-stream'
        ])->post("https://api.keycdn.com/zones/{$config['zone_id']}/assets", [
            'file' => base64_encode($fileContent),
            'path' => $cdnPath
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'provider' => 'keycdn',
                'cdn_url' => $config['base_url'] . '/' . $cdnPath,
                'response' => $response->json()
            ];
        }

        return [
            'success' => false,
            'provider' => 'keycdn',
            'error' => $response->body()
        ];
    }

    /**
     * Get CDN statistics
     */
    public function getCDNStatistics(string $provider = null): array
    {
        $provider = $provider ?? $this->cdnConfig['default_provider'];

        try {
            switch ($provider) {
                case 'cloudflare':
                    return $this->getCloudflareStats();
                case 'aws_cloudfront':
                    return $this->getCloudFrontStats();
                case 'keycdn':
                    return $this->getKeyCDNStats();
                default:
                    return ['error' => 'Unsupported CDN provider'];
            }
        } catch (\Exception $e) {
            Log::error('Failed to get CDN statistics', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get Cloudflare statistics
     */
    private function getCloudflareStats(): array
    {
        $config = $this->cdnConfig['providers']['cloudflare'];
        
        if (!$config['enabled'] || !$config['api_token'] || !$config['zone_id']) {
            return ['error' => 'Cloudflare CDN not configured'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_token']
        ])->get("https://api.cloudflare.com/client/v4/zones/{$config['zone_id']}/analytics/dashboard");

        if ($response->successful()) {
            return [
                'success' => true,
                'provider' => 'cloudflare',
                'stats' => $response->json()
            ];
        }

        return [
            'success' => false,
            'provider' => 'cloudflare',
            'error' => $response->body()
        ];
    }

    /**
     * Get AWS CloudFront statistics
     */
    private function getCloudFrontStats(): array
    {
        return [
            'success' => false,
            'provider' => 'aws_cloudfront',
            'error' => 'AWS CloudFront integration requires AWS SDK'
        ];
    }

    /**
     * Get KeyCDN statistics
     */
    private function getKeyCDNStats(): array
    {
        $config = $this->cdnConfig['providers']['keycdn'];
        
        if (!$config['enabled'] || !$config['api_key'] || !$config['zone_id']) {
            return ['error' => 'KeyCDN not configured'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($config['api_key'] . ':')
        ])->get("https://api.keycdn.com/zones/{$config['zone_id']}/stats");

        if ($response->successful()) {
            return [
                'success' => true,
                'provider' => 'keycdn',
                'stats' => $response->json()
            ];
        }

        return [
            'success' => false,
            'provider' => 'keycdn',
            'error' => $response->body()
        ];
    }

    /**
     * Generate CDN URL
     */
    public function generateCDNUrl(string $path, string $provider = null): string
    {
        $provider = $provider ?? $this->cdnConfig['default_provider'];
        $config = $this->cdnConfig['providers'][$provider] ?? null;

        if (!$config || !$config['enabled'] || !$config['base_url']) {
            return $path; // Return original path if CDN not configured
        }

        return rtrim($config['base_url'], '/') . '/' . ltrim($path, '/');
    }

    /**
     * Check CDN health
     */
    public function checkCDNHealth(string $provider = null): array
    {
        $provider = $provider ?? $this->cdnConfig['default_provider'];
        $config = $this->cdnConfig['providers'][$provider] ?? null;

        if (!$config || !$config['enabled']) {
            return [
                'healthy' => false,
                'provider' => $provider,
                'error' => 'CDN not configured or disabled'
            ];
        }

        try {
            // Test CDN connectivity
            $testUrl = $this->generateCDNUrl('/test-health-check');
            $response = Http::timeout(10)->get($testUrl);

            return [
                'healthy' => $response->successful(),
                'provider' => $provider,
                'response_time' => $response->transferStats?->getTransferTime() ?? 0,
                'status_code' => $response->status()
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'provider' => $provider,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get CDN configuration
     */
    public function getCDNConfiguration(): array
    {
        return [
            'enabled' => $this->cdnConfig['enabled'],
            'default_provider' => $this->cdnConfig['default_provider'],
            'providers' => array_map(function ($provider) {
                return [
                    'enabled' => $provider['enabled'],
                    'configured' => !empty($provider['api_token'] ?? $provider['api_key'] ?? $provider['access_key'])
                ];
            }, $this->cdnConfig['providers']),
            'cache_ttl' => $this->cdnConfig['cache_ttl'],
            'purge_on_update' => $this->cdnConfig['purge_on_update']
        ];
    }
}
