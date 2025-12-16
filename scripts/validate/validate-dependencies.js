#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { glob } = require('glob');

/**
 * Validate frontend dependencies - Check for CDN/npm conflicts
 */
function validateDependencies() {
    console.log('🔍 Validating dependencies...\n');
    
    const packageJsonPath = path.join(process.cwd(), 'package.json');
    if (!fs.existsSync(packageJsonPath)) {
        console.error('❌ package.json not found!');
        process.exit(1);
    }
    
    const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));
    const bladeFiles = getAllBladeFiles();
    
    const errors = [];
    const warnings = [];
    
    // Check 1: Alpine.js không được mix CDN + npm
    const hasAlpineCDN = bladeFiles.some(file => {
        const content = fs.readFileSync(file, 'utf8');
        return content.includes('alpinejs') || 
               content.includes('cdn.min.js') ||
               content.includes('alpine.js');
    });
    const hasAlpineNPM = packageJson.dependencies?.alpinejs || packageJson.devDependencies?.alpinejs;
    
    if (hasAlpineCDN && hasAlpineNPM) {
        errors.push({
            type: 'CRITICAL',
            message: 'Alpine.js được load từ cả CDN và npm package!',
            fix: 'Chọn MỘT trong hai:\n  - Option A: Xóa alpinejs từ package.json và chỉ dùng CDN\n  - Option B: Xóa CDN script và chỉ dùng npm package'
        });
    }
    
    // Check 2: Chart.js consistency
    const hasChartNPM = packageJson.dependencies?.['chart.js'] || packageJson.devDependencies?.['chart.js'];
    const hasChartCDN = bladeFiles.some(file => {
        const content = fs.readFileSync(file, 'utf8');
        return content.includes('chart.js') && content.includes('cdn');
    });
    
    if (hasChartCDN && hasChartNPM) {
        warnings.push({
            type: 'WARNING',
            message: 'Chart.js được load từ cả CDN và npm',
            fix: 'Nên chọn một cách để tránh conflict'
        });
    }
    
    // Check 3: Axios consistency
    const hasAxiosNPM = packageJson.dependencies?.axios || packageJson.devDependencies?.axios;
    const hasAxiosCDN = bladeFiles.some(file => {
        const content = fs.readFileSync(file, 'utf8');
        return content.includes('axios') && content.includes('cdn');
    });
    
    if (hasAxiosCDN && hasAxiosNPM) {
        warnings.push({
            type: 'WARNING',
            message: 'Axios được load từ cả CDN và npm',
            fix: 'Nên chọn một cách để tránh conflict'
        });
    }
    
    // Check 4: Duplicate script tags
    bladeFiles.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        const alpineMatches = content.match(/alpinejs[^"']*/gi);
        if (alpineMatches && alpineMatches.length > 1) {
            errors.push({
                type: 'CRITICAL',
                message: `File ${file} có nhiều Alpine.js script tags`,
                fix: 'Chỉ giữ lại MỘT script tag cho Alpine.js'
            });
        }
    });
    
    // Print results
    if (errors.length > 0) {
        console.error('🚨 CRITICAL ERRORS:\n');
        errors.forEach((err, idx) => {
            console.error(`${idx + 1}. ${err.message}`);
            console.error(`   Fix: ${err.fix}\n`);
        });
    }
    
    if (warnings.length > 0) {
        console.warn('⚠️  WARNINGS:\n');
        warnings.forEach((warn, idx) => {
            console.warn(`${idx + 1}. ${warn.message}`);
            console.warn(`   Fix: ${warn.fix}\n`);
        });
    }
    
    if (errors.length > 0) {
        console.error('❌ Dependency validation failed!\n');
        process.exit(1);
    }
    
    if (warnings.length > 0) {
        console.log('✅ Dependency validation passed with warnings\n');
    } else {
        console.log('✅ Dependency validation passed\n');
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

// Run validation
validateDependencies();

