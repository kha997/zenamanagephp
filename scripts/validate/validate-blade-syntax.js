#!/usr/bin/env node

const fs = require('fs');
const { glob } = require('glob');

/**
 * Validate Blade syntax - Check for common issues
 */
function validateBladeSyntax() {
    console.log('🔍 Validating Blade syntax...\n');
    
    const bladeFiles = getAllBladeFiles();
    const errors = [];
    const warnings = [];
    
    bladeFiles.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        const lines = content.split('\n');
        
        // Check 1: Inline JavaScript với line breaks trong x-data
        lines.forEach((line, lineNum) => {
            if (line.includes('x-data="') && line.includes('{')) {
                // Check if x-data spans multiple lines (problematic)
                // But allow function calls with parameters on multiple lines
                const xDataMatch = content.match(/x-data="([^"]+)"/);
                if (xDataMatch && xDataMatch[1].includes('\n')) {
                    // Allow if it's a function call with parameters (not inline object)
                    const ref = xDataMatch[1].trim();
                    if (!ref.match(/^[a-zA-Z_][a-zA-Z0-9_]*\s*\(/)) {
                        // Only error if it's NOT a function call (i.e., inline object)
                        errors.push({
                            file,
                            line: lineNum + 1,
                            message: 'x-data attribute có line breaks - cần move vào function',
                            code: line.trim()
                        });
                    }
                }
            }
        });
        
        // Check 2: @json usage - ensure proper escaping
        const jsonMatches = content.matchAll(/@json\(([^)]+)\)/g);
        for (const match of jsonMatches) {
            const expr = match[1].trim();
            // Check if variable exists
            if (!expr.match(/^\$[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/)) {
                warnings.push({
                    file,
                    message: `@json(${expr}) - Complex expression, ensure proper escaping`,
                    code: match[0]
                });
            }
        }
        
        // Check 3: Alpine component registration vs usage
        if (content.includes('Alpine.data(') && content.includes('x-data="')) {
            // Check if components are properly registered before use
            const alpineDataMatches = content.matchAll(/Alpine\.data\(['"]([^'"]+)['"]/g);
            const xDataMatches = content.matchAll(/x-data="([^"]+)"/g);
            
            const registeredComponents = new Set();
            alpineDataMatches.forEach(match => registeredComponents.add(match[1]));
            
            xDataMatches.forEach(match => {
                const componentName = match[1].replace(/\(\)$/, '');
                if (!registeredComponents.has(componentName) && !componentName.match(/^\{/)) {
                    warnings.push({
                        file,
                        message: `Alpine component "${componentName}" được dùng nhưng có thể chưa được register`,
                        code: match[0]
                    });
                }
            });
        }
        
        // Check 4: Unescaped quotes in inline attributes
        if (content.match(/x-data="[^"]*['"][^"]*"/)) {
            warnings.push({
                file,
                message: 'Có thể có unescaped quotes trong x-data attribute',
                code: 'Check manually'
            });
        }
    });
    
    // Print results
    if (errors.length > 0) {
        console.error('🚨 BLADE SYNTAX ERRORS:\n');
        errors.forEach((err, idx) => {
            console.error(`${idx + 1}. ${err.file}:${err.line}`);
            console.error(`   ${err.message}`);
            console.error(`   Code: ${err.code}\n`);
        });
    }
    
    if (warnings.length > 0) {
        console.warn('⚠️  BLADE WARNINGS:\n');
        warnings.forEach((warn, idx) => {
            console.warn(`${idx + 1}. ${warn.file}`);
            console.warn(`   ${warn.message}`);
            console.warn(`   Code: ${warn.code}\n`);
        });
    }
    
    if (errors.length > 0) {
        console.error('❌ Blade syntax validation failed!\n');
        process.exit(1);
    }
    
    if (warnings.length > 0) {
        console.log('✅ Blade syntax validation passed with warnings\n');
    } else {
        console.log('✅ Blade syntax validation passed\n');
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
validateBladeSyntax();

