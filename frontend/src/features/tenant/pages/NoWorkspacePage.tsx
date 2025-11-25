import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { useAuthStore } from '../../auth/store';

/**
 * NoWorkspacePage - Displays when user has no tenants
 * 
 * Shows a clear message explaining that the user is not part of any workspace,
 * with CTAs to go home or logout.
 */
export const NoWorkspacePage: React.FC = () => {
  const navigate = useNavigate();
  const { logout } = useAuthStore();

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  const handleGoHome = () => {
    // Navigate to root or marketing page if available
    window.location.href = '/';
  };

  return (
    <Container>
      <div
        data-testid="no-workspace-page"
        style={{
          display: 'flex',
          justifyContent: 'center',
          alignItems: 'center',
          minHeight: '60vh',
          padding: '2rem 0',
        }}
      >
        <Card style={{ maxWidth: '500px', width: '100%' }}>
          <CardHeader>
            <CardTitle>No workspace yet</CardTitle>
            <CardDescription>
              You are not currently part of any workspace
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              <p style={{ color: 'var(--color-text-muted)', fontSize: '14px', lineHeight: '1.6' }}>
                You haven't been added to any workspace yet. To get started:
              </p>
              <ul style={{ color: 'var(--color-text-muted)', fontSize: '14px', lineHeight: '1.8', paddingLeft: '1.5rem' }}>
                <li>Ask an administrator to invite you via email</li>
                <li>Or contact support if you believe this is an error</li>
              </ul>
              <div style={{ display: 'flex', gap: '0.75rem', marginTop: '1rem', flexWrap: 'wrap' }}>
                <Button
                  variant="primary"
                  onClick={handleGoHome}
                  data-testid="no-workspace-home"
                >
                  Go to Home
                </Button>
                <Button
                  variant="secondary"
                  onClick={handleLogout}
                  data-testid="no-workspace-logout"
                >
                  Log out
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </Container>
  );
};

export default NoWorkspacePage;

