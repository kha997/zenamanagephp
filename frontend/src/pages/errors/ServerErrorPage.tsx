import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button } from '../../components/ui/primitives/Button';
import { spacing } from '../../shared/tokens/spacing';
import { radius } from '../../shared/tokens/radius';

/**
 * ServerErrorPage - 500 Error Page
 * 
 * Displays when a server error occurs.
 */
export const ServerErrorPage: React.FC = () => {
  const navigate = useNavigate();

  const handleReload = () => {
    window.location.reload();
  };

  return (
    <div
      style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: '100vh',
        padding: spacing.xl,
        textAlign: 'center',
        background: 'var(--bg)',
        color: 'var(--text)',
      }}
    >
      <div
        style={{
          maxWidth: '500px',
          padding: spacing.xl,
        }}
      >
        <h1
          style={{
            fontSize: '72px',
            fontWeight: 700,
            marginBottom: spacing.md,
            color: 'var(--color-semantic-danger-600)',
          }}
        >
          500
        </h1>
        <h2
          style={{
            fontSize: '24px',
            fontWeight: 600,
            marginBottom: spacing.sm,
          }}
        >
          Server Error
        </h2>
        <p
          style={{
            color: 'var(--muted)',
            marginBottom: spacing.xl,
            lineHeight: 1.6,
          }}
        >
          Something went wrong on our end. Please try again in a few moments.
        </p>
        <div
          style={{
            display: 'flex',
            gap: spacing.md,
            justifyContent: 'center',
            flexWrap: 'wrap',
          }}
        >
          <Button onClick={handleReload}>
            Reload Page
          </Button>
          <Link to="/app/dashboard">
            <Button
              style={{
                backgroundColor: 'var(--accent)',
                color: '#fff',
              }}
            >
              Go to Dashboard
            </Button>
          </Link>
        </div>
      </div>
    </div>
  );
};

export default ServerErrorPage;

