import type { FullConfig } from '@playwright/test';

async function globalSetup(_: FullConfig) {
  const scope = globalThis as typeof globalThis & {
    crypto?: Crypto & {
      randomUUID?: () => string;
      getRandomValues?: (array: Uint8Array) => Uint8Array;
    };
  };

  if (!scope.crypto) {
    scope.crypto = {} as Crypto;
  }

  if (!scope.crypto.randomUUID) {
    scope.crypto.randomUUID = () =>
      'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = Math.floor(Math.random() * 16);
        const value = char === 'x' ? random : (random & 0x3) | 0x8;
        return value.toString(16);
      });
  }

  if (!scope.crypto.getRandomValues) {
    scope.crypto.getRandomValues = (array: Uint8Array) => {
      const buffer = array;
      for (let index = 0; index < buffer.length; index += 1) {
        buffer[index] = Math.floor(Math.random() * 256);
      }
      return buffer;
    };
  }
}

export default globalSetup;
