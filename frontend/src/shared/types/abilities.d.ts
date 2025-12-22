/**
 * Generated Ability Types
 * 
 * Auto-generated from OpenAPI x-abilities extension.
 * DO NOT EDIT MANUALLY - run `npm run generate:abilities` to update.
 * 
 * Last generated: 2025-11-18
 */

/**
 * System-wide abilities (admin-only)
 */
export type SystemAbility = 
  | 'admin.access'
  | 'tenants.manage'
  | 'maintenance.*';

/**
 * Tenant-scoped abilities
 */
export type TenantAbility =
  | 'admin.access.tenant'
  | 'projects.view'
  | 'projects.create'
  | 'projects.modify'
  | 'projects.delete'
  | 'projects.manage'
  | 'tasks.view'
  | 'tasks.create'
  | 'tasks.modify'
  | 'tasks.delete'
  | 'tasks.manage'
  | 'documents.view'
  | 'documents.create'
  | 'documents.modify'
  | 'documents.delete'
  | 'documents.approve'
  | 'templates.manage'
  | 'users.view'
  | 'users.create'
  | 'users.modify'
  | 'users.delete'
  | 'reports.view'
  | 'reports.generate'
  | 'change_requests.view'
  | 'change_requests.create'
  | 'change_requests.approve'
  | 'change_requests.reject'
  | 'quotes.view'
  | 'quotes.create'
  | 'quotes.modify'
  | 'quotes.approve';

/**
 * All abilities
 */
export type Ability = SystemAbility | TenantAbility;

/**
 * Ability matrix from OpenAPI spec
 */
export interface AbilityMatrix {
  [ability: string]: {
    required_roles: string[];
    required_permissions: string[];
    description: string;
  };
}

/**
 * Check if user has ability
 */
export function hasAbility(userAbilities: Ability[], ability: Ability): boolean {
  return userAbilities.includes(ability);
}

/**
 * Check if user has any of the abilities
 */
export function hasAnyAbility(userAbilities: Ability[], abilities: Ability[]): boolean {
  return abilities.some(ability => userAbilities.includes(ability));
}

/**
 * Check if user has all of the abilities
 */
export function hasAllAbilities(userAbilities: Ability[], abilities: Ability[]): boolean {
  return abilities.every(ability => userAbilities.includes(ability));
}

