import React from 'react';

export interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  title?: React.ReactNode;
  headerExtra?: React.ReactNode;
}

export const Card: React.FC<CardProps> = ({ title, headerExtra, children, style, ...props }) => {
  return (
    <div
      {...props}
      style={{
        background: 'var(--surface)',
        border: '1px solid var(--border)',
        borderRadius: 12,
        boxShadow: 'var(--shadow-xs, 0 1px 2px rgba(0,0,0,0.04))',
        overflow: 'hidden',
        ...style,
      }}
    >
      {(title || headerExtra) && (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: 16, borderBottom: '1px solid var(--border)' }}>
          <div style={{ fontWeight: 600, fontSize: 16, color: 'var(--text)' }}>{title}</div>
          <div>{headerExtra}</div>
        </div>
      )}
      <div style={{ padding: 16, color: 'var(--text)' }}>{children}</div>
    </div>
  );
};

export default Card;

