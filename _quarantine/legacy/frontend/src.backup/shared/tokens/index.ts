import { buildColorTokens, type ColorMode, type SemanticColorKey } from './colors';
import { spacingTokens } from './spacing';
import { radiusTokens } from './radius';
import { typographyTokens } from './typography';

type TokenTree = Record<string, string | number | Record<string, any>>;

const flattenTokens = (prefix: string, tree: TokenTree, acc: Record<string, string>) => {
  Object.entries(tree).forEach(([key, value]) => {
    const tokenKey = prefix ? `${prefix}-${key}` : key;
    if (typeof value === 'object' && value !== null) {
      flattenTokens(tokenKey, value as TokenTree, acc);
      return;
    }

    acc[`--${tokenKey}`] = String(value);
  });
};

const colorCache = {
  light: buildColorTokens('light'),
  dark: buildColorTokens('dark'),
} as const;

export type ThemeSnapshot = {
  colors: typeof colorCache.light;
  spacing: typeof spacingTokens;
  radius: typeof radiusTokens;
  typography: typeof typographyTokens;
};

export const buildCssVarMap = (mode: ColorMode): Record<string, string> => {
  const colors = colorCache[mode];
  const vars: Record<string, string> = {};

  flattenTokens('color-semantic', colors.semantic as TokenTree, vars);
  flattenTokens('color-surface', colors.surface as TokenTree, vars);
  flattenTokens('color-text', colors.text as TokenTree, vars);
  flattenTokens('color-border', colors.border as TokenTree, vars);
  flattenTokens('space', spacingTokens as TokenTree, vars);
  flattenTokens('radius', radiusTokens as TokenTree, vars);
  flattenTokens('font-family', typographyTokens.fontFamily as TokenTree, vars);
  flattenTokens('font-size', typographyTokens.fontSize as TokenTree, vars);
  flattenTokens('line-height', typographyTokens.lineHeight as TokenTree, vars);
  flattenTokens('font-weight', typographyTokens.fontWeight as TokenTree, vars);

  return vars;
};

export const applyTheme = (mode: ColorMode, target: HTMLElement = document.documentElement) => {
  const vars = buildCssVarMap(mode);
  Object.entries(vars).forEach(([key, value]) => target.style.setProperty(key, value));
  target.dataset.theme = mode;
  // Add/remove 'dark' class for Tailwind dark mode
  if (mode === 'dark') {
    target.classList.add('dark');
  } else {
    target.classList.remove('dark');
  }
  return vars;
};

export const getThemeSnapshot = (mode: ColorMode): ThemeSnapshot => ({
  colors: colorCache[mode],
  spacing: spacingTokens,
  radius: radiusTokens,
  typography: typographyTokens,
});

export const resolveNextMode = (mode: ColorMode): ColorMode => (mode === 'light' ? 'dark' : 'light');

export type { ColorMode, SemanticColorKey };
export { spacingTokens, radiusTokens, typographyTokens };
