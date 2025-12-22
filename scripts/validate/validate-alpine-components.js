#!/usr/bin/env node

const fs = require('fs');
const { glob } = require('glob');

/**
 * Validate Alpine.js components - Check if all referenced components are registered
 */
function validateAlpineComponents() {
    console.log('🔍 Validating Alpine.js components...\n');
    
    const bladeFiles = getAllBladeFiles();
    const jsFiles = getAllJSFiles();
    const errors = [];
    const warnings = [];
    
    // Extract all x-data references from Blade files
    const xDataRefs = new Map();
    bladeFiles.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        // Match x-data attributes, handling multi-line with regex flags
        const xDataPattern = /x-data\s*=\s*"([^"]+)"/g;
        let match;
        while ((match = xDataPattern.exec(content)) !== null) {
            const ref = match[1].replace(/\s+/g, ' ').trim(); // Normalize whitespace
            if (!xDataRefs.has(ref)) {
                xDataRefs.set(ref, []);
            }
            xDataRefs.get(ref).push(file);
        }
    });
    
    // Check if all x-data refs are registered in JS files
    xDataRefs.forEach((files, ref) => {
        // Skip inline objects
        if (ref.trim().startsWith('{')) {
            return;
        }
        
        // Extract component name (remove parameters)
        const componentMatch = ref.match(/^([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/);
        const componentName = componentMatch ? componentMatch[1] : ref.replace(/\(\)$/, '');
        
        // Skip if component name is empty
        if (!componentName || componentName === '') {
            return;
        }
        
        // Check in JS files
        let found = false;
        jsFiles.forEach(jsFile => {
            const content = fs.readFileSync(jsFile, 'utf8');
            if (content.includes(`Alpine.data('${componentName}'`) || 
                content.includes(`Alpine.data("${componentName}"`)) {
                found = true;
            }
        });
        
        // Check in Blade files (inline registration)
        if (!found) {
            bladeFiles.forEach(bladeFile => {
                const content = fs.readFileSync(bladeFile, 'utf8');
                if (content.includes(`Alpine.data('${componentName}'`) || 
                    content.includes(`Alpine.data("${componentName}"`)) {
                    found = true;
                }
            });
        }
        
        if (!found && componentName !== '') {
            errors.push({
                component: componentName,
                files: files,
                message: `Alpine component "${componentName}" được reference nhưng chưa được register`
            });
        }
    });
    
    // Check for duplicate registrations
    const registeredComponents = new Map();
    jsFiles.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        const matches = content.matchAll(/Alpine\.data\(['"]([^'"]+)['"]/g);
        for (const match of matches) {
            const name = match[1];
            if (!registeredComponents.has(name)) {
                registeredComponents.set(name, []);
            }
            registeredComponents.get(name).push(file);
        }
    });
    
    registeredComponents.forEach((files, name) => {
        if (files.length > 1) {
            warnings.push({
                component: name,
                files: files,
                message: `Alpine component "${name}" được register nhiều lần`
            });
        }
    });
    
    // Print results
    if (errors.length > 0) {
        console.error('🚨 ALPINE COMPONENT ERRORS:\n');
        errors.forEach((err, idx) => {
            console.error(`${idx + 1}. ${err.message}`);
            console.error(`   Component: ${err.component}`);
            console.error(`   Used in: ${err.files.join(', ')}\n`);
        });
    }
    
    if (warnings.length > 0) {
        console.warn('⚠️  ALPINE WARNINGS:\n');
        warnings.forEach((warn, idx) => {
            console.warn(`${idx + 1}. ${warn.message}`);
            console.warn(`   Registered in: ${warn.files.join(', ')}\n`);
        });
    }
    
    if (errors.length > 0) {
        console.error('❌ Alpine component validation failed!\n');
        process.exit(1);
    }
    
    if (warnings.length > 0) {
        console.log('✅ Alpine component validation passed with warnings\n');
    } else {
        console.log('✅ Alpine component validation passed\n');
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
        return glob.sync('resources/js/**/*.{js,ts}', {
            ignore: ['node_modules/**', 'vendor/**']
        });
    } catch (error) {
        console.error('Error finding JS files:', error);
        return [];
    }
}

// Run validation
validateAlpineComponents();

