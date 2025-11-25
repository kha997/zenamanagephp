/**
 * Navigation Feature Flag Configuration
 * 
 * Controls which navigation component to use:
 * - false: Use PrimaryNavigator (legacy)
 * - true: Use AppNavigator (new, text-only, full dark mode support)
 * 
 * Set via environment variable: VITE_USE_NEW_NAV=true
 */
export const USE_NEW_NAVIGATION = 
  (import.meta.env as { VITE_USE_NEW_NAV?: string }).VITE_USE_NEW_NAV === 'true' || 
  (import.meta.env as { VITE_USE_NEW_NAV?: string }).VITE_USE_NEW_NAV === '1';

