import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { PreferencesForm } from '../PreferencesForm';
import { useThemeMode } from '../../../app/theme-context';

// Mock the theme context
vi.mock('../../../app/theme-context', () => ({
  useThemeMode: vi.fn(),
}));

// Mock i18n context
vi.mock('../../../app/i18n-context', () => ({
  useI18n: () => ({
    t: (key: string, options?: { defaultValue?: string }) => options?.defaultValue || key,
  }),
}));

// Mock tokens
vi.mock('../../../shared/tokens', () => ({
  applyTheme: vi.fn(),
}));

const mockOnSubmit = vi.fn();
const mockUseThemeMode = useThemeMode as any;

const defaultProps = {
  onSubmit: mockOnSubmit,
  isLoading: false,
  initialData: {
    theme: 'light' as const,
    layout: 'grid' as const,
    density: 'comfortable' as const,
    refreshInterval: 60,
    notifications: {
      enabled: true,
      sound: true,
      desktop: true,
    },
    widgets: {
      defaultSize: 'medium' as const,
      autoRefresh: true,
      showTitles: true,
    },
  },
};

describe('PreferencesForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockUseThemeMode.mockReturnValue({
      mode: 'light',
      setMode: vi.fn(),
    });
  });

  describe('Rendering', () => {
    it('should render all preference sections', () => {
      render(<PreferencesForm {...defaultProps} />);

      expect(screen.getByText('Theme Settings')).toBeInTheDocument();
      expect(screen.getByText('Layout Settings')).toBeInTheDocument();
      expect(screen.getByText('Notification Settings')).toBeInTheDocument();
      expect(screen.getByText('Widget Settings')).toBeInTheDocument();
      expect(screen.getByText('Refresh Settings')).toBeInTheDocument();
    });

    it('should render theme options', () => {
      render(<PreferencesForm {...defaultProps} />);

      expect(screen.getByText('light')).toBeInTheDocument();
      expect(screen.getByText('dark')).toBeInTheDocument();
      expect(screen.getByText('auto')).toBeInTheDocument();
    });

    it('should render layout options', () => {
      render(<PreferencesForm {...defaultProps} />);

      expect(screen.getAllByText('grid')).toHaveLength(1);
      expect(screen.getAllByText('list')).toHaveLength(1);
      expect(screen.getAllByText('compact')).toHaveLength(2); // One for layout, one for density
    });

    it('should render density options', () => {
      render(<PreferencesForm {...defaultProps} />);

      expect(screen.getByText('comfortable')).toBeInTheDocument();
      expect(screen.getAllByText('compact')).toHaveLength(2); // One for layout, one for density
      expect(screen.getByText('spacious')).toBeInTheDocument();
    });
  });

  describe('Form Interaction', () => {
    it('should update theme selection', async () => {
      const user = userEvent.setup();
      render(<PreferencesForm {...defaultProps} />);

      const darkButton = screen.getByText('dark');
      await user.click(darkButton);

      // Check that the button has the primary variant class
      expect(darkButton.closest('button')).toHaveClass('bg-[var(--color-semantic-primary-500)]');
    });

    it('should update layout selection', async () => {
      const user = userEvent.setup();
      render(<PreferencesForm {...defaultProps} />);

      const listButton = screen.getByText('list');
      await user.click(listButton);

      // Check that the button has the primary variant class
      expect(listButton.closest('button')).toHaveClass('bg-[var(--color-semantic-primary-500)]');
    });

    it('should update density selection', async () => {
      const user = userEvent.setup();
      render(<PreferencesForm {...defaultProps} />);

      const compactButtons = screen.getAllByText('compact');
      const densityCompactButton = compactButtons[1]; // Second one is density
      await user.click(densityCompactButton);

      // Check that the button has the primary variant class
      expect(densityCompactButton.closest('button')).toHaveClass('bg-[var(--color-semantic-primary-500)]');
    });

    it('should toggle notification settings', async () => {
      const user = userEvent.setup();
      render(<PreferencesForm {...defaultProps} />);

      // Find checkboxes by their position in the form
      const checkboxes = screen.getAllByRole('checkbox');
      const soundCheckbox = checkboxes[1]; // Second checkbox is sound notifications
      expect(soundCheckbox).toBeChecked();

      await user.click(soundCheckbox);
      expect(soundCheckbox).not.toBeChecked();
    });

    it('should update refresh interval', async () => {
      const user = userEvent.setup();
      render(<PreferencesForm {...defaultProps} />);

      const rangeInput = screen.getByRole('slider');
      expect(rangeInput).toHaveValue('60');

      fireEvent.change(rangeInput, { target: { value: '120' } });
      expect(rangeInput).toHaveValue('120');
    });
  });

  describe('Form Submission', () => {
    it('should submit form with current values', async () => {
      const user = userEvent.setup();
      render(<PreferencesForm {...defaultProps} />);

      // Make a change to enable the submit button
      const darkButton = screen.getByText('dark');
      await user.click(darkButton);

      const saveButton = screen.getByText('Save Preferences');
      await user.click(saveButton);

      await waitFor(() => {
        expect(mockOnSubmit).toHaveBeenCalledWith(expect.objectContaining({
          theme: 'dark',
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
        }));
      });
    });

    it('should show loading state during submission', () => {
      render(<PreferencesForm {...defaultProps} isLoading={true} />);

      const saveButton = screen.getByText('Save Preferences');
      expect(saveButton.closest('button')).toBeDisabled();
    });

    it('should show unsaved changes indicator when form is dirty', async () => {
      const user = userEvent.setup();
      render(<PreferencesForm {...defaultProps} />);

      const darkButton = screen.getByText('dark');
      await user.click(darkButton);

      expect(screen.getByText('Unsaved changes')).toBeInTheDocument();
    });

    it('should enable save button when form is dirty', async () => {
      const user = userEvent.setup();
      render(<PreferencesForm {...defaultProps} />);

      const saveButton = screen.getByText('Save Preferences');
      expect(saveButton.closest('button')).toBeDisabled();

      const darkButton = screen.getByText('dark');
      await user.click(darkButton);

      expect(saveButton.closest('button')).not.toBeDisabled();
    });
  });

  describe('Reset Functionality', () => {
    it('should reset form to initial values', async () => {
      const user = userEvent.setup();
      render(<PreferencesForm {...defaultProps} />);

      // Make a change
      const darkButton = screen.getByText('dark');
      await user.click(darkButton);

      // Reset
      const resetButton = screen.getByText('Reset');
      await user.click(resetButton);

      // Check that light is selected again (should have primary variant)
      const lightButton = screen.getByText('light');
      expect(lightButton.closest('button')).toHaveClass('bg-[var(--color-semantic-primary-500)]');
    });

    it('should disable reset button when form is not dirty', () => {
      render(<PreferencesForm {...defaultProps} />);

      const resetButton = screen.getByText('Reset');
      expect(resetButton.closest('button')).toBeDisabled();
    });
  });

  describe('Theme Preview', () => {
    it('should show theme preview buttons', () => {
      render(<PreferencesForm {...defaultProps} />);

      expect(screen.getByText('☀️ Light')).toBeInTheDocument();
      expect(screen.getByText('🌙 Dark')).toBeInTheDocument();
    });

    it('should call setMode when preview button is clicked', async () => {
      const user = userEvent.setup();
      const mockSetMode = vi.fn();
      mockUseThemeMode.mockReturnValue({
        mode: 'light',
        setMode: mockSetMode,
      });

      render(<PreferencesForm {...defaultProps} />);

      const darkPreviewButton = screen.getByText('🌙 Dark');
      await user.click(darkPreviewButton);

      expect(mockSetMode).toHaveBeenCalledWith('dark');
    });
  });

  describe('Validation', () => {
    it('should validate refresh interval range', async () => {
      const user = userEvent.setup();
      render(<PreferencesForm {...defaultProps} />);

      const rangeInput = screen.getByRole('slider');
      
      // Test minimum value - the input enforces min="30"
      fireEvent.change(rangeInput, { target: { value: '20' } });
      expect(rangeInput).toHaveValue('30'); // Should snap to minimum

      // Test maximum value - the input enforces max="300"
      fireEvent.change(rangeInput, { target: { value: '400' } });
      expect(rangeInput).toHaveValue('300'); // Should snap to maximum
    });
  });
});
