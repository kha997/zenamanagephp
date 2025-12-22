#!/usr/bin/env node

/**
 * Generate TypeScript types for abilities from OpenAPI x-abilities extensions
 * 
 * This script reads the OpenAPI spec and extracts x-abilities from endpoints,
 * then generates TypeScript types for frontend use.
 * 
 * Usage: npm run generate:abilities
 */

const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');

function extractAbilities(specPath) {
  const specContent = fs.readFileSync(specPath, 'utf-8');
  const spec = yaml.load(specContent);

  const abilities = [];

  for (const [endpoint, methods] of Object.entries(spec.paths || {})) {
    for (const [method, operation] of Object.entries(methods)) {
      if (operation['x-abilities'] && Array.isArray(operation['x-abilities'])) {
        for (const ability of operation['x-abilities']) {
          abilities.push({
            ability,
            endpoint,
            method: method.toUpperCase(),
            summary: operation.summary,
            tags: operation.tags,
          });
        }
      }
    }
  }

  return abilities;
}

function generateTypeDefinitions(abilities) {
  // Get unique abilities
  const uniqueAbilities = [...new Set(abilities.map(a => a.ability))].sort();

  // Generate type definitions
  const typeDefs = `/**
 * Generated TypeScript types for API abilities
 * 
 * This file is auto-generated from OpenAPI spec x-abilities extensions.
 * Do not edit manually. Run \`npm run generate:abilities\` to regenerate.
 * 
 * Generated: ${new Date().toISOString()}
 */

/**
 * All available abilities/permissions in the system
 */
export type Ability = ${uniqueAbilities.map(a => `'${a}'`).join(' | ')};

/**
 * Ability definitions with endpoint information
 */
export interface AbilityDefinition {
  ability: Ability;
  endpoint: string;
  method: string;
  summary?: string;
  tags?: string[];
}

/**
 * Map of abilities to their endpoint definitions
 */
export const ABILITY_DEFINITIONS: Record<Ability, AbilityDefinition[]> = {
${uniqueAbilities.map(ability => {
  const defs = abilities.filter(a => a.ability === ability);
  return `  '${ability}': ${JSON.stringify(defs, null, 2).split('\n').map((line, i) => i === 0 ? line : '  ' + line).join('\n')},`;
}).join('\n')}
};

/**
 * Check if user has a specific ability
 */
export function hasAbility(userAbilities: Ability[], ability: Ability): boolean {
  return userAbilities.includes(ability);
}

/**
 * Check if user has any of the specified abilities
 */
export function hasAnyAbility(userAbilities: Ability[], requiredAbilities: Ability[]): boolean {
  return requiredAbilities.some(ability => userAbilities.includes(ability));
}

/**
 * Check if user has all of the specified abilities
 */
export function hasAllAbilities(userAbilities: Ability[], requiredAbilities: Ability[]): boolean {
  return requiredAbilities.every(ability => userAbilities.includes(ability));
}

/**
 * Get abilities required for an endpoint
 */
export function getAbilitiesForEndpoint(endpoint: string, method: string): Ability[] {
  const defs = Object.values(ABILITY_DEFINITIONS).flat();
  const matching = defs.filter(
    def => def.endpoint === endpoint && def.method === method
  );
  return matching.map(def => def.ability);
}
`;

  return typeDefs;
}

function main() {
  // Handle both CommonJS and ES modules
  const currentDir = __dirname || path.dirname(new URL(import.meta.url).pathname);
  const specPath = path.join(currentDir, '../../docs/api/openapi.yaml');
  const outputPath = path.join(currentDir, '../src/shared/types/abilities.d.ts');

  if (!fs.existsSync(specPath)) {
    console.error(`OpenAPI spec not found at: ${specPath}`);
    process.exit(1);
  }

  console.log('Extracting abilities from OpenAPI spec...');
  const abilities = extractAbilities(specPath);

  if (abilities.length === 0) {
    console.warn('No x-abilities found in OpenAPI spec');
    return;
  }

  console.log(`Found ${abilities.length} ability definitions`);
  console.log(`Unique abilities: ${[...new Set(abilities.map(a => a.ability))].length}`);

  console.log('Generating TypeScript types...');
  const typeDefs = generateTypeDefinitions(abilities);

  // Ensure output directory exists
  const outputDir = path.dirname(outputPath);
  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
  }

  fs.writeFileSync(outputPath, typeDefs, 'utf-8');
  console.log(`Types generated: ${outputPath}`);
}

if (require.main === module) {
  main();
}

