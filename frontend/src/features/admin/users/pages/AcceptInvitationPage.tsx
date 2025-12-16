import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../../../../shared/ui/card';
import { Button } from '../../../../shared/ui/button';
import { Input } from '../../../../components/ui/primitives/Input';
import { useValidateInvitationToken, useAcceptInvitation } from '../hooks';
import { LoadingSpinner } from '../../../../components/shared/LoadingSpinner';
import toast from 'react-hot-toast';
import { useAuthStore } from '../../../../features/auth/store';

export const AcceptInvitationPage: React.FC = () => {
  const { token } = useParams<{ token: string }>();
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuthStore();
  
  const [name, setName] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [phone, setPhone] = useState('');
  const [jobTitle, setJobTitle] = useState('');

  const { data: validationData, isLoading: isValidating, error: validationError } = useValidateInvitationToken(
    token || '',
    !!token
  );
  const acceptMutation = useAcceptInvitation();

  // Pre-fill name if user is logged in
  useEffect(() => {
    if (isAuthenticated && user && !name) {
      setName(user.name || '');
    }
  }, [isAuthenticated, user, name]);

  // Pre-fill invitation data
  useEffect(() => {
    if (validationData) {
      if (validationData.first_name && !firstName) {
        setFirstName(validationData.first_name);
      }
      if (validationData.last_name && !lastName) {
        setLastName(validationData.last_name);
      }
    }
  }, [validationData, firstName, lastName]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!token) {
      toast.error('Invalid invitation token');
      return;
    }

    // Validate password if new user
    if (!isAuthenticated) {
      if (!name || !password || !passwordConfirmation) {
        toast.error('Please fill in all required fields');
        return;
      }

      if (password !== passwordConfirmation) {
        toast.error('Passwords do not match');
        return;
      }

      if (password.length < 8) {
        toast.error('Password must be at least 8 characters');
        return;
      }
    }

    try {
      const userData: any = {
        name: isAuthenticated ? user?.name : name,
        password: isAuthenticated ? undefined : password,
        password_confirmation: isAuthenticated ? undefined : passwordConfirmation,
        first_name: firstName || undefined,
        last_name: lastName || undefined,
        phone: phone || undefined,
        job_title: jobTitle || undefined,
      };

      const result = await acceptMutation.mutateAsync({ token, userData });
      
      toast.success(result.message || 'Invitation accepted successfully');
      
      // Redirect based on authentication status
      if (isAuthenticated) {
        // Already logged in - redirect to dashboard
        navigate('/app/dashboard');
      } else {
        // New user - redirect to login or dashboard (auto-logged in)
        navigate('/app/dashboard');
      }
    } catch (error: any) {
      const errorMessage = error?.response?.data?.error?.message || error?.message || 'Failed to accept invitation';
      toast.error(errorMessage);
    }
  };

  if (isValidating) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)]">
        <Card style={{ maxWidth: 500, width: '100%' }}>
          <CardContent className="flex items-center justify-center py-12">
            <LoadingSpinner size="lg" message="Validating invitation..." />
          </CardContent>
        </Card>
      </div>
    );
  }

  if (validationError || !validationData) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] px-4">
        <Card style={{ maxWidth: 500, width: '100%' }}>
          <CardHeader>
            <CardTitle className="text-[var(--color-semantic-danger-600)]">Invalid Invitation</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <p className="text-[var(--color-text-secondary)]">
                {validationError instanceof Error
                  ? validationError.message
                  : 'This invitation link is invalid or has expired.'}
              </p>
              <div className="flex gap-2">
                <Button variant="outline" onClick={() => navigate('/login')}>
                  Go to Login
                </Button>
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

  const isExpired = new Date(validationData.expires_at) < new Date();

  if (isExpired) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] px-4">
        <Card style={{ maxWidth: 500, width: '100%' }}>
          <CardHeader>
            <CardTitle className="text-[var(--color-semantic-warning-600)]">Invitation Expired</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <p className="text-[var(--color-text-secondary)]">
                This invitation has expired. Please contact the person who invited you to request a new invitation.
              </p>
              <div className="flex gap-2">
                <Button variant="outline" onClick={() => navigate('/login')}>
                  Go to Login
                </Button>
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

  return (
    <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-base)] px-4 py-12">
      <Card style={{ maxWidth: 600, width: '100%' }}>
        <CardHeader>
          <CardTitle>Accept Invitation</CardTitle>
          <p className="text-sm text-[var(--color-text-secondary)] mt-2">
            You've been invited to join <strong>{validationData.tenant_name}</strong> as a{' '}
            <strong>{validationData.role}</strong>
          </p>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Email (read-only) */}
            <div>
              <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                Email Address
              </label>
              <Input
                type="email"
                value={validationData.email}
                disabled
                className="bg-[var(--color-surface-muted)]"
              />
            </div>

            {/* Name (required for new users) */}
            {!isAuthenticated && (
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                  Full Name *
                </label>
                <Input
                  type="text"
                  placeholder="John Doe"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  required
                />
              </div>
            )}

            {/* Password (required for new users) */}
            {!isAuthenticated && (
              <>
                <div>
                  <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                    Password *
                  </label>
                  <Input
                    type="password"
                    placeholder="At least 8 characters"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                    minLength={8}
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                    Confirm Password *
                  </label>
                  <Input
                    type="password"
                    placeholder="Confirm your password"
                    value={passwordConfirmation}
                    onChange={(e) => setPasswordConfirmation(e.target.value)}
                    required
                    minLength={8}
                  />
                </div>
              </>
            )}

            {/* Optional fields */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                  First Name
                </label>
                <Input
                  type="text"
                  placeholder="John"
                  value={firstName}
                  onChange={(e) => setFirstName(e.target.value)}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                  Last Name
                </label>
                <Input
                  type="text"
                  placeholder="Doe"
                  value={lastName}
                  onChange={(e) => setLastName(e.target.value)}
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                  Phone
                </label>
                <Input
                  type="tel"
                  placeholder="+1 234 567 8900"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                  Job Title
                </label>
                <Input
                  type="text"
                  placeholder="Software Engineer"
                  value={jobTitle}
                  onChange={(e) => setJobTitle(e.target.value)}
                />
              </div>
            </div>

            {/* Message from inviter */}
            {validationData.message && (
              <div className="p-4 bg-[var(--color-surface-muted)] rounded-[var(--radius-md)]">
                <p className="text-sm text-[var(--color-text-secondary)]">
                  <strong>Message from inviter:</strong>
                </p>
                <p className="text-sm text-[var(--color-text-primary)] mt-1">{validationData.message}</p>
              </div>
            )}

            {/* Submit button */}
            <div className="flex gap-2 pt-4">
              <Button
                type="submit"
                variant="primary"
                loading={acceptMutation.isPending}
                disabled={acceptMutation.isPending}
                className="flex-1"
              >
                {isAuthenticated ? 'Accept Invitation' : 'Create Account & Accept'}
              </Button>
              <Button
                type="button"
                variant="outline"
                onClick={() => navigate('/')}
                disabled={acceptMutation.isPending}
              >
                Cancel
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
};

export default AcceptInvitationPage;

