import React from 'react';

type Variant = 'primary' | 'secondary' | 'tertiary';
type Size = 'sm' | 'md';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
}

const baseStyle: React.CSSProperties = {
  height: 40,
  padding: '0 16px',
  borderRadius: 8,
  fontWeight: 500,
  border: '1px solid transparent',
  outline: 'none',
};

const variants: Record<Variant, React.CSSProperties> = {
  primary: {
    background: 'var(--primary-button-bg)',
    color: 'var(--primary-button-text)',
    borderColor: 'var(--primary-button-bg)',
  },
  secondary: {
    background: 'transparent',
    color: 'var(--text)',
    borderColor: 'var(--border)',
  },
  tertiary: {
    background: 'transparent',
    color: 'var(--text)',
    borderColor: 'transparent',
  },
};

const sizes: Record<Size, React.CSSProperties> = {
  sm: { height: 36, padding: '0 12px' },
  md: { height: 40, padding: '0 16px' },
};

export const Button: React.FC<ButtonProps> = ({ variant = 'primary', size = 'md', style, disabled, ...props }) => {
  const st: React.CSSProperties = {
    ...baseStyle,
    ...sizes[size],
    ...variants[variant],
    opacity: disabled ? 0.6 : 1,
    cursor: disabled ? 'not-allowed' : 'pointer',
    boxShadow: '0 0 0 0 var(--ring)',
  };

  return (
    <button
      {...props}
      disabled={disabled}
      style={{ ...st, ...style }}
      onFocus={(e) => {
        e.currentTarget.style.boxShadow = `0 0 0 3px var(--ring)`;
      }}
      onBlur={(e) => {
        e.currentTarget.style.boxShadow = '0 0 0 0 var(--ring)';
      }}
    />
  );
};

export default Button;

