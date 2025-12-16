# ThemeProvider Usage

Wrap your app once:

```tsx
import { ThemeProvider } from '@/shared/theme/ThemeProvider';

export function AppRoot() {
  return (
    <ThemeProvider>
      {/* your routers/layouts */}
    </ThemeProvider>
  );
}
```

Access theme and toggle:

```tsx
import { useTheme } from '@/shared/theme/ThemeProvider';

function ThemeToggleButton() {
  const { theme, toggleTheme } = useTheme();
  return (
    <button onClick={toggleTheme}>
      {theme === 'light' ? 'Dark' : 'Light'}
    </button>
  );
}
```

Notes
- Respects system preference until the user chooses a theme.
- Persists preference in localStorage under `ui.theme`.
- Applies CSS variables on `<html>` via `data-theme` and inline variables.

