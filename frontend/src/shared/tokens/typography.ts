export const typographyTokens = {
  fontFamily: {
    sans: '"Inter", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif',
    mono: '"JetBrains Mono", "Fira Code", "SFMono-Regular", ui-monospace, Menlo, Monaco, "Liberation Mono", "Courier New", monospace',
  },
  fontSize: {
    xs: '0.75rem',
    sm: '0.875rem',
    md: '1rem',
    lg: '1.125rem',
    xl: '1.25rem',
    '2xl': '1.5rem',
    '3xl': '1.875rem',
  },
  lineHeight: {
    tight: '1.25',
    snug: '1.35',
    normal: '1.5',
    relaxed: '1.65',
  },
  fontWeight: {
    regular: '400',
    medium: '500',
    semibold: '600',
    bold: '700',
  },
} as const;

export type FontSizeToken = keyof typeof typographyTokens.fontSize;
