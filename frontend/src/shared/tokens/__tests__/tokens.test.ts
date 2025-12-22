import { describe, expect, it } from 'vitest';
import { applyTheme, buildCssVarMap } from '..';

describe('design tokens', () => {
  it('applyTheme thiết lập CSS variables và dataset', () => {
    const element = document.createElement('div');
    const vars = applyTheme('light', element);

    expect(vars['--color-semantic-primary-500']).toBe('#3b82f6');
    expect(element.style.getPropertyValue('--color-semantic-primary-500')).toBe('#3b82f6');
    expect(element.dataset.theme).toBe('light');
  });

  it('light vs dark surface khác nhau', () => {
    const light = buildCssVarMap('light');
    const dark = buildCssVarMap('dark');

    expect(light['--color-surface-card']).not.toBe(dark['--color-surface-card']);
  });
});
