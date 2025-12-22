#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { glob } = require('glob');

/**
 * Validate orphaned code - Check for unused imports, functions, classes
 * This script prevents orphaned code from being committed
 */
function validateOrphanedCode() {
    console.log('🔍 Validating orphaned code...\n');
    
    const errors = [];
    const warnings = [];
    
    // 1. Check unused imports in JavaScript/TypeScript files
    console.log('📦 Checking unused imports...');
    const jsFiles = getAllJSFiles();
    const unusedImports = checkUnusedImports(jsFiles);
    if (unusedImports.length > 0) {
        warnings.push({
            type: 'unused-imports',
            count: unusedImports.length,
            items: unusedImports.slice(0, 10) // Show first 10
        });
    }
    
    // 2. Check unused functions in JavaScript files
    console.log('🔧 Checking unused functions...');
    const unusedFunctions = checkUnusedFunctions(jsFiles);
    if (unusedFunctions.length > 0) {
        warnings.push({
            type: 'unused-functions',
            count: unusedFunctions.length,
            items: unusedFunctions.slice(0, 10)
        });
    }
    
    // 3. Check unused classes in JavaScript files
    console.log('🏷️  Checking unused classes...');
    const unusedClasses = checkUnusedClasses(jsFiles);
    if (unusedClasses.length > 0) {
        warnings.push({
            type: 'unused-classes',
            count: unusedClasses.length,
            items: unusedClasses.slice(0, 10)
        });
    }
    
    // Print results
    if (errors.length > 0) {
        console.error('🚨 CRITICAL ERRORS:\n');
        errors.forEach((err, idx) => {
            console.error(`${idx + 1}. ${err.message}`);
            if (err.details) {
                console.error(`   ${err.details}`);
            }
            console.error('');
        });
    }
    
    if (warnings.length > 0) {
        console.warn('⚠️  WARNINGS:\n');
        warnings.forEach((warn, idx) => {
            console.warn(`${idx + 1}. ${warn.type}: ${warn.count} items found`);
            if (warn.items && warn.items.length > 0) {
                warn.items.forEach(item => {
                    console.warn(`   - ${item.file}${item.line ? `:${item.line}` : ''} - ${item.name || item.import || ''}`);
                });
            }
            if (warn.count > 10) {
                console.warn(`   ... and ${warn.count - 10} more\n`);
            } else {
                console.warn('');
            }
        });
    }
    
    // In CI/CD, fail on warnings (strict mode)
    const isCI = process.env.CI === 'true' || process.env.GITHUB_ACTIONS === 'true';
    
    if (errors.length > 0) {
        console.error('❌ Orphaned code validation failed!\n');
        process.exit(1);
    }
    
    if (warnings.length > 0) {
        if (isCI) {
            console.error('❌ Orphaned code validation failed in CI mode!\n');
            console.error('   Fix warnings before committing.\n');
            process.exit(1);
        } else {
            console.log('✅ Orphaned code validation passed with warnings\n');
            console.log('   Run with CI=true to fail on warnings\n');
        }
    } else {
        console.log('✅ Orphaned code validation passed\n');
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

function checkUnusedImports(files) {
    const unusedImports = [];
    
    files.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        const lines = content.split('\n');
        
        lines.forEach((line, lineNum) => {
            // Match ES6 imports
            const importMatch = line.match(/^import\s+(?:(\w+)\s+from|\{([^}]+)\}\s+from|(\*)\s+as\s+(\w+)\s+from)\s+['"]([^'"]+)['"]/);
            if (importMatch) {
                const defaultImport = importMatch[1];
                const namedImports = importMatch[2];
                const namespaceImport = importMatch[4];
                
                // Get remaining content after this line
                const remainingContent = content.substring(content.indexOf(line) + line.length);
                
                // Check if imports are used
                if (defaultImport && !remainingContent.includes(defaultImport)) {
                    unusedImports.push({
                        file,
                        line: lineNum + 1,
                        import: defaultImport,
                        type: 'default'
                    });
                }
                
                if (namedImports) {
                    const imports = namedImports.split(',').map(i => i.trim().split(/\s+as\s+/)[0].trim());
                    imports.forEach(imp => {
                        if (imp && !remainingContent.includes(imp)) {
                            unusedImports.push({
                                file,
                                line: lineNum + 1,
                                import: imp,
                                type: 'named'
                            });
                        }
                    });
                }
                
                if (namespaceImport && !remainingContent.includes(namespaceImport)) {
                    unusedImports.push({
                        file,
                        line: lineNum + 1,
                        import: namespaceImport,
                        type: 'namespace'
                    });
                }
            }
        });
    });
    
    return unusedImports;
}

function checkUnusedFunctions(files) {
    const unusedFunctions = [];
    const functionMap = new Map(); // file -> functions
    const usageMap = new Map(); // function -> files using it
    
    // First pass: collect all functions
    files.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        const functionMatches = content.matchAll(/(?:export\s+)?(?:async\s+)?function\s+(\w+)|const\s+(\w+)\s*=\s*(?:async\s+)?\(/g);
        
        const functions = [];
        for (const match of functionMatches) {
            const funcName = match[1] || match[2];
            if (funcName && !funcName.startsWith('_')) { // Skip private functions
                functions.push(funcName);
            }
        }
        functionMap.set(file, functions);
    });
    
    // Second pass: check usage
    files.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        functionMap.forEach((functions, funcFile) => {
            if (funcFile === file) return; // Skip self-reference
            
            functions.forEach(funcName => {
                if (content.includes(funcName)) {
                    if (!usageMap.has(funcName)) {
                        usageMap.set(funcName, []);
                    }
                    usageMap.get(funcName).push(file);
                }
            });
        });
    });
    
    // Find unused functions
    functionMap.forEach((functions, file) => {
        functions.forEach(funcName => {
            if (!usageMap.has(funcName)) {
                // Check if it's exported (might be used externally)
                const content = fs.readFileSync(file, 'utf8');
                const isExported = content.includes(`export ${funcName}`) || 
                                   content.includes(`export function ${funcName}`) ||
                                   content.includes(`export const ${funcName}`);
                
                if (!isExported) {
                    unusedFunctions.push({
                        file,
                        name: funcName,
                        type: 'function'
                    });
                }
            }
        });
    });
    
    return unusedFunctions;
}

function checkUnusedClasses(files) {
    const unusedClasses = [];
    const classMap = new Map();
    const usageMap = new Map();
    
    // Collect all classes
    files.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        const classMatches = content.matchAll(/(?:export\s+)?class\s+(\w+)/g);
        
        const classes = [];
        for (const match of classMatches) {
            classes.push(match[1]);
        }
        classMap.set(file, classes);
    });
    
    // Check usage
    files.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        classMap.forEach((classes, classFile) => {
            classes.forEach(className => {
                if (content.includes(`new ${className}`) || 
                    content.includes(`${className}.`) ||
                    content.includes(`extends ${className}`)) {
                    if (!usageMap.has(className)) {
                        usageMap.set(className, []);
                    }
                    usageMap.get(className).push(file);
                }
            });
        });
    });
    
    // Find unused classes
    classMap.forEach((classes, file) => {
        classes.forEach(className => {
            if (!usageMap.has(className)) {
                const content = fs.readFileSync(file, 'utf8');
                const isExported = content.includes(`export class ${className}`);
                
                if (!isExported) {
                    unusedClasses.push({
                        file,
                        name: className,
                        type: 'class'
                    });
                }
            }
        });
    });
    
    return unusedClasses;
}

// Run validation
validateOrphanedCode();

