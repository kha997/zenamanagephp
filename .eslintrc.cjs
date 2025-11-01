module.exports = {
  root: true,
  env: { browser: true, es2023: true },
  extends: [
    'eslint:recommended',
    'plugin:@typescript-eslint/recommended',
    'plugin:react-hooks/recommended',
  ],
  ignorePatterns: ['dist', '.eslintrc.cjs'],
  parser: '@typescript-eslint/parser',
  plugins: ['react-refresh', 'jsx-a11y', 'local-rules'],
  globals: {
    Alpine: 'readonly',
    Chart: 'readonly',
    axios: 'readonly',
    gtag: 'readonly',
    NodeJS: 'readonly'
  },
  rules: {
    'react-refresh/only-export-components': [
      'warn',
      { allowConstantExport: true },
    ],
    '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
    '@typescript-eslint/no-explicit-any': 'warn',
    'local-rules/no-nav-anchor-links': 'error',
  },
  overrides: [
    {
      files: ['src/components/ui/header/**/*.{ts,tsx}'],
      extends: ['plugin:jsx-a11y/recommended'],
      rules: {
        '@typescript-eslint/consistent-type-imports': 'error',
        'jsx-a11y/anchor-is-valid': [
          'error',
          { components: ['Link'], specialLink: ['to'], aspects: ['noHref', 'invalidHref'] },
        ],
        'jsx-a11y/no-autofocus': 'error',
        'jsx-a11y/no-noninteractive-tabindex': 'error',
      },
    },
  ],
};
