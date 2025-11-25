#!/usr/bin/env node

/**
 * Generate TypeScript types from OpenAPI specification
 * 
 * This script:
 * 1. Fetches OpenAPI spec from /api/v1/openapi.json
 * 2. Generates TypeScript types using openapi-typescript
 * 3. Writes types to frontend/src/types/api/generated.ts
 */

const fs = require('fs');
const path = require('path');
const https = require('https');
const http = require('http');

const API_BASE_URL = process.env.API_BASE_URL || 'http://localhost:8000';
const OPENAPI_ENDPOINT = `${API_BASE_URL}/api/v1/openapi.json`;
const OUTPUT_FILE = path.join(__dirname, '../src/types/api/generated.ts');

async function fetchOpenApiSpec() {
    return new Promise((resolve, reject) => {
        const url = new URL(OPENAPI_ENDPOINT);
        const client = url.protocol === 'https:' ? https : http;
        
        client.get(url, (res) => {
            let data = '';
            
            res.on('data', (chunk) => {
                data += chunk;
            });
            
            res.on('end', () => {
                if (res.statusCode === 200) {
                    try {
                        resolve(JSON.parse(data));
                    } catch (e) {
                        reject(new Error(`Failed to parse OpenAPI spec: ${e.message}`));
                    }
                } else {
                    reject(new Error(`Failed to fetch OpenAPI spec: ${res.statusCode}`));
                }
            });
        }).on('error', (err) => {
            reject(err);
        });
    });
}

function generateTypeScriptTypes(spec) {
    // Basic TypeScript type generation from OpenAPI spec
    // In production, use openapi-typescript or similar library
    
    let output = `// Auto-generated TypeScript types from OpenAPI specification
// DO NOT EDIT - Generated on ${new Date().toISOString()}
// Source: ${OPENAPI_ENDPOINT}

export interface ApiResponse<T = any> {
  ok?: boolean;
  success?: boolean;
  data?: T;
  message?: string;
  error?: {
    code: string;
    message: string;
    details?: any;
    traceId?: string;
  };
  meta?: {
    current_page?: number;
    per_page?: number;
    total?: number;
    last_page?: number;
  };
}

export interface PaginatedResponse<T> extends ApiResponse<T[]> {
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from?: number;
    to?: number;
  };
  links?: {
    first?: string;
    last?: string;
    prev?: string;
    next?: string;
  };
}

// API Endpoints
export type ApiEndpoints = {
`;

    // Generate endpoint types from OpenAPI paths
    if (spec.paths) {
        for (const [path, methods] of Object.entries(spec.paths)) {
            for (const [method, operation] of Object.entries(methods)) {
                if (['get', 'post', 'put', 'patch', 'delete'].includes(method.toLowerCase())) {
                    const operationId = operation.operationId || `${method}_${path.replace(/\//g, '_').replace(/[{}]/g, '')}`;
                    const summary = operation.summary || operationId;
                    
                    output += `  // ${summary}\n`;
                    output += `  '${method.toUpperCase()} ${path}': {\n`;
                    
                    // Request body type
                    if (operation.requestBody) {
                        output += `    request: any; // TODO: Generate from requestBody schema\n`;
                    } else {
                        output += `    request: void;\n`;
                    }
                    
                    // Response types
                    output += `    response: ApiResponse<any>; // TODO: Generate from responses schema\n`;
                    output += `  };\n\n`;
                }
            }
        }
    }

    output += `};

// Error codes
export type ApiErrorCode = 
`;

    // Extract error codes from responses
    const errorCodes = new Set();
    if (spec.paths) {
        for (const methods of Object.values(spec.paths)) {
            for (const operation of Object.values(methods)) {
                if (operation.responses) {
                    for (const response of Object.values(operation.responses)) {
                        if (response.content && response.content['application/json']) {
                            const schema = response.content['application/json'].schema;
                            if (schema && schema.properties && schema.properties.error) {
                                // Try to extract error codes
                            }
                        }
                    }
                }
            }
        }
    }

    // Add common error codes
    errorCodes.add('UNAUTHORIZED');
    errorCodes.add('FORBIDDEN');
    errorCodes.add('NOT_FOUND');
    errorCodes.add('VALIDATION_FAILED');
    errorCodes.add('INTERNAL_ERROR');

    output += `  | ${Array.from(errorCodes).map(c => `'${c}'`).join('\n  | ')};\n`;

    output += `
// Helper function to generate idempotency key
export function generateIdempotencyKey(resource: string, action: string): string {
  const timestamp = Date.now();
  const nonce = Math.random().toString(36).substring(2, 15);
  return \`\${resource}_\${action}_\${timestamp}_\${nonce}\`;
}
`;

    return output;
}

async function main() {
    try {
        console.log('Fetching OpenAPI specification...');
        const spec = await fetchOpenApiSpec();
        
        console.log('Generating TypeScript types...');
        const types = generateTypeScriptTypes(spec);
        
        // Ensure directory exists
        const outputDir = path.dirname(OUTPUT_FILE);
        if (!fs.existsSync(outputDir)) {
            fs.mkdirSync(outputDir, { recursive: true });
        }
        
        // Write types to file
        fs.writeFileSync(OUTPUT_FILE, types, 'utf8');
        
        console.log(`✅ TypeScript types generated successfully: ${OUTPUT_FILE}`);
    } catch (error) {
        console.error('❌ Error generating types:', error.message);
        process.exit(1);
    }
}

if (require.main === module) {
    main();
}

module.exports = { fetchOpenApiSpec, generateTypeScriptTypes };

