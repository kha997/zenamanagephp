<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="tenant-id" content="{{ auth()->user()->tenant_id ?? '' }}">
    <meta name="app-url" content="{{ config('app.url') }}">
    <meta name="api-base-url" content="{{ url('/api/v1') }}">
    
    <title>{{ config('app.name', 'ZenaManage') }}</title>
    
    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Laravel Vite assets -->
    @php
    // Load CSS from built manifest
    // Laravel Vite will automatically use manifest.json if available
    $rootManifestPath = public_path('build/manifest.json');
    if (file_exists($rootManifestPath)) {
        $manifest = json_decode(file_get_contents($rootManifestPath), true);
        if (isset($manifest['resources/css/app.css']['file'])) {
            echo '<link rel="stylesheet" href="' . asset('build/' . $manifest['resources/css/app.css']['file']) . '">';
        }
    } else {
        // Fallback: try Laravel Vite directive (requires Vite dev server)
        try {
            @vite(['resources/css/app.css']);
        } catch (\Exception $e) {
            // Silent fail - CSS will be missing but page won't crash
        }
    }
    @endphp
    
    <!-- Set document lang attribute based on current locale -->
    <script>
        document.documentElement.lang = '{{ app()->getLocale() }}';
    </script>
    
    <!-- Initialize Laravel data for React -->
    <script>
        window.Laravel = {
            csrfToken: '{{ csrf_token() }}',
            locale: '{{ app()->getLocale() }}',
            user: @json(auth()->user()),
            tenant: {
                id: '{{ auth()->user()->tenant_id ?? '' }}',
            },
            permissions: @json(auth()->user()->permissions ?? []),
            appUrl: '{{ config('app.url') }}',
            apiBaseUrl: '{{ url('/api/v1') }}',
        };
    </script>
</head>
<body>
    <!-- React SPA root -->
    <div id="app"></div>
    
    <!-- React app entry point -->
    @php
    // Try to load from built manifest first (works in both dev and production)
    // Fallback to Vite dev server only if manifest not found and in local env
    $manifestPaths = [
        public_path('build/.vite/manifest.json'),
        public_path('build/manifest.json'),
    ];
    
    $manifest = null;
    $manifestPath = null;
    
    foreach ($manifestPaths as $path) {
        if (file_exists($path)) {
            $manifest = json_decode(file_get_contents($path), true);
            $manifestPath = $path;
            break;
        }
    }
    
    if ($manifest) {
        // Found manifest - load from built assets
        // Try different possible keys in manifest
        $entryKeys = [
            'frontend/src/main',
            'src/main.tsx',
            'src/main',
            'frontend/src/main.tsx',
            'main.tsx',
        ];
        
        $entry = null;
        $entryKey = null;
        
        foreach ($entryKeys as $key) {
            if (isset($manifest[$key])) {
                $entry = $manifest[$key];
                $entryKey = $key;
                break;
            }
        }
        
        // If not found by key, find first entry
        if (!$entry) {
            foreach ($manifest as $key => $value) {
                if (isset($value['isEntry']) && $value['isEntry']) {
                    $entry = $value;
                    $entryKey = $key;
                    break;
                }
            }
        }
        
        if ($entry && isset($entry['file'])) {
            // Load JS entry
            echo '<script type="module" src="' . asset('build/' . $entry['file']) . '"></script>' . "\n";
            
            // Load CSS files
            if (isset($entry['css']) && is_array($entry['css'])) {
                foreach ($entry['css'] as $css) {
                    echo '<link rel="stylesheet" href="' . asset('build/' . $css) . '">' . "\n";
                }
            }
            
            // Load imported chunks (if any) - only preload JS files, not CSS
            if (isset($entry['imports']) && is_array($entry['imports'])) {
                foreach ($entry['imports'] as $import) {
                    if (isset($manifest[$import]['file'])) {
                        $importFile = $manifest[$import]['file'];
                        // Only preload JS files, skip CSS files
                        if (strpos($importFile, '.js') !== false || strpos($importFile, '.mjs') !== false) {
                            echo '<link rel="modulepreload" href="' . asset('build/' . $importFile) . '">' . "\n";
                        }
                    }
                }
            }
        } else {
            // Manifest found but no entry point - try dev server if in local env
            if (app()->environment('local')) {
                echo '<!-- Manifest found but no entry point. Trying Vite dev server... -->' . "\n";
                echo '<script type="module" src="http://localhost:5173/src/main.tsx"></script>' . "\n";
            } else {
                echo '<!-- Manifest found but no entry point. Run: cd frontend && npm run build -->' . "\n";
            }
        }
    } else {
        // No manifest found
        if (app()->environment('local')) {
            // Development: try Vite dev server
            echo '<!-- No manifest found. Loading from Vite dev server... -->' . "\n";
            echo '<script type="module" src="http://localhost:5173/src/main.tsx"></script>' . "\n";
        } else {
            // Production: error
            echo '<!-- Manifest not found. Run: cd frontend && npm run build -->' . "\n";
            echo '<script type="module" src="' . asset('build/assets/js/main.js') . '"></script>' . "\n";
        }
    }
    @endphp
    
    <!-- Fallback for no-JS -->
    <noscript>
        <div style="padding: 2rem; text-align: center;">
            <h1>JavaScript Required</h1>
            <p>Please enable JavaScript to use this application.</p>
        </div>
    </noscript>
</body>
</html>

