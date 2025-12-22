import React, { useState, useRef, useEffect } from 'react';

export interface SelectOption {
  value: string;
  label: string;
}

export interface SelectProps extends Omit<React.SelectHTMLAttributes<HTMLSelectElement>, 'onChange'> {
  options?: SelectOption[];
  value?: string;
  onChange?: (value: string) => void;
  placeholder?: string;
  error?: string;
  children?: React.ReactNode;
  'data-testid'?: string;
}

export const Select = React.forwardRef<HTMLSelectElement, SelectProps>(
  ({ options, value, onChange, placeholder, error, className, style, disabled, children, 'data-testid': testId, ...props }, ref) => {
    const [isOpen, setIsOpen] = useState(false);
    const [internalValue, setInternalValue] = useState(value || '');
    const selectRef = useRef<HTMLSelectElement>(null);
    const wrapperRef = useRef<HTMLDivElement>(null);
    const isInternalChangeRef = useRef(false);
    const prevValueRef = useRef<string | undefined>(value);

    // Sync internal value with prop value (only when prop changes externally)
    useEffect(() => {
      // Only update if value prop changed from outside (not from internal change)
      if (value !== undefined && value !== prevValueRef.current && !isInternalChangeRef.current) {
        prevValueRef.current = value;
        if (value !== internalValue) {
          setInternalValue(value);
        }
      } else if (value !== undefined) {
        prevValueRef.current = value;
      }
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]); // Only depend on value prop to avoid loops

    // Close dropdown when clicking outside
    useEffect(() => {
      const handleClickOutside = (event: MouseEvent) => {
        if (wrapperRef.current && !wrapperRef.current.contains(event.target as Node)) {
          setIsOpen(false);
        }
      };

      if (isOpen) {
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
      }
    }, [isOpen]);

    const handleSelectChange = (newValue: string) => {
      // Prevent infinite loop
      if (isInternalChangeRef.current) {
        return;
      }
      
      isInternalChangeRef.current = true;
      setInternalValue(newValue);
      onChange?.(newValue);
      setIsOpen(false);
      
      // Update native select value without triggering change event
      if (selectRef.current) {
        selectRef.current.value = newValue;
      }
      
      // Reset flag after a microtask to allow next change
      Promise.resolve().then(() => {
        isInternalChangeRef.current = false;
      });
    };

    // Handle native select change (only for form compatibility, not for custom dropdown)
    const handleNativeSelectChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
      // Only handle if it's not an internal change
      if (!isInternalChangeRef.current) {
        handleSelectChange(e.target.value);
      }
    };

    // Build options from prop or extract from children
    const effectiveOptions: SelectOption[] = options || [];
    
    // If no options prop but has children, extract options from children
    if (!options && children) {
      React.Children.forEach(children, (child) => {
        if (React.isValidElement(child) && child.type === 'option') {
          const optionValue = child.props.value || '';
          const optionLabel = typeof child.props.children === 'string' 
            ? child.props.children 
            : optionValue;
          effectiveOptions.push({ value: optionValue, label: optionLabel });
        }
      });
    }
    
    const selectedOption = effectiveOptions.find((opt) => opt.value === internalValue);
    const displayValue = selectedOption?.label || placeholder || '';

    const wrapperStyle: React.CSSProperties = {
      position: 'relative',
      width: '100%',
      ...style,
    };

    const buttonStyle: React.CSSProperties = {
      width: '100%',
      height: 40,
      borderRadius: 10,
      border: `1px solid ${error ? 'var(--gray-400)' : 'var(--gray-200)'}`,
      background: 'transparent',
      color: 'var(--text)',
      padding: '0 12px',
      paddingRight: 36,
      fontSize: 14,
      fontWeight: 400,
      outline: 'none',
      cursor: disabled ? 'not-allowed' : 'pointer',
      opacity: disabled ? 0.6 : 1,
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      textAlign: 'left',
      boxShadow: 'none',
      transition: 'border-color 150ms, box-shadow 150ms',
    };

    const dropdownStyle: React.CSSProperties = {
      position: 'absolute',
      top: '100%',
      left: 0,
      right: 0,
      marginTop: 4,
      backgroundColor: 'var(--surface)',
      border: '1px solid var(--border)',
      borderRadius: 10,
      boxShadow: '0 2px 8px rgba(0, 0, 0, 0.06)',
      zIndex: 1000,
      maxHeight: 240,
      overflowY: 'auto',
      display: isOpen ? 'block' : 'none',
    };

    const optionStyle: React.CSSProperties = {
      padding: '10px 12px',
      fontSize: 14,
      color: 'var(--text)',
      cursor: 'pointer',
      backgroundColor: 'transparent',
      border: 'none',
      width: '100%',
      textAlign: 'left',
      transition: 'background-color 150ms',
    };

    const arrowStyle: React.CSSProperties = {
      position: 'absolute',
      right: 12,
      top: '50%',
      transform: `translateY(-50%) ${isOpen ? 'rotate(180deg)' : ''}`,
      transition: 'transform 150ms',
      width: 12,
      height: 12,
      pointerEvents: 'none',
      color: 'var(--muted)',
    };

    return (
      <div style={wrapperStyle} ref={wrapperRef} className={className}>
        {/* Hidden native select for form compatibility */}
        <select
          ref={(node) => {
            if (typeof ref === 'function') {
              ref(node);
            } else if (ref) {
              (ref as React.MutableRefObject<HTMLSelectElement | null>).current = node;
            }
            selectRef.current = node;
          }}
          value={internalValue}
          onChange={handleNativeSelectChange}
          disabled={disabled}
          style={{ display: 'none' }}
          {...props}
        >
          {placeholder && (
            <option value="" disabled>
              {placeholder}
            </option>
          )}
          {options ? (
            options.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))
          ) : (
            children
          )}
        </select>

        {/* Custom styled button */}
        <button
          type="button"
          style={buttonStyle}
          onClick={() => !disabled && setIsOpen(!isOpen)}
          onFocus={(e) => {
            e.currentTarget.style.boxShadow = `0 0 0 3px var(--ring)`;
            e.currentTarget.style.borderColor = 'var(--accent)';
          }}
          onBlur={(e) => {
            e.currentTarget.style.boxShadow = 'none';
            e.currentTarget.style.borderColor = error ? 'var(--gray-400)' : 'var(--gray-200)';
          }}
          disabled={disabled}
          aria-haspopup="listbox"
          aria-expanded={isOpen}
          data-testid={testId}
        >
          <span style={{ color: internalValue ? 'var(--text)' : 'var(--muted)' }}>
            {displayValue}
          </span>
          <svg
            style={arrowStyle}
            viewBox="0 0 12 12"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path
              d="M3 4.5L6 7.5L9 4.5"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        </button>

        {/* Dropdown */}
        {isOpen && effectiveOptions.length > 0 && (
          <div style={dropdownStyle} role="listbox">
            {effectiveOptions.map((option) => (
              <button
                key={option.value}
                type="button"
                role="option"
                aria-selected={option.value === internalValue}
                style={{
                  ...optionStyle,
                  backgroundColor: option.value === internalValue ? 'var(--muted-surface)' : 'transparent',
                  fontWeight: option.value === internalValue ? 500 : 400,
                }}
                onMouseEnter={(e) => {
                  if (option.value !== internalValue) {
                    e.currentTarget.style.backgroundColor = 'var(--muted-surface)';
                  }
                }}
                onMouseLeave={(e) => {
                  if (option.value !== internalValue) {
                    e.currentTarget.style.backgroundColor = 'transparent';
                  }
                }}
                onClick={() => handleSelectChange(option.value)}
              >
                {option.label}
              </button>
            ))}
          </div>
        )}

        {error && (
          <div style={{ marginTop: 6, fontSize: 12, color: 'var(--muted)' }}>{error}</div>
        )}
      </div>
    );
  }
);

Select.displayName = 'Select';

export default Select;

