<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class EmailTemplateCacheService
{
    protected $cacheEnabled;
    protected $cacheTtl;
    protected $cacheDriver;

    public function __construct()
    {
        $this->cacheEnabled = config('mail.template_cache.enabled', true);
        $this->cacheTtl = config('mail.template_cache.ttl', 3600);
        $this->cacheDriver = config('mail.template_cache.driver', 'file');
    }

    /**
     * Get cached email template
     */
    public function getCachedTemplate(string $templateName, array $data = []): ?string
    {
        if (!$this->cacheEnabled) {
            return null;
        }

        $cacheKey = $this->generateCacheKey($templateName, $data);
        
        try {
            return Cache::store($this->cacheDriver)->get($cacheKey);
        } catch (\Exception $e) {
            Log::warning('Failed to get cached email template', [
                'template' => $templateName,
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Cache email template
     */
    public function cacheTemplate(string $templateName, array $data, string $renderedContent): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        $cacheKey = $this->generateCacheKey($templateName, $data);
        
        try {
            Cache::store($this->cacheDriver)->put($cacheKey, $renderedContent, $this->cacheTtl);
            
            Log::debug('Email template cached successfully', [
                'template' => $templateName,
                'cache_key' => $cacheKey,
                'ttl' => $this->cacheTtl,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to cache email template', [
                'template' => $templateName,
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Render email template with caching
     */
    public function renderTemplate(string $templateName, array $data = []): string
    {
        // Try to get from cache first
        $cachedContent = $this->getCachedTemplate($templateName, $data);
        if ($cachedContent) {
            return $cachedContent;
        }

        // Render template
        $renderedContent = $this->renderBladeTemplate($templateName, $data);

        // Cache the rendered content
        $this->cacheTemplate($templateName, $data, $renderedContent);

        return $renderedContent;
    }

    /**
     * Clear template cache
     */
    public function clearCache(string $templateName = null): void
    {
        try {
            if ($templateName) {
                // Clear specific template cache
                $pattern = "email_template_{$templateName}_*";
                $this->clearCacheByPattern($pattern);
            } else {
                // Clear all email template cache
                $this->clearCacheByPattern('email_template_*');
            }

            Log::info('Email template cache cleared', [
                'template' => $templateName ?? 'all',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear email template cache', [
                'template' => $templateName ?? 'all',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        try {
            $stats = [
                'enabled' => $this->cacheEnabled,
                'driver' => $this->cacheDriver,
                'ttl' => $this->cacheTtl,
                'total_cached' => 0,
                'cache_size' => 0,
                'templates' => [],
            ];

            if ($this->cacheEnabled) {
                $pattern = 'email_template_*';
                $keys = $this->getCacheKeys($pattern);
                
                $stats['total_cached'] = count($keys);
                
                foreach ($keys as $key) {
                    $templateInfo = $this->parseCacheKey($key);
                    if ($templateInfo) {
                        $stats['templates'][$templateInfo['template']] = [
                            'count' => ($stats['templates'][$templateInfo['template']]['count'] ?? 0) + 1,
                            'last_cached' => $templateInfo['timestamp'] ?? null,
                        ];
                    }
                }
            }

            return $stats;
        } catch (\Exception $e) {
            Log::error('Failed to get cache statistics', [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'enabled' => $this->cacheEnabled,
                'driver' => $this->cacheDriver,
                'ttl' => $this->cacheTtl,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Warm up cache for common templates
     */
    public function warmUpCache(): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        try {
            $commonTemplates = [
                'invitation' => [
                    'organizationName' => 'Demo Organization',
                    'inviterName' => 'Admin',
                    'roleDisplayName' => 'User',
                ],
                'welcome' => [
                    'organizationName' => 'Demo Organization',
                    'roleDisplayName' => 'User',
                ],
            ];

            foreach ($commonTemplates as $template => $data) {
                $this->renderTemplate($template, $data);
            }

            Log::info('Email template cache warmed up successfully');
        } catch (\Exception $e) {
            Log::error('Failed to warm up email template cache', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate cache key
     */
    private function generateCacheKey(string $templateName, array $data): string
    {
        $dataHash = hash('sha256', json_encode($data));
        $timestamp = now()->format('Y-m-d-H');
        
        return "email_template_{$templateName}_{$dataHash}_{$timestamp}";
    }

    /**
     * Render Blade template
     */
    private function renderBladeTemplate(string $templateName, array $data): string
    {
        try {
            $viewPath = "emails.{$templateName}";
            
            if (!View::exists($viewPath)) {
                throw new \Exception("Email template '{$viewPath}' not found");
            }

            return View::make($viewPath, $data)->render();
        } catch (\Exception $e) {
            Log::error('Failed to render email template', [
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Clear cache by pattern
     */
    private function clearCacheByPattern(string $pattern): void
    {
        $keys = $this->getCacheKeys($pattern);
        
        foreach ($keys as $key) {
            Cache::store($this->cacheDriver)->forget($key);
        }
    }

    /**
     * Get cache keys by pattern
     */
    private function getCacheKeys(string $pattern): array
    {
        try {
            if ($this->cacheDriver === 'redis') {
                return Cache::getRedis()->keys($pattern);
            } elseif ($this->cacheDriver === 'file') {
                return $this->getFileCacheKeys($pattern);
            } else {
                // For other drivers, we can't easily get keys by pattern
                return [];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get cache keys', [
                'pattern' => $pattern,
                'driver' => $this->cacheDriver,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get file cache keys
     */
    private function getFileCacheKeys(string $pattern): array
    {
        try {
            $cachePath = storage_path('framework/cache/data');
            $keys = [];
            
            if (File::exists($cachePath)) {
                $files = File::allFiles($cachePath);
                
                foreach ($files as $file) {
                    $filename = $file->getFilename();
                    if (fnmatch($pattern, $filename)) {
                        $keys[] = str_replace('.php', '', $filename);
                    }
                }
            }
            
            return $keys;
        } catch (\Exception $e) {
            Log::warning('Failed to get file cache keys', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Parse cache key to extract template info
     */
    private function parseCacheKey(string $key): ?array
    {
        try {
            $parts = explode('_', $key);
            if (count($parts) >= 4 && $parts[0] === 'email' && $parts[1] === 'template') {
                return [
                    'template' => $parts[2],
                    'hash' => $parts[3] ?? null,
                    'timestamp' => $parts[4] ?? null,
                ];
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}