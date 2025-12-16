#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { glob } = require('glob');

/**
 * Detect unused files - Find files that are not referenced anywhere
 */
function detectUnusedFiles() {
    console.log('🔍 Detecting unused files...\n');
    
    const errors = [];
    const warnings = [];
    
    // 1. Check unused Blade components
    console.log('📄 Checking unused Blade components...');
    const bladeFiles = getAllBladeFiles();
    const unusedBladeFiles = checkUnusedBladeFiles(bladeFiles);
    if (unusedBladeFiles.length > 0) {
        warnings.push({
            type: 'unused-blade-files',
            count: unusedBladeFiles.length,
            items: unusedBladeFiles.slice(0, 10)
        });
    }
    
    // 2. Check unused JS/TS files
    console.log('📜 Checking unused JS/TS files...');
    const jsFiles = getAllJSFiles();
    const unusedJSFiles = checkUnusedJSFiles(jsFiles);
    if (unusedJSFiles.length > 0) {
        warnings.push({
            type: 'unused-js-files',
            count: unusedJSFiles.length,
            items: unusedJSFiles.slice(0, 10)
        });
    }
    
    // 3. Check unused CSS files
    console.log('🎨 Checking unused CSS files...');
    const cssFiles = getAllCSSFiles();
    const unusedCSSFiles = checkUnusedCSSFiles(cssFiles);
    if (unusedCSSFiles.length > 0) {
        warnings.push({
            type: 'unused-css-files',
            count: unusedCSSFiles.length,
            items: unusedCSSFiles.slice(0, 10)
        });
    }
    
    // Print results
    if (errors.length > 0) {
        console.error('🚨 CRITICAL ERRORS:\n');
        errors.forEach((err, idx) => {
            console.error(`${idx + 1}. ${err.message}\n`);
        });
    }
    
    if (warnings.length > 0) {
        console.warn('⚠️  WARNINGS:\n');
        warnings.forEach((warn, idx) => {
            console.warn(`${idx + 1}. ${warn.type}: ${warn.count} files found`);
            if (warn.items && warn.items.length > 0) {
                warn.items.forEach(item => {
                    console.warn(`   - ${item}`);
                });
            }
            if (warn.count > 10) {
                console.warn(`   ... and ${warn.count - 10} more\n`);
            } else {
                console.warn('');
            }
        });
    }
    
    const isCI = process.env.CI === 'true' || process.env.GITHUB_ACTIONS === 'true';
    
    if (errors.length > 0) {
        console.error('❌ Unused files detection failed!\n');
        process.exit(1);
    }
    
    if (warnings.length > 0) {
        if (isCI) {
            console.warn('⚠️  Unused files detected in CI mode\n');
            console.warn('   Review and remove unused files\n');
            // Don't fail in CI, just warn
        } else {
            console.log('✅ Unused files detection completed with warnings\n');
        }
    } else {
        console.log('✅ No unused files detected\n');
    }
}

function getAllBladeFiles() {
    try {
        return glob.sync('resources/views/**/*.blade.php', {
            ignore: ['node_modules/**', 'vendor/**']
        });
    } catch (error) {
        console.error('Error finding Blade files:', error);
        return [];
    }
}

function getAllJSFiles() {
    try {
        return glob.sync('resources/js/**/*.{js,ts,jsx,tsx}', {
            ignore: ['node_modules/**', 'vendor/**', '**/*.test.js', '**/*.spec.js']
        });
    } catch (error) {
        console.error('Error finding JS files:', error);
        return [];
    }
}

function getAllCSSFiles() {
    try {
        return glob.sync('resources/css/**/*.css', {
            ignore: ['node_modules/**', 'vendor/**']
        });
    } catch (error) {
        console.error('Error finding CSS files:', error);
        return [];
    }
}

function checkUnusedBladeFiles(files) {
    const unusedFiles = [];
    const allFiles = getAllBladeFiles();
    const allJSFiles = getAllJSFiles();
    
    // Collect all references
    const references = new Set();
    allFiles.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        // Match @include, @extends, @component, <x-component>
        const includeMatches = content.matchAll(/@(include|extends|component)\s*\(['"]([^'"]+)['"]/g);
        const componentMatches = content.matchAll(/<x-([\w\.-]+)/g);
        
        for (const match of includeMatches) {
            references.add(match[2]);
        }
        for (const match of componentMatches) {
            references.add(match[1].replace(/\./g, '/'));
        }
    });
    
    // Check JS files for Blade references
    allJSFiles.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        const viewMatches = content.matchAll(/view\(['"]([^'"]+)['"]/g);
        for (const match of viewMatches) {
            references.add(match[1]);
        }
    });
    
    // Find unused files
    files.forEach(file => {
        const relativePath = file.replace('resources/views/', '').replace('.blade.php', '');
        const normalizedPath = relativePath.replace(/\./g, '/');
        
        if (!references.has(relativePath) && 
            !references.has(normalizedPath) &&
            !file.includes('layouts/') && // Keep layouts
            !file.includes('components/') && // Keep components
            !file.includes('_demos/') && // Keep demos
            !file.includes('_legacy/') && // Keep legacy for now
            !file.includes('_future/')) { // Keep future
            unusedFiles.push(file);
        }
    });
    
    return unusedFiles;
}

function checkUnusedJSFiles(files) {
    const unusedFiles = [];
    const allFiles = [...getAllJSFiles(), ...getAllBladeFiles()];
    
    // Collect all imports/references
    const references = new Set();
    allFiles.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        // Match import statements
        const importMatches = content.matchAll(/import\s+.*from\s+['"]([^'"]+)['"]/g);
        const requireMatches = content.matchAll(/require\(['"]([^'"]+)['"]\)/g);
        
        for (const match of importMatches) {
            let importPath = match[1];
            if (!importPath.startsWith('.')) {
                continue; // Skip node_modules imports
            }
            references.add(importPath);
        }
        for (const match of requireMatches) {
            let requirePath = match[1];
            if (!requirePath.startsWith('.')) {
                continue;
            }
            references.add(requirePath);
        }
    });
    
    // Check entry points
    const entryPoints = [
        'resources/js/app.js',
        'resources/js/bootstrap.js',
        'resources/js/task-comments.js'
    ];
    
    files.forEach(file => {
        const relativePath = './' + file.replace('resources/', '');
        const normalizedPath = relativePath.replace(/\.js$/, '');
        
        const isEntryPoint = entryPoints.some(ep => file === ep);
        const isReferenced = references.has(relativePath) || 
                            references.has(normalizedPath) ||
                            references.has(relativePath + '.js') ||
                            references.has(normalizedPath + '.js');
        
        if (!isEntryPoint && !isReferenced && 
            !file.includes('.test.') && 
            !file.includes('.spec.')) {
            unusedFiles.push(file);
        }
    });
    
    return unusedFiles;
}

function checkUnusedCSSFiles(files) {
    const unusedFiles = [];
    const allFiles = [...getAllBladeFiles(), ...getAllJSFiles()];
    
    // Collect all CSS references
    const references = new Set();
    allFiles.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        // Match @vite, link rel="stylesheet", import css
        const viteMatches = content.matchAll(/@vite\(\[['"]([^'"]+\.css)['"]/g);
        const linkMatches = content.matchAll(/<link[^>]+href=['"]([^'"]+\.css)['"]/g);
        const importMatches = content.matchAll(/import\s+['"]([^'"]+\.css)['"]/g);
        
        for (const match of viteMatches) {
            references.add(match[1]);
        }
        for (const match of linkMatches) {
            references.add(match[1]);
        }
        for (const match of importMatches) {
            references.add(match[1]);
        }
    });
    
    files.forEach(file => {
        const relativePath = file.replace('resources/', '');
        const normalizedPath = '/' + relativePath;
        
        if (!references.has(relativePath) && 
            !references.has(normalizedPath) &&
            !file.includes('app.css')) { // Keep main CSS
            unusedFiles.push(file);
        }
    });
    
    return unusedFiles;
}

// Run detection
detectUnusedFiles();

