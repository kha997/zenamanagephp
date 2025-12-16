import React from 'react';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  leadingIcon?: React.ReactNode;
  trailingIcon?: React.ReactNode;
  error?: string;
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ leadingIcon, trailingIcon, error, style, onFocus, onBlur, ...props }, ref) => {
  const basePadding = 12; // Base padding value in pixels
  
  const base: React.CSSProperties = {
    height: 40,
    borderRadius: 10,
    border: '1px solid var(--gray-200)',
    background: 'transparent',
    color: 'var(--text)',
    paddingTop: 0,
    paddingBottom: 0,
    paddingLeft: leadingIcon ? 36 : basePadding,
    paddingRight: trailingIcon ? 36 : basePadding,
    outline: 'none',
    width: '100%',
  };

  const wrapper: React.CSSProperties = {
    position: 'relative',
    display: 'flex',
    alignItems: 'center',
  };

  const iconStyle: React.CSSProperties = { position: 'absolute', width: 18, height: 18, color: 'var(--muted)' };

  const handleFocus = (e: React.FocusEvent<HTMLInputElement>) => {
    e.currentTarget.style.boxShadow = `0 0 0 3px var(--ring)`;
    e.currentTarget.style.borderColor = 'var(--accent)';
    onFocus?.(e);
  };

  const handleBlur = (e: React.FocusEvent<HTMLInputElement>) => {
    e.currentTarget.style.boxShadow = 'none';
    e.currentTarget.style.borderColor = error ? 'var(--gray-400)' : 'var(--gray-200)';
    onBlur?.(e);
  };

  return (
    <div style={{ width: '100%' }}>
      <div style={wrapper}>
        {leadingIcon && <span style={{ ...iconStyle, left: 10 }}>{leadingIcon}</span>}
        <input
          ref={ref}
          {...props}
          style={{
            ...base,
            borderColor: error ? 'var(--gray-400)' : 'var(--gray-200)',
            ...style,
          }}
          onFocus={handleFocus}
          onBlur={handleBlur}
        />
        {trailingIcon && <span style={{ ...iconStyle, right: 10 }}>{trailingIcon}</span>}
      </div>
      {error && <div style={{ marginTop: 6, fontSize: 12, color: 'var(--muted)' }}>{error}</div>}
    </div>
  );
});

Input.displayName = 'Input';

export default Input;

