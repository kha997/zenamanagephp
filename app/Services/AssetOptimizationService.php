<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class AssetOptimizationService
{
    /**
     * Get asset optimization metrics
     */
    public function getAssetMetrics(): array
    {
        $metrics = [];

        try {
            $publicPath = public_path();
            $assetsPath = $publicPath . '/assets';

            // Get asset file sizes
            $metrics['css_size'] = $this->getDirectorySize($publicPath . '/css');
            $metrics['js_size'] = $this->getDirectorySize($publicPath . '/js');
            $metrics['images_size'] = $this->getDirectorySize($publicPath . '/images');
            $metrics['fonts_size'] = $this->getDirectorySize($publicPath . '/fonts');

            // Get total assets size
            $metrics['total_size'] = $metrics['css_size'] + $metrics['js_size'] + 
                                    $metrics['images_size'] + $metrics['fonts_size'];

            // Get file counts
            $metrics['file_counts'] = [
                'css' => $this->getFileCount($publicPath . '/css'),
                'js' => $this->getFileCount($publicPath . '/js'),
                'images' => $this->getFileCount($publicPath . '/images'),
                'fonts' => $this->getFileCount($publicPath . '/fonts'),
            ];

            // Check for minified files
            $metrics['minification'] = [
                'css_minified' => $this->getMinifiedFileCount($publicPath . '/css'),
                'js_minified' => $this->getMinifiedFileCount($publicPath . '/js'),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get asset metrics', ['error' => $e->getMessage()]);
            $metrics['error'] = $e->getMessage();
        }

        return $metrics;
    }

    /**
     * Get directory size in bytes
     */
    private function getDirectorySize(string $path): int
    {
        if (!File::exists($path)) {
            return 0;
        }

        $size = 0;
        $files = File::allFiles($path);
        
        foreach ($files as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    /**
     * Get file count in directory
     */
    private function getFileCount(string $path): int
    {
        if (!File::exists($path)) {
            return 0;
        }

        return count(File::allFiles($path));
    }

    /**
     * Get count of minified files
     */
    private function getMinifiedFileCount(string $path): int
    {
        if (!File::exists($path)) {
            return 0;
        }

        $files = File::allFiles($path);
        $minifiedCount = 0;

        foreach ($files as $file) {
            if (str_contains($file->getFilename(), '.min.')) {
                $minifiedCount++;
            }
        }

        return $minifiedCount;
    }

    /**
     * Get production asset configuration
     */
    public function getProductionAssetConfig(): array
    {
        return [
            'compression' => [
                'gzip' => true,
                'brotli' => true,
                'level' => 9,
            ],
            'minification' => [
                'css' => true,
                'js' => true,
                'html' => true,
            ],
            'bundling' => [
                'css_bundle' => true,
                'js_bundle' => true,
                'vendor_separation' => true,
            ],
            'caching' => [
                'browser_cache' => 31536000, // 1 year
                'cdn_cache' => 31536000,
                'versioning' => true,
            ],
            'optimization' => [
                'image_optimization' => true,
                'font_optimization' => true,
                'critical_css' => true,
                'lazy_loading' => true,
            ],
        ];
    }

    /**
     * Generate asset optimization recommendations
     */
    public function getOptimizationRecommendations(): array
    {
        $recommendations = [];
        $metrics = $this->getAssetMetrics();

        // Check total size
        if ($metrics['total_size'] > 5 * 1024 * 1024) { // 5MB
            $recommendations[] = [
                'type' => 'size',
                'priority' => 'high',
                'message' => 'Total asset size is large. Consider compression and minification.',
                'current_size' => $this->formatBytes($metrics['total_size']),
            ];
        }

        // Check minification
        $totalFiles = array_sum($metrics['file_counts']);
        $minifiedFiles = $metrics['minification']['css_minified'] + $metrics['minification']['js_minified'];
        
        if ($minifiedFiles < $totalFiles * 0.8) {
            $recommendations[] = [
                'type' => 'minification',
                'priority' => 'medium',
                'message' => 'Consider minifying more assets for better performance.',
                'minified_ratio' => round(($minifiedFiles / $totalFiles) * 100, 2),
            ];
        }

        // Check image optimization
        if ($metrics['images_size'] > 2 * 1024 * 1024) { // 2MB
            $recommendations[] = [
                'type' => 'images',
                'priority' => 'medium',
                'message' => 'Consider optimizing images (WebP, compression).',
                'image_size' => $this->formatBytes($metrics['images_size']),
            ];
        }

        return $recommendations;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }

    /**
     * Get Vite configuration for production
     */
    public function getViteProductionConfig(): array
    {
        return [
            'build' => [
                'outDir' => 'public/build',
                'assetsDir' => 'assets',
                'sourcemap' => false,
                'minify' => 'terser',
                'terserOptions' => [
                    'compress' => [
                        'drop_console' => true,
                        'drop_debugger' => true,
                    ],
                ],
            ],
            'css' => [
                'postcss' => [
                    'plugins' => [
                        'autoprefixer',
                        'cssnano',
                    ],
                ],
            ],
            'server' => [
                'hmr' => false,
            ],
        ];
    }

    /**
     * Get CDN configuration
     */
    public function getCDNConfig(): array
    {
        return [
            'enabled' => env('CDN_ENABLED', false),
            'url' => env('CDN_URL'),
            'assets_url' => env('ASSET_URL'),
            'fallback' => true,
            'optimization' => [
                'auto_webp' => true,
                'quality' => 85,
                'progressive' => true,
            ],
        ];
    }

    /**
     * Generate asset versioning strategy
     */
    public function getVersioningStrategy(): array
    {
        return [
            'method' => 'hash', // hash, timestamp, or manual
            'include_hash' => true,
            'query_string' => true,
            'manifest' => true,
            'cache_busting' => true,
        ];
    }

    /**
     * Get critical CSS configuration
     */
    public function getCriticalCSSConfig(): array
    {
        return [
            'enabled' => true,
            'inline' => true,
            'minify' => true,
            'extract' => [
                'above_fold' => true,
                'viewport' => '1200x800',
            ],
            'fallback' => true,
        ];
    }

    /**
     * Get lazy loading configuration
     */
    public function getLazyLoadingConfig(): array
    {
        return [
            'images' => [
                'enabled' => true,
                'placeholder' => 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMSIgaGVpZ2h0PSIxIiB2aWV3Qm94PSIwIDAgMSAxIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxIiBoZWlnaHQ9IjEiIGZpbGw9IiNmM2Y0ZjYiLz48L3N2Zz4=',
                'threshold' => 0.1,
            ],
            'scripts' => [
                'enabled' => true,
                'defer' => true,
                'async' => false,
            ],
        ];
    }

    /**
     * Monitor asset loading performance
     */
    public function monitorAssetPerformance(): array
    {
        $monitoring = [];

        try {
            // This would typically be done via JavaScript in the browser
            // For now, return configuration for client-side monitoring
            $monitoring = [
                'enabled' => true,
                'metrics' => [
                    'load_time',
                    'first_paint',
                    'first_contentful_paint',
                    'largest_contentful_paint',
                    'cumulative_layout_shift',
                ],
                'thresholds' => [
                    'load_time' => 3000, // 3 seconds
                    'first_paint' => 1000, // 1 second
                    'first_contentful_paint' => 1500, // 1.5 seconds
                ],
            ];

        } catch (\Exception $e) {
            $monitoring = [
                'error' => $e->getMessage(),
                'status' => 'unhealthy',
            ];
        }

        return $monitoring;
    }

    /**
     * Get asset optimization checklist
     */
    public function getOptimizationChecklist(): array
    {
        return [
            'Compression' => [
                'Gzip compression enabled' => true,
                'Brotli compression enabled' => false,
                'Compression level optimized' => true,
            ],
            'Minification' => [
                'CSS minified' => true,
                'JavaScript minified' => true,
                'HTML minified' => true,
            ],
            'Bundling' => [
                'CSS bundled' => true,
                'JavaScript bundled' => true,
                'Vendor code separated' => true,
            ],
            'Caching' => [
                'Browser caching configured' => true,
                'CDN caching configured' => false,
                'Asset versioning enabled' => true,
            ],
            'Optimization' => [
                'Images optimized' => false,
                'Fonts optimized' => false,
                'Critical CSS inlined' => false,
                'Lazy loading implemented' => false,
            ],
        ];
    }
}
