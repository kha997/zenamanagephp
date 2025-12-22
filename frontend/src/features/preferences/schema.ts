import { z } from 'zod';

export const preferencesSchema = z.object({
  theme: z.enum(['light', 'dark', 'auto']),
  layout: z.enum(['grid', 'list', 'compact']),
  density: z.enum(['comfortable', 'compact', 'spacious']),
  refreshInterval: z.number().min(30).max(300), // 30 seconds to 5 minutes
  notifications: z.object({
    enabled: z.boolean(),
    sound: z.boolean(),
    desktop: z.boolean(),
  }),
  widgets: z.object({
    defaultSize: z.enum(['small', 'medium', 'large', 'xlarge']),
    autoRefresh: z.boolean(),
    showTitles: z.boolean(),
  }),
});

export type PreferencesFormData = z.infer<typeof preferencesSchema>;

// Default preferences
export const defaultPreferences: PreferencesFormData = {
  theme: 'auto',
  layout: 'grid',
  density: 'comfortable',
  refreshInterval: 60,
  notifications: {
    enabled: true,
    sound: true,
    desktop: true,
  },
  widgets: {
    defaultSize: 'medium',
    autoRefresh: true,
    showTitles: true,
  },
};
