export const spacingTokens = {
  none: '0px',
  xxs: '0.125rem',
  xs: '0.25rem',
  sm: '0.5rem',
  md: '0.75rem',
  lg: '1rem',
  xl: '1.5rem',
  '2xl': '2rem',
  '3xl': '3rem',
} as const;

export const spacing = spacingTokens;

export type SpacingToken = keyof typeof spacingTokens;
