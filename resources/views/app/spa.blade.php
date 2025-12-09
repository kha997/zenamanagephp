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
    
    <!-- CSS will be loaded from frontend-build manifest in body section -->
    
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
    <div id="root"></div>
    
    <!-- React app entry point - Load from frontend-build index.html -->
    @php
    // Load frontend SPA build from public/frontend-build/index.html
    $frontendIndexHtml = public_path('frontend-build/index.html');
    
    if (!file_exists($frontendIndexHtml)) {
        // Frontend build not found - show error or try dev server
        if (app()->environment('local')) {
            echo '<!-- Frontend build not found. Loading from Vite dev server... -->' . "\n";
            echo '<script type="module" src="http://localhost:5173/src/main.tsx"></script>' . "\n";
        } else {
            throw new \Exception('Missing frontend build. Run: cd frontend && npm run build');
        }
    } else {
        // Parse index.html to extract script, link, and preload tags
        $indexHtml = file_get_contents($frontendIndexHtml);
        preg_match_all('/<script[^>]+src="([^"]+)"[^>]*>/i', $indexHtml, $scriptMatches);
        preg_match_all('/<link[^>]+href="([^"]+)"[^>]*rel="stylesheet"[^>]*>/i', $indexHtml, $cssMatches);
        preg_match_all('/<link[^>]+href="([^"]+)"[^>]*rel="modulepreload"[^>]*>/i', $indexHtml, $preloadMatches);
        
        // Extract paths (remove leading / if present)
        $frontendScripts = !empty($scriptMatches[1]) ? array_map(function($path) {
            return ltrim($path, '/');
        }, $scriptMatches[1]) : [];
        $frontendCss = !empty($cssMatches[1]) ? array_map(function($path) {
            return ltrim($path, '/');
        }, $cssMatches[1]) : [];
        $frontendPreloads = !empty($preloadMatches[1]) ? array_map(function($path) {
            return ltrim($path, '/');
        }, $preloadMatches[1]) : [];
        
        if (empty($frontendScripts)) {
            throw new \Exception('No scripts found in frontend index.html. Run: cd frontend && npm run build');
        }
        
        // E2E Guard: In testing environment, verify bundle contains __e2e_logs instrumentation
        if (app()->environment('testing') && !empty($frontendScripts)) {
            $entryScriptSrc = $frontendScripts[0]; // First script is the entry point
            $entryPath = public_path('frontend-build/' . $entryScriptSrc);
            
            if (!is_file($entryPath)) {
                abort(500, 'Frontend bundle entry file not found: ' . $entryScriptSrc . '. Run `cd frontend && npm run build`.');
            }
            
            $entryContents = @file_get_contents($entryPath);
            if ($entryContents === false) {
                abort(500, 'Failed to read frontend bundle: ' . $entryScriptSrc . '. Run `cd frontend && npm run build`.');
            }
            
            if (!str_contains($entryContents, '__e2e_logs')) {
                abort(500, 'Frontend bundle seems stale or missing E2E instrumentation. Run `cd frontend && npm run build`.');
            }
        }
        
        // Load preloads first (skip external URLs)
        foreach ($frontendPreloads as $preload) {
            if (strpos($preload, 'http') === 0 || strpos($preload, '//') === 0) {
                continue;
            }
            echo '<link rel="modulepreload" href="' . asset('frontend-build/' . $preload) . '">' . "\n";
        }
        
        // Load CSS files (skip external URLs)
        foreach ($frontendCss as $css) {
            if (strpos($css, 'http') === 0 || strpos($css, '//') === 0) {
                continue;
            }
            echo '<link rel="stylesheet" href="' . asset('frontend-build/' . $css) . '">' . "\n";
        }
        
        // Load main JS entry (skip external URLs)
        foreach ($frontendScripts as $script) {
            if (strpos($script, 'http') === 0 || strpos($script, '//') === 0) {
                continue;
            }
            echo '<script type="module" src="' . asset('frontend-build/' . $script) . '"></script>' . "\n";
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

