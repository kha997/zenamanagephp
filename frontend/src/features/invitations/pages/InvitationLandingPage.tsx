import React, { useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useAuthStore } from '../../auth/store';
import { usePublicInvitation, useAcceptInvitation, useDeclineInvitation } from '../hooks';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../shared/ui/button';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';
import toast from 'react-hot-toast';
import type { ApiError } from '../../../shared/api/client';

/**
 * InvitationLandingPage
 * 
 * Public invitation landing page for Round 21.
 * Handles all invitation states: not logged in, email mismatch, email match, expired, accepted, declined, revoked.
 * 
 * Route: /invite/:token
 */
export const InvitationLandingPage: React.FC = () => {
  const { token } = useParams<{ token: string }>();
  const navigate = useNavigate();
  const { user, isAuthenticated, checkAuth, logout } = useAuthStore();

  const {
    data: invitationResponse,
    isLoading,
    error: invitationError,
  } = usePublicInvitation(token, { enabled: !!token });

  const acceptMutation = useAcceptInvitation();
  const declineMutation = useDeclineInvitation();

  // Derived state
  const invitation = invitationResponse?.data;
  const isLoggedIn = isAuthenticated && !!user;
  const invitedEmail = invitation?.email || '';
  const currentEmail = user?.email || '';
  const emailMatches = isLoggedIn && currentEmail.toLowerCase() === invitedEmail.toLowerCase();
  const isPending = invitation?.status === 'pending';
  const isExpired = invitation?.is_expired || invitation?.status === 'expired';
  const isAccepted = invitation?.status === 'accepted';
  const isDeclined = invitation?.status === 'declined';
  const isRevoked = invitation?.status === 'revoked';

  // Handle accept success
  useEffect(() => {
    if (acceptMutation.isSuccess) {
      toast.success('Invitation accepted successfully!');
      
      // Refresh auth context to get updated tenant list
      checkAuth().then(() => {
        // Redirect to dashboard
        navigate('/app/dashboard', { replace: true });
      }).catch(() => {
        // Even if checkAuth fails, redirect anyway
        navigate('/app/dashboard', { replace: true });
      });
    }
  }, [acceptMutation.isSuccess, navigate, checkAuth]);

  // Handle accept error
  useEffect(() => {
    if (acceptMutation.isError) {
      const error = acceptMutation.error as ApiError;
      const errorCode = error?.code || '';
      const errorMessage = error?.message || 'Failed to accept invitation';

      // Map error codes to user-friendly messages
      if (errorCode === 'TENANT_INVITE_EMAIL_MISMATCH') {
        toast.error('This invitation is for a different email address. Please log in with the invited email.');
      } else if (errorCode === 'TENANT_INVITE_EXPIRED') {
        toast.error('This invitation has expired.');
      } else if (errorCode === 'TENANT_INVITE_ALREADY_ACCEPTED') {
        toast.error('This invitation has already been accepted.');
      } else if (errorCode === 'TENANT_INVITE_ALREADY_DECLINED') {
        toast.error('This invitation has already been declined.');
      } else if (errorCode === 'TENANT_INVITE_ALREADY_REVOKED') {
        toast.error('This invitation has been revoked.');
      } else if (errorCode === 'TENANT_INVITE_INVALID_TOKEN') {
        toast.error('Invalid invitation token.');
      } else {
        toast.error(errorMessage);
      }
    }
  }, [acceptMutation.isError, acceptMutation.error]);

  // Handle decline success
  useEffect(() => {
    if (declineMutation.isSuccess) {
      toast.success('You have declined this invitation.');
    }
  }, [declineMutation.isSuccess]);

  // Handle decline error
  useEffect(() => {
    if (declineMutation.isError) {
      const error = declineMutation.error as ApiError;
      const errorCode = error?.code || '';
      const errorMessage = error?.message || 'Failed to decline invitation';

      // Map error codes to user-friendly messages
      if (errorCode === 'TENANT_INVITE_EXPIRED') {
        toast.error('This invitation has expired.');
      } else if (errorCode === 'TENANT_INVITE_ALREADY_ACCEPTED') {
        toast.error('This invitation has already been accepted.');
      } else if (errorCode === 'TENANT_INVITE_ALREADY_DECLINED') {
        toast.error('This invitation has already been declined.');
      } else if (errorCode === 'TENANT_INVITE_ALREADY_REVOKED') {
        toast.error('This invitation has been revoked.');
      } else if (errorCode === 'TENANT_INVITE_INVALID_TOKEN') {
        toast.error('Invalid invitation token.');
      } else {
        toast.error(errorMessage);
      }
    }
  }, [declineMutation.isError, declineMutation.error]);

  const handleAccept = async () => {
    if (!token) {
      toast.error('Invalid invitation token');
      return;
    }

    try {
      await acceptMutation.mutateAsync(token);
    } catch (error) {
      // Error handling is done in useEffect above
    }
  };

  const handleDecline = async () => {
    if (!token) {
      toast.error('Invalid invitation token');
      return;
    }

    try {
      await declineMutation.mutateAsync(token);
    } catch (error) {
      // Error handling is done in useEffect above
    }
  };

  const handleLogout = async () => {
    await logout();
    navigate('/login', { state: { from: { pathname: `/invite/${token}` } } });
  };

  // Loading state
  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] px-4">
        <Card style={{ maxWidth: 500, width: '100%' }}>
          <CardContent className="flex items-center justify-center py-12">
            <LoadingSpinner size="lg" message="Loading invitation..." />
          </CardContent>
        </Card>
      </div>
    );
  }

  // Error state - invalid token or network error
  if (invitationError || !invitation) {
    const error = invitationError as ApiError;
    const errorCode = error?.code || '';
    const isInvalidToken = errorCode === 'TENANT_INVITE_INVALID_TOKEN' || !invitation;

    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] px-4">
        <Card style={{ maxWidth: 500, width: '100%' }}>
          <CardHeader>
            <CardTitle className="text-[var(--color-semantic-danger-600)]">
              {isInvalidToken ? 'Invitation Not Found' : 'Error Loading Invitation'}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <p className="text-[var(--color-text-secondary)]">
                {isInvalidToken
                  ? 'This invitation link is invalid or has been removed.'
                  : error?.message || 'Unable to load invitation details. Please try again later.'}
              </p>
              <div className="flex gap-2">
                {!isLoggedIn && (
                  <Button variant="outline" onClick={() => navigate('/login')}>
                    Go to Login
                  </Button>
                )}
                <Button onClick={() => navigate('/')}>
                  Go to Home
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  // Expired state
  if (isExpired) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] px-4">
        <Card style={{ maxWidth: 500, width: '100%' }}>
          <CardHeader>
            <CardTitle className="text-[var(--color-semantic-warning-600)]">
              Invitation Expired
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <p className="text-[var(--color-text-secondary)]">
                This invitation has expired. Please contact the person who invited you to request a new invitation.
              </p>
              <div className="flex gap-2">
                {!isLoggedIn && (
                  <Button variant="outline" onClick={() => navigate('/login')}>
                    Go to Login
                  </Button>
                )}
                <Button onClick={() => navigate('/')}>
                  Go to Home
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  // Already accepted state
  if (isAccepted) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] px-4">
        <Card style={{ maxWidth: 500, width: '100%' }}>
          <CardHeader>
            <CardTitle className="text-[var(--color-semantic-success-600)]">
              Invitation Already Accepted
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <p className="text-[var(--color-text-secondary)]">
                You have already accepted this invitation to join <strong>{invitation.tenant_name}</strong>.
              </p>
              <div className="flex gap-2">
                {isLoggedIn ? (
                  <Button onClick={() => navigate('/app/dashboard')}>
                    Go to Dashboard
                  </Button>
                ) : (
                  <Button onClick={() => navigate('/login')}>
                    Go to Login
                  </Button>
                )}
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  // Already declined state
  if (isDeclined) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] px-4">
        <Card style={{ maxWidth: 500, width: '100%' }}>
          <CardHeader>
            <CardTitle>Invitation Declined</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <p className="text-[var(--color-text-secondary)]">
                You have declined this invitation to join <strong>{invitation.tenant_name}</strong>.
              </p>
              <div className="flex gap-2">
                {isLoggedIn ? (
                  <Button onClick={() => navigate('/app/dashboard')}>
                    Go to Dashboard
                  </Button>
                ) : (
                  <Button onClick={() => navigate('/login')}>
                    Go to Login
                  </Button>
                )}
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  // Revoked state
  if (isRevoked) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] px-4">
        <Card style={{ maxWidth: 500, width: '100%' }}>
          <CardHeader>
            <CardTitle className="text-[var(--color-semantic-danger-600)]">
              Invitation Revoked
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <p className="text-[var(--color-text-secondary)]">
                This invitation has been revoked by the tenant owner. Please contact them if you believe this is an error.
              </p>
              <div className="flex gap-2">
                {!isLoggedIn && (
                  <Button variant="outline" onClick={() => navigate('/login')}>
                    Go to Login
                  </Button>
                )}
                <Button onClick={() => navigate('/')}>
                  Go to Home
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  // Pending invitation - show different UI based on login status and email match
  if (isPending) {
    // Not logged in
    if (!isLoggedIn) {
      return (
        <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] px-4 py-12">
          <Card style={{ maxWidth: 600, width: '100%' }}>
            <CardHeader>
              <CardTitle>You've Been Invited!</CardTitle>
              <p className="text-sm text-[var(--color-text-secondary)] mt-2">
                You've been invited to join <strong>{invitation.tenant_name}</strong> as a{' '}
                <strong>{invitation.role}</strong>.
              </p>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                <div className="p-4 bg-[var(--color-surface-muted)] rounded-[var(--radius-md)]">
                  <p className="text-sm text-[var(--color-text-secondary)]">
                    <strong>Invited email:</strong> {invitation.email}
                  </p>
                </div>
                <p className="text-sm text-[var(--color-text-secondary)]">
                  Please log in or create an account to accept this invitation.
                </p>
                <div className="flex gap-2 pt-4">
                  <Button
                    variant="primary"
                    onClick={() => navigate('/login', { state: { from: { pathname: `/invite/${token}` } } })}
                    className="flex-1"
                  >
                    Log In
                  </Button>
                  <Button
                    variant="outline"
                    onClick={() => navigate('/register', { state: { email: invitation.email } })}
                    className="flex-1"
                  >
                    Create Account
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      );
    }

    // Logged in but email mismatch
    if (!emailMatches) {
      return (
        <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] px-4 py-12">
          <Card style={{ maxWidth: 600, width: '100%' }}>
            <CardHeader>
              <CardTitle>Email Mismatch</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                <div className="p-4 bg-[var(--color-surface-muted)] rounded-[var(--radius-md)]">
                  <p className="text-sm text-[var(--color-text-secondary)] mb-2">
                    <strong>You are logged in as:</strong> {currentEmail}
                  </p>
                  <p className="text-sm text-[var(--color-text-secondary)]">
                    <strong>This invitation is for:</strong> {invitedEmail}
                  </p>
                </div>
                <div className="p-4 bg-[var(--color-semantic-warning-50)] border border-[var(--color-semantic-warning-200)] rounded-[var(--radius-md)]">
                  <p className="text-sm text-[var(--color-semantic-warning-700)]">
                    You need to log in with the invited email address to accept this invitation.
                  </p>
                </div>
                <p className="text-sm text-[var(--color-text-secondary)]">
                  You've been invited to join <strong>{invitation.tenant_name}</strong> as a{' '}
                  <strong>{invitation.role}</strong>.
                </p>
                <div className="flex gap-2 pt-4">
                  <Button
                    variant="primary"
                    onClick={handleLogout}
                    className="flex-1"
                  >
                    Log Out and Log In with Invited Email
                  </Button>
                  <Button
                    variant="outline"
                    onClick={() => navigate('/app/dashboard')}
                  >
                    Go to Dashboard
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      );
    }

    // Logged in and email matches - can accept/decline
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] px-4 py-12">
        <Card style={{ maxWidth: 600, width: '100%' }}>
          <CardHeader>
            <CardTitle>Accept Invitation</CardTitle>
            <p className="text-sm text-[var(--color-text-secondary)] mt-2">
              You've been invited to join <strong>{invitation.tenant_name}</strong> as a{' '}
              <strong>{invitation.role}</strong>.
            </p>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="p-4 bg-[var(--color-surface-muted)] rounded-[var(--radius-md)]">
                <p className="text-sm text-[var(--color-text-secondary)]">
                  <strong>Invited email:</strong> {invitation.email}
                </p>
              </div>
              <div className="flex gap-2 pt-4">
                <Button
                  variant="primary"
                  onClick={handleAccept}
                  loading={acceptMutation.isPending}
                  disabled={acceptMutation.isPending || declineMutation.isPending}
                  className="flex-1"
                >
                  Accept Invitation
                </Button>
                <Button
                  variant="outline"
                  onClick={handleDecline}
                  loading={declineMutation.isPending}
                  disabled={acceptMutation.isPending || declineMutation.isPending}
                  className="flex-1"
                >
                  Decline
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  // Fallback - should not reach here
  return (
    <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] px-4">
      <Card style={{ maxWidth: 500, width: '100%' }}>
        <CardContent className="py-12">
          <p className="text-[var(--color-text-secondary)] text-center">
            Unknown invitation state. Please contact support.
          </p>
        </CardContent>
      </Card>
    </div>
  );
};

export default InvitationLandingPage;

