export type ColorMode = 'light' | 'dark';
type RampKey = '50' | '100' | '200' | '300' | '400' | '500' | '600' | '700' | '800' | '900';
export type SemanticColorKey = 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'info' | 'neutral';

interface SemanticScale {
  ramp: Record<RampKey, string>;
  contrast: string;
}

const semanticPalette: Record<SemanticColorKey, Record<ColorMode, SemanticScale>> = {
  primary: {
    light: {
      ramp: {
        '50': '#eff6ff',
        '100': '#dbeafe',
        '200': '#bfdbfe',
        '300': '#93c5fd',
        '400': '#60a5fa',
        '500': '#3b82f6',
        '600': '#2563eb',
        '700': '#1d4ed8',
        '800': '#1e40af',
        '900': '#1e3a8a',
      },
      contrast: '#ffffff',
    },
    dark: {
      ramp: {
        '50': '#0b1226',
        '100': '#102046',
        '200': '#132b5c',
        '300': '#17387b',
        '400': '#1b469a',
        '500': '#2255b3',
        '600': '#376de0',
        '700': '#5a89f0',
        '800': '#88a8f6',
        '900': '#c3d4fb',
      },
      contrast: '#0b1120',
    },
  },
  secondary: {
    light: {
      ramp: {
        '50': '#f5f3ff',
        '100': '#ede9fe',
        '200': '#ddd6fe',
        '300': '#c4b5fd',
        '400': '#a855f7',
        '500': '#9333ea',
        '600': '#7e22ce',
        '700': '#6b21a8',
        '800': '#581c87',
        '900': '#43146b',
      },
      contrast: '#ffffff',
    },
    dark: {
      ramp: {
        '50': '#161037',
        '100': '#22165c',
        '200': '#301c80',
        '300': '#3d23a3',
        '400': '#4d2fc7',
        '500': '#5f3fe2',
        '600': '#7b5cf3',
        '700': '#9d7ffc',
        '800': '#c3abff',
        '900': '#e3d9ff',
      },
      contrast: '#0b1120',
    },
  },
  success: {
    light: {
      ramp: {
        '50': '#ecfdf5',
        '100': '#d1fae5',
        '200': '#a7f3d0',
        '300': '#6ee7b7',
        '400': '#34d399',
        '500': '#10b981',
        '600': '#059669',
        '700': '#047857',
        '800': '#065f46',
        '900': '#064e3b',
      },
      contrast: '#ffffff',
    },
    dark: {
      ramp: {
        '50': '#052e21',
        '100': '#084734',
        '200': '#0c5f46',
        '300': '#11775a',
        '400': '#1a8f6f',
        '500': '#24a481',
        '600': '#34be97',
        '700': '#5fd6b1',
        '800': '#92e7cd',
        '900': '#c8f6e5',
      },
      contrast: '#0b1120',
    },
  },
  warning: {
    light: {
      ramp: {
        '50': '#fffbeb',
        '100': '#fef3c7',
        '200': '#fde68a',
        '300': '#fcd34d',
        '400': '#fbbf24',
        '500': '#f59e0b',
        '600': '#d97706',
        '700': '#b45309',
        '800': '#92400e',
        '900': '#78350f',
      },
      contrast: '#0b1120',
    },
    dark: {
      ramp: {
        '50': '#2a1900',
        '100': '#3d2200',
        '200': '#502d00',
        '300': '#633a00',
        '400': '#7c4c00',
        '500': '#995f00',
        '600': '#b77707',
        '700': '#d89826',
        '800': '#f3b84f',
        '900': '#fde4a3',
      },
      contrast: '#0b1120',
    },
  },
  danger: {
    light: {
      ramp: {
        '50': '#fef2f2',
        '100': '#fee2e2',
        '200': '#fecaca',
        '300': '#fca5a5',
        '400': '#f87171',
        '500': '#ef4444',
        '600': '#dc2626',
        '700': '#b91c1c',
        '800': '#991b1b',
        '900': '#7f1d1d',
      },
      contrast: '#ffffff',
    },
    dark: {
      ramp: {
        '50': '#2c0a14',
        '100': '#3f111d',
        '200': '#561a27',
        '300': '#6c2431',
        '400': '#892f3e',
        '500': '#a53b4a',
        '600': '#c75261',
        '700': '#e56f7c',
        '800': '#f59aa7',
        '900': '#ffd2d8',
      },
      contrast: '#0b1120',
    },
  },
  info: {
    light: {
      ramp: {
        '50': '#f0f9ff',
        '100': '#e0f2fe',
        '200': '#bae6fd',
        '300': '#7dd3fc',
        '400': '#38bdf8',
        '500': '#0ea5e9',
        '600': '#0284c7',
        '700': '#0369a1',
        '800': '#075985',
        '900': '#0c4a6e',
      },
      contrast: '#ffffff',
    },
    dark: {
      ramp: {
        '50': '#0b2233',
        '100': '#0f3148',
        '200': '#13425f',
        '300': '#155078',
        '400': '#1a6293',
        '500': '#1f75ae',
        '600': '#2f91cc',
        '700': '#54abd8',
        '800': '#86c6e8',
        '900': '#c9e8f6',
      },
      contrast: '#0b1120',
    },
  },
  neutral: {
    light: {
      ramp: {
        '50': '#f8fafc',
        '100': '#f1f5f9',
        '200': '#e2e8f0',
        '300': '#cbd5f5',
        '400': '#94a3b8',
        '500': '#64748b',
        '600': '#475569',
        '700': '#334155',
        '800': '#1e293b',
        '900': '#0f172a',
      },
      contrast: '#0f172a',
    },
    dark: {
      ramp: {
        '50': '#111827',
        '100': '#1f2937',
        '200': '#273341',
        '300': '#323f4c',
        '400': '#3f4c58',
        '500': '#4b5864',
        '600': '#5b6774',
        '700': '#748190',
        '800': '#9aa5b1',
        '900': '#cbd4dd',
      },
      contrast: '#f8fafc',
    },
  },
};

const surfaceColors = {
  light: {
    base: '#f4f6fb',
    card: '#ffffff',
    muted: '#f1f5f9',
    subtle: '#e2e8f0',
    overlay: 'rgba(15, 23, 42, 0.55)',
  },
  dark: {
    base: '#0b1120',
    card: '#111827',
    muted: '#1f2937',
    subtle: '#1e293b',
    overlay: 'rgba(15, 23, 42, 0.72)',
  },
} as const;

const textColors = {
  light: {
    primary: '#0f172a',
    secondary: '#334155',
    muted: '#64748b',
    contrast: '#f8fafc',
  },
  dark: {
    primary: '#f8fafc',
    secondary: '#e2e8f0',
    muted: '#94a3b8',
    contrast: '#0b1120',
  },
} as const;

const borderColors = {
  light: {
    subtle: '#e2e8f0',
    default: '#cbd5f5',
    strong: '#94a3b8',
    focus: 'rgba(59, 130, 246, 0.45)',
  },
  dark: {
    subtle: '#1e293b',
    default: '#334155',
    strong: '#475569',
    focus: 'rgba(59, 130, 246, 0.65)',
  },
} as const;

export const buildColorTokens = (mode: ColorMode) => {
  const semantic = Object.entries(semanticPalette).reduce<Record<string, Record<string, string>>>(
    (acc, [key, value]) => {
      acc[key] = {
        ...value[mode].ramp,
        contrast: value[mode].contrast,
      };
      return acc;
    },
    {},
  );

  return {
    semantic,
    surface: surfaceColors[mode],
    text: textColors[mode],
    border: borderColors[mode],
  };
};
