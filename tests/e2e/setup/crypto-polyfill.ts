// Crypto polyfill for Playwright tests
// This file provides polyfills for crypto.randomUUID and crypto.getRandomValues
// which are not available in the Playwright test environment

export default async function globalSetup() {
  // Setup crypto polyfills
  if (typeof window !== 'undefined' && !window.crypto) {
    // @ts-ignore
    window.crypto = {};
  }

  if (typeof window !== 'undefined' && !window.crypto.randomUUID) {
    // @ts-ignore
    window.crypto.randomUUID = function() {
      return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
      });
    };
  }

  if (typeof window !== 'undefined' && !window.crypto.getRandomValues) {
    // @ts-ignore
    window.crypto.getRandomValues = function(array: any) {
      for (let i = 0; i < array.length; i++) {
        array[i] = Math.floor(Math.random() * 256);
      }
      return array;
    };
  }

  // Fix for Playwright's crypto.random issue
  if (typeof window !== 'undefined' && !(window.crypto as any).random) {
    // @ts-ignore
    (window.crypto as any).random = {
      randomUUID: function() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
          const r = Math.random() * 16 | 0;
          const v = c === 'x' ? r : (r & 0x3 | 0x8);
          return v.toString(16);
        });
      }
    };
  }

  // Also polyfill for Node.js environment if needed
  if (typeof globalThis !== 'undefined' && !(globalThis as any).crypto) {
    // @ts-ignore
    (globalThis as any).crypto = {
      randomUUID: function() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
          const r = Math.random() * 16 | 0;
          const v = c === 'x' ? r : (r & 0x3 | 0x8);
          return v.toString(16);
        });
      },
      getRandomValues: function(array: any) {
        for (let i = 0; i < array.length; i++) {
          array[i] = Math.floor(Math.random() * 256);
        }
        return array;
      }
    };
  }
}
