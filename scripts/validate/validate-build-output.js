#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

/**
 * Validate build output - Check bundle sizes and required assets
 */
function validateBuildOutput() {
    console.log('🔍 Validating build output...\n');
    
    const manifestPath = path.join(process.cwd(), 'public/build/manifest.json');
    
    if (!fs.existsSync(manifestPath)) {
        console.error('❌ Build manifest not found!');
        console.error('   Run "npm run build" first');
        process.exit(1);
    }
    
    const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
    const errors = [];
    const warnings = [];
    
    // Check bundle sizes
    Object.entries(manifest).forEach(([entry, asset]) => {
        if (asset.file) {
            const filePath = path.join(process.cwd(), 'public/build', asset.file);
            if (fs.existsSync(filePath)) {
                const stats = fs.statSync(filePath);
                const sizeMB = stats.size / (1024 * 1024);
                
                if (sizeMB > 1) {
                    warnings.push({
                        file: asset.file,
                        size: `${sizeMB.toFixed(2)}MB`,
                        message: 'Bundle quá lớn (>1MB)'
                    });
                }
            } else {
                errors.push({
                    file: asset.file,
                    message: 'File không tồn tại trong build output'
                });
            }
        }
        
        // Check CSS files
        if (asset.css && Array.isArray(asset.css)) {
            asset.css.forEach(cssFile => {
                const cssPath = path.join(process.cwd(), 'public/build', cssFile);
                if (!fs.existsSync(cssPath)) {
                    errors.push({
                        file: cssFile,
                        message: 'CSS file không tồn tại'
                    });
                }
            });
        }
    });
    
    // Check required assets
    const requiredAssets = {
        'app.js': 'Main application bundle',
        'app.css': 'Main stylesheet'
    };
    
    Object.entries(requiredAssets).forEach(([assetName, description]) => {
        const found = Object.values(manifest).some(item => {
            if (item.file && item.file.includes(assetName)) {
                return true;
            }
            if (item.css && item.css.some(css => css.includes(assetName))) {
                return true;
            }
            return false;
        });
        
        if (!found) {
            errors.push({
                file: assetName,
                message: `Required asset "${description}" không có trong build!`
            });
        }
    });
    
    // Print results
    if (errors.length > 0) {
        console.error('🚨 BUILD VALIDATION ERRORS:\n');
        errors.forEach((err, idx) => {
            console.error(`${idx + 1}. ${err.file}`);
            console.error(`   ${err.message}\n`);
        });
    }
    
    if (warnings.length > 0) {
        console.warn('⚠️  BUILD WARNINGS:\n');
        warnings.forEach((warn, idx) => {
            console.warn(`${idx + 1}. ${warn.file} (${warn.size})`);
            console.warn(`   ${warn.message}\n`);
        });
    }
    
    if (errors.length > 0) {
        console.error('❌ Build output validation failed!\n');
        process.exit(1);
    }
    
    if (warnings.length > 0) {
        console.log('✅ Build output validation passed with warnings\n');
    } else {
        console.log('✅ Build output validation passed\n');
    }
}

// Run validation
validateBuildOutput();

