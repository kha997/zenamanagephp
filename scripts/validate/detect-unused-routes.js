#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { glob } = require('glob');

/**
 * Detect unused routes - Find routes that are not referenced in code
 */
function detectUnusedRoutes() {
    console.log('🔍 Detecting unused routes...\n');
    
    const warnings = [];
    
    // Parse routes from route files
    console.log('📋 Parsing routes...');
    const routes = parseRoutes();
    console.log(`   Found ${routes.length} routes\n`);
    
    // Check route usage
    console.log('🔎 Checking route usage...');
    const unusedRoutes = checkRouteUsage(routes);
    
    if (unusedRoutes.length > 0) {
        warnings.push({
            type: 'unused-routes',
            count: unusedRoutes.length,
            items: unusedRoutes.slice(0, 20)
        });
    }
    
    // Print results
    if (warnings.length > 0) {
        console.warn('⚠️  WARNINGS:\n');
        warnings.forEach((warn, idx) => {
            console.warn(`${idx + 1}. ${warn.type}: ${warn.count} routes found`);
            if (warn.items && warn.items.length > 0) {
                warn.items.forEach(item => {
                    console.warn(`   - ${item.method} ${item.path} (${item.file})`);
                });
            }
            if (warn.count > 20) {
                console.warn(`   ... and ${warn.count - 20} more\n`);
            } else {
                console.warn('');
            }
        });
    }
    
    const isCI = process.env.CI === 'true' || process.env.GITHUB_ACTIONS === 'true';
    
    if (warnings.length > 0) {
        if (isCI) {
            console.warn('⚠️  Unused routes detected in CI mode\n');
            console.warn('   Review routes - some may be API endpoints used externally\n');
            // Don't fail, just warn
        } else {
            console.log('✅ Route detection completed with warnings\n');
        }
    } else {
        console.log('✅ No unused routes detected\n');
    }
}

function parseRoutes() {
    const routes = [];
    const routeFiles = [
        'routes/web.php',
        'routes/api.php',
        'routes/app.php'
    ];
    
    routeFiles.forEach(routeFile => {
        if (!fs.existsSync(routeFile)) {
            return;
        }
        
        const content = fs.readFileSync(routeFile, 'utf8');
        
        // Match Route::method('path', ...)
        const routeMatches = content.matchAll(/Route::(get|post|put|patch|delete|options|any|match)\s*\(\s*['"]([^'"]+)['"]/g);
        
        for (const match of routeMatches) {
            const method = match[1].toUpperCase();
            const path = match[2];
            
            routes.push({
                method,
                path,
                file: routeFile,
                fullPath: `${method} ${path}`
            });
        }
        
        // Match Route::prefix(...)->group(...)
        const prefixMatches = content.matchAll(/Route::prefix\(['"]([^'"]+)['"]\)/g);
        prefixMatches.forEach(prefixMatch => {
            const prefix = prefixMatch[1];
            // Note: This is a simplified check, may need refinement
        });
    });
    
    return routes;
}

function checkRouteUsage(routes) {
    const unusedRoutes = [];
    const allFiles = [
        ...glob.sync('resources/views/**/*.blade.php', { ignore: ['node_modules/**', 'vendor/**'] }),
        ...glob.sync('resources/js/**/*.{js,ts,jsx,tsx}', { ignore: ['node_modules/**', 'vendor/**'] }),
        ...glob.sync('app/**/*.php', { ignore: ['node_modules/**', 'vendor/**'] })
    ];
    
    routes.forEach(route => {
        let found = false;
        
        // Check for route name usage
        const routeName = route.path.replace(/\//g, '.').replace(/\{.*?\}/g, '*');
        
        allFiles.forEach(file => {
            const content = fs.readFileSync(file, 'utf8');
            
            // Check various patterns
            if (content.includes(route.path) ||
                content.includes(`route('${routeName}')`) ||
                content.includes(`route("${routeName}")`) ||
                content.includes(`url('${route.path}')`) ||
                content.includes(`url("${route.path}")`) ||
                content.includes(`href="${route.path}"`) ||
                content.includes(`href='${route.path}'`) ||
                content.includes(`action="${route.path}"`) ||
                content.includes(`action='${route.path}'`) ||
                content.includes(`/${route.path}`)) {
                found = true;
            }
        });
        
        // API routes might be used externally, so be lenient
        if (!found && !route.path.startsWith('/api/')) {
            unusedRoutes.push(route);
        }
    });
    
    return unusedRoutes;
}

// Run detection
detectUnusedRoutes();

