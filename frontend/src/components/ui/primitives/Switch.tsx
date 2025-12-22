import React from 'react';

export interface SwitchProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label?: string;
  description?: string;
}

export const Switch = React.forwardRef<HTMLInputElement, SwitchProps>(
  ({ label, description, className, style, disabled, ...props }, ref) => {
    const switchId = React.useId();

    const containerStyle: React.CSSProperties = {
      display: 'flex',
      alignItems: 'flex-start',
      gap: 12,
      ...style,
    };

    const switchWrapperStyle: React.CSSProperties = {
      position: 'relative',
      display: 'inline-flex',
      alignItems: 'center',
      flexShrink: 0,
      width: 44,
      height: 26,
      cursor: disabled ? 'not-allowed' : 'pointer',
      opacity: disabled ? 0.6 : 1,
    };

    const trackStyle: React.CSSProperties = {
      position: 'absolute',
      width: '100%',
      height: 26,
      borderRadius: 13,
      backgroundColor: props.checked ? 'var(--accent)' : 'var(--gray-300)',
      transition: 'background-color 150ms cubic-bezier(0.2, 0, 0, 1)',
      border: 'none',
    };

    const thumbStyle: React.CSSProperties = {
      position: 'absolute',
      width: 20,
      height: 20,
      borderRadius: '50%',
      backgroundColor: '#fff',
      boxShadow: '0 2px 4px rgba(0, 0, 0, 0.1)',
      transform: props.checked ? 'translateX(20px)' : 'translateX(2px)',
      transition: 'transform 150ms cubic-bezier(0.2, 0, 0, 1)',
      left: 0,
    };

    const labelStyle: React.CSSProperties = {
      flex: 1,
      minWidth: 0,
    };

    const labelTextStyle: React.CSSProperties = {
      fontSize: 14,
      fontWeight: 500,
      color: 'var(--text)',
      lineHeight: 1.5,
      cursor: disabled ? 'not-allowed' : 'pointer',
    };

    const descriptionStyle: React.CSSProperties = {
      fontSize: 12,
      color: 'var(--muted)',
      lineHeight: 1.4,
      marginTop: 2,
    };

    return (
      <div style={containerStyle} className={className}>
        <div style={switchWrapperStyle}>
          <input
            ref={ref}
            type="checkbox"
            role="switch"
            aria-checked={props.checked}
            id={switchId}
            disabled={disabled}
            style={{
              position: 'absolute',
              opacity: 0,
              width: '100%',
              height: '100%',
              margin: 0,
              cursor: disabled ? 'not-allowed' : 'pointer',
              zIndex: 1,
            }}
            {...props}
          />
          <div style={trackStyle} aria-hidden="true" />
          <div style={thumbStyle} aria-hidden="true" />
        </div>
        {(label || description) && (
          <div style={labelStyle}>
            {label && (
              <label htmlFor={switchId} style={labelTextStyle}>
                {label}
              </label>
            )}
            {description && <div style={descriptionStyle}>{description}</div>}
          </div>
        )}
      </div>
    );
  }
);

Switch.displayName = 'Switch';

export default Switch;

