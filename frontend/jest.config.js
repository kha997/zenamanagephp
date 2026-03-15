export default {
  testEnvironment: 'jsdom',
  setupFilesAfterEnv: ['<rootDir>/src/setupTests.ts'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/src/$1',
    '^react$': '<rootDir>/../node_modules/react/index.js',
    '^react/jsx-runtime$': '<rootDir>/../node_modules/react/jsx-runtime.js',
    '^react-dom$': '<rootDir>/../node_modules/react-dom/index.js',
    '^\\.\\.\\/\\.\\.\\/hooks/useAuth$': '<rootDir>/src/hooks/useAuth.ts',
    '^\\.\\.\\/\\.\\.\\/hooks/useRealTimeUpdates$': '<rootDir>/src/hooks/useRealTimeUpdates.ts',
    '^\\.\\.\\/\\.\\.\\/types/dashboard$': '<rootDir>/src/types/dashboard.ts',
  },
  testPathIgnorePatterns: [
    '<rootDir>/src/tests/auth-integration.test.tsx',
  ],
  collectCoverageFrom: [
    'src/**/*.{ts,tsx}',
    '!src/**/*.d.ts',
    '!src/index.tsx',
    '!src/reportWebVitals.ts',
  ],
  coverageThreshold: {
    global: {
      branches: 80,
      functions: 80,
      lines: 80,
      statements: 80,
    },
  },
  testMatch: [
    '<rootDir>/src/**/__tests__/**/*.{ts,tsx}',
    '<rootDir>/src/**/*.{test,spec}.{ts,tsx}',
  ],
  transform: {
    '^.+\\.(ts|tsx)$': [
      'ts-jest',
      {
        diagnostics: false,
        tsconfig: '<rootDir>/tsconfig.jest.json',
      },
    ],
  },
  moduleFileExtensions: ['ts', 'tsx', 'js', 'jsx', 'json', 'node'],
};
