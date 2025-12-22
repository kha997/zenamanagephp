export const radiusTokens = {
  none: '0px',
  sm: '0.25rem',
  md: '0.5rem',
  lg: '0.75rem',
  xl: '1rem',
  pill: '9999px',
} as const;

export const radius = radiusTokens;

export type RadiusToken = keyof typeof radiusTokens;
