import React, { useState, useCallback } from 'react';
import { Button } from '../ui/primitives/Button';
import { spacing } from '../../shared/tokens/spacing';
import { radius } from '../../shared/tokens/radius';

export interface FilterOption {
  id: string;
  label: string;
  value: any;
}

export interface FilterPreset {
  id: string;
  name: string;
  filters: Record<string, any>;
  icon?: string;
}

export interface SmartFiltersProps {
  /** Available filter options */
  filters: {
    [key: string]: FilterOption[];
  };
  /** Current filter values */
  values: Record<string, any>;
  /** Change handler */
  onChange: (filters: Record<string, any>) => void;
  /** Available presets */
  presets?: FilterPreset[];
  /** Preset click handler */
  onPresetClick?: (preset: FilterPreset) => void;
}

/**
 * SmartFilters - Intelligent filter component with presets
 * 
 * Follows Apple-style design spec with tokens and spacing.
 */
export const SmartFilters: React.FC<SmartFiltersProps> = ({
  filters,
  values,
  onChange,
  presets = [],
  onPresetClick,
}) => {
  const [isOpen, setIsOpen] = useState(false);

  const handleFilterChange = useCallback(
    (key: string, value: any) => {
      onChange({ ...values, [key]: value });
    },
    [values, onChange]
  );

  const handlePresetClick = useCallback(
    (preset: FilterPreset) => {
      if (onPresetClick) {
        onPresetClick(preset);
      } else {
        onChange(preset.filters);
      }
    },
    [onChange, onPresetClick]
  );

  const clearFilters = useCallback(() => {
    onChange({});
  }, [onChange]);

  // Count only filters that have actual values (not null, undefined, or empty string)
  // Exclude 'search' from count as it's handled separately
  const activeFilterCount = Object.keys(values).filter((key) => {
    // Don't count search as a filter (it's handled separately in search bar)
    if (key === 'search') return false;
    const value = values[key];
    return value !== null && value !== undefined && value !== '';
  }).length;
  
  // Debug logging in development
  if (process.env.NODE_ENV === 'development' && activeFilterCount > 0) {
    console.log('[SmartFilters] Active filters:', {
      values,
      activeFilterCount,
      activeKeys: Object.keys(values).filter((key) => {
        if (key === 'search') return false;
        const value = values[key];
        return value !== null && value !== undefined && value !== '';
      })
    });
  }

  return (
    <div className="space-y-2" data-testid="smart-filters">
      {/* Presets and Filters in one row on desktop */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 flex-wrap">
        {/* Presets */}
        {presets.length > 0 && (
          <>
            {presets.map((preset) => (
              <Button
                key={preset.id}
                onClick={() => handlePresetClick(preset)}
                className="w-full sm:w-auto min-h-[40px] text-xs"
                style={{
                  padding: `${spacing.xs}px ${spacing.sm}px`,
                  borderRadius: radius.sm,
                  fontSize: '12px',
                }}
              >
                {preset.icon && <span className="mr-1">{preset.icon}</span>}
                {preset.name}
              </Button>
            ))}
          </>
        )}

        {/* Filters */}
        {Object.entries(filters).map(([key, options]) => (
          <select
            key={key}
            value={values[key] || ''}
            onChange={(e) => handleFilterChange(key, e.target.value || null)}
            className="w-full sm:w-auto sm:min-w-[140px] min-h-[40px] text-xs"
            style={{
              padding: `${spacing.xs}px ${spacing.sm}px`,
              borderRadius: radius.sm,
              border: '1px solid var(--border)',
              backgroundColor: 'var(--surface)',
              color: 'var(--text)',
              fontSize: '12px',
            }}
          >
            <option value="">All {key}</option>
            {options.map((option) => (
              <option key={option.id} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        ))}

        {/* Clear Button */}
        {activeFilterCount > 0 && (
          <Button
            onClick={clearFilters}
            className="w-full sm:w-auto min-h-[40px] text-xs"
            style={{
              padding: `${spacing.xs}px ${spacing.sm}px`,
              borderRadius: radius.sm,
              fontSize: '12px',
            }}
          >
            Clear ({activeFilterCount})
          </Button>
        )}
      </div>
    </div>
  );
};

