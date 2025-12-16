import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { InvitationLandingPage } from '../pages/InvitationLandingPage';
import { useAuthStore } from '../../auth/store';
import { usePublicInvitation, useAcceptInvitation, useDeclineInvitation } from '../hooks';
import toast from 'react-hot-toast';

// Mock the auth store
vi.mock('../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

// Mock the invitation hooks
vi.mock('../hooks', () => ({
  usePublicInvitation: vi.fn(),
  useAcceptInvitation: vi.fn(),
  useDeclineInvitation: vi.fn(),
}));

// Mock react-router-dom
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ token: 'test-token-123' }),
    useNavigate: () => vi.fn(),
  };
});

// Mock react-hot-toast
vi.mock('react-hot-toast', () => ({
  default: {
    success: vi.fn(),
    error: vi.fn(),
  },
}));

const mockUseAuthStore = vi.mocked(useAuthStore);
const mockUsePublicInvitation = vi.mocked(usePublicInvitation);
const mockUseAcceptInvitation = vi.mocked(useAcceptInvitation);
const mockUseDeclineInvitation = vi.mocked(useDeclineInvitation);

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
      mutations: {
        retry: false,
      },
    },
  });

  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>{children}</BrowserRouter>
    </QueryClientProvider>
  );
};

const mockInvitation = {
  tenant_name: 'Test Tenant',
  email: 'invited@example.com',
  role: 'member',
  status: 'pending' as const,
  is_expired: false,
};

describe('InvitationLandingPage - Flow Tests', () => {
  const mockNavigate = vi.fn();
  const mockCheckAuth = vi.fn().mockResolvedValue(undefined);
  const mockLogout = vi.fn().mockResolvedValue(undefined);
  const mockAcceptMutateAsync = vi.fn();
  const mockDeclineMutateAsync = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();

    // Default mock implementations
    vi.mock('react-router-dom', async () => {
      const actual = await vi.importActual('react-router-dom');
      return {
        ...actual,
        useParams: () => ({ token: 'test-token-123' }),
        useNavigate: () => mockNavigate,
      };
    });

    mockUseAuthStore.mockReturnValue({
      user: null,
      isAuthenticated: false,
      checkAuth: mockCheckAuth,
      logout: mockLogout,
    } as any);

    mockUsePublicInvitation.mockReturnValue({
      data: {
        success: true,
        data: mockInvitation,
      },
      isLoading: false,
      error: null,
    } as any);

    mockUseAcceptInvitation.mockReturnValue({
      mutateAsync: mockAcceptMutateAsync,
      isPending: false,
      isSuccess: false,
      isError: false,
      error: null,
    } as any);

    mockUseDeclineInvitation.mockReturnValue({
      mutateAsync: mockDeclineMutateAsync,
      isPending: false,
      isSuccess: false,
      isError: false,
      error: null,
    } as any);
  });

  describe('Loading State', () => {
    it('should show loading spinner when invitation is loading', () => {
      mockUsePublicInvitation.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <InvitationLandingPage />
        </Wrapper>
      );

      expect(screen.getByText('Loading invitation...')).toBeInTheDocument();
    });
  });

  describe('Invalid Token', () => {
    it('should show invalid token message when token is invalid', () => {
      const error = {
        code: 'TENANT_INVITE_INVALID_TOKEN',
        message: 'Invalid token',
        status: 404,
      };

      mockUsePublicInvitation.mockReturnValue({
        data: undefined,
        isLoading: false,
        error,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <InvitationLandingPage />
        </Wrapper>
      );

      expect(screen.getByText('Invitation Not Found')).toBeInTheDocument();
      expect(screen.getByText(/This invitation link is invalid or has been removed/)).toBeInTheDocument();
      expect(screen.queryByText('Accept Invitation')).not.toBeInTheDocument();
      expect(screen.queryByText('Decline')).not.toBeInTheDocument();
    });
  });

  describe('Expired Invitation', () => {
    it('should show expired message when invitation is expired', () => {
      mockUsePublicInvitation.mockReturnValue({
        data: {
          success: true,
          data: {
            ...mockInvitation,
            status: 'expired',
            is_expired: true,
          },
        },
        isLoading: false,
        error: null,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <InvitationLandingPage />
        </Wrapper>
      );

      expect(screen.getByText('Invitation Expired')).toBeInTheDocument();
      expect(screen.getByText(/This invitation has expired/)).toBeInTheDocument();
      expect(screen.queryByText('Accept Invitation')).not.toBeInTheDocument();
      expect(screen.queryByText('Decline')).not.toBeInTheDocument();
    });
  });

  describe('Already Accepted', () => {
    it('should show already accepted message when invitation is accepted', () => {
      mockUsePublicInvitation.mockReturnValue({
        data: {
          success: true,
          data: {
            ...mockInvitation,
            status: 'accepted',
          },
        },
        isLoading: false,
        error: null,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <InvitationLandingPage />
        </Wrapper>
      );

      expect(screen.getByText('Invitation Already Accepted')).toBeInTheDocument();
      expect(screen.getByText(/You have already accepted this invitation/)).toBeInTheDocument();
      expect(screen.queryByText('Accept Invitation')).not.toBeInTheDocument();
      expect(screen.queryByText('Decline')).not.toBeInTheDocument();
    });
  });

  describe('Already Declined', () => {
    it('should show declined message when invitation is declined', () => {
      mockUsePublicInvitation.mockReturnValue({
        data: {
          success: true,
          data: {
            ...mockInvitation,
            status: 'declined',
          },
        },
        isLoading: false,
        error: null,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <InvitationLandingPage />
        </Wrapper>
      );

      expect(screen.getByText('Invitation Declined')).toBeInTheDocument();
      expect(screen.getByText(/You have declined this invitation/)).toBeInTheDocument();
      expect(screen.queryByText('Accept Invitation')).not.toBeInTheDocument();
      expect(screen.queryByText('Decline')).not.toBeInTheDocument();
    });
  });

  describe('Revoked Invitation', () => {
    it('should show revoked message when invitation is revoked', () => {
      mockUsePublicInvitation.mockReturnValue({
        data: {
          success: true,
          data: {
            ...mockInvitation,
            status: 'revoked',
          },
        },
        isLoading: false,
        error: null,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <InvitationLandingPage />
        </Wrapper>
      );

      expect(screen.getByText('Invitation Revoked')).toBeInTheDocument();
      expect(screen.getByText(/This invitation has been revoked/)).toBeInTheDocument();
      expect(screen.queryByText('Accept Invitation')).not.toBeInTheDocument();
      expect(screen.queryByText('Decline')).not.toBeInTheDocument();
    });
  });

  describe('Not Logged In - Pending Invitation', () => {
    it('should show invitation info and login/signup buttons when not logged in', () => {
      mockUseAuthStore.mockReturnValue({
        user: null,
        isAuthenticated: false,
        checkAuth: mockCheckAuth,
        logout: mockLogout,
      } as any);

      mockUsePublicInvitation.mockReturnValue({
        data: {
          success: true,
          data: mockInvitation,
        },
        isLoading: false,
        error: null,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <InvitationLandingPage />
        </Wrapper>
      );

      expect(screen.getByText("You've Been Invited!")).toBeInTheDocument();
      expect(screen.getByText(/You've been invited to join Test Tenant/)).toBeInTheDocument();
      expect(screen.getByText('invited@example.com')).toBeInTheDocument();
      expect(screen.getByText('Log In')).toBeInTheDocument();
      expect(screen.getByText('Create Account')).toBeInTheDocument();
      expect(screen.queryByText('Accept Invitation')).not.toBeInTheDocument();
      expect(screen.queryByText('Decline')).not.toBeInTheDocument();
    });
  });

  describe('Logged In - Email Mismatch', () => {
    it('should show email mismatch warning when logged in with different email', () => {
      mockUseAuthStore.mockReturnValue({
        user: { id: '1', email: 'other@example.com', name: 'Other User' },
        isAuthenticated: true,
        checkAuth: mockCheckAuth,
        logout: mockLogout,
      } as any);

      mockUsePublicInvitation.mockReturnValue({
        data: {
          success: true,
          data: mockInvitation,
        },
        isLoading: false,
        error: null,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <InvitationLandingPage />
        </Wrapper>
      );

      expect(screen.getByText('Email Mismatch')).toBeInTheDocument();
      expect(screen.getByText(/You are logged in as: other@example.com/)).toBeInTheDocument();
      expect(screen.getByText(/This invitation is for: invited@example.com/)).toBeInTheDocument();
      expect(screen.getByText(/You need to log in with the invited email address/)).toBeInTheDocument();
      expect(screen.getByText('Log Out and Log In with Invited Email')).toBeInTheDocument();
      expect(screen.queryByText('Accept Invitation')).not.toBeInTheDocument();
      expect(screen.queryByText('Decline')).not.toBeInTheDocument();
    });
  });

  describe('Logged In - Email Matches - Pending Invitation', () => {
    it('should show accept and decline buttons when logged in with matching email', () => {
      mockUseAuthStore.mockReturnValue({
        user: { id: '1', email: 'invited@example.com', name: 'Invited User' },
        isAuthenticated: true,
        checkAuth: mockCheckAuth,
        logout: mockLogout,
      } as any);

      mockUsePublicInvitation.mockReturnValue({
        data: {
          success: true,
          data: mockInvitation,
        },
        isLoading: false,
        error: null,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <InvitationLandingPage />
        </Wrapper>
      );

      expect(screen.getByText('Accept Invitation')).toBeInTheDocument();
      expect(screen.getByText('Decline')).toBeInTheDocument();
      expect(screen.getByText(/You've been invited to join Test Tenant/)).toBeInTheDocument();
    });

    it('should call accept mutation when accept button is clicked', async () => {
      const user = userEvent.setup();
      mockAcceptMutateAsync.mockResolvedValue({ success: true });

      mockUseAuthStore.mockReturnValue({
        user: { id: '1', email: 'invited@example.com', name: 'Invited User' },
        isAuthenticated: true,
        checkAuth: mockCheckAuth,
        logout: mockLogout,
      } as any);

      mockUsePublicInvitation.mockReturnValue({
        data: {
          success: true,
          data: mockInvitation,
        },
        isLoading: false,
        error: null,
      } as any);

      // Update mock to return success state after mutation
      mockUseAcceptInvitation.mockReturnValue({
        mutateAsync: mockAcceptMutateAsync,
        isPending: false,
        isSuccess: true,
        isError: false,
        error: null,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <InvitationLandingPage />
        </Wrapper>
      );

      const acceptButton = screen.getByText('Accept Invitation');
      await user.click(acceptButton);

      await waitFor(() => {
        expect(mockAcceptMutateAsync).toHaveBeenCalledWith('test-token-123');
      });
    });

    it('should call decline mutation when decline button is clicked', async () => {
      const user = userEvent.setup();
      mockDeclineMutateAsync.mockResolvedValue({ success: true });

      mockUseAuthStore.mockReturnValue({
        user: { id: '1', email: 'invited@example.com', name: 'Invited User' },
        isAuthenticated: true,
        checkAuth: mockCheckAuth,
        logout: mockLogout,
      } as any);

      mockUsePublicInvitation.mockReturnValue({
        data: {
          success: true,
          data: mockInvitation,
        },
        isLoading: false,
        error: null,
      } as any);

      // Update mock to return success state after mutation
      mockUseDeclineInvitation.mockReturnValue({
        mutateAsync: mockDeclineMutateAsync,
        isPending: false,
        isSuccess: true,
        isError: false,
        error: null,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <InvitationLandingPage />
        </Wrapper>
      );

      const declineButton = screen.getByText('Decline');
      await user.click(declineButton);

      await waitFor(() => {
        expect(mockDeclineMutateAsync).toHaveBeenCalledWith('test-token-123');
      });
    });
  });

  describe('Error Handling', () => {
    it('should show error toast when accept fails with email mismatch', async () => {
      const error = {
        code: 'TENANT_INVITE_EMAIL_MISMATCH',
        message: 'Email mismatch',
        status: 422,
      };

      mockUseAuthStore.mockReturnValue({
        user: { id: '1', email: 'invited@example.com', name: 'Invited User' },
        isAuthenticated: true,
        checkAuth: mockCheckAuth,
        logout: mockLogout,
      } as any);

      mockUsePublicInvitation.mockReturnValue({
        data: {
          success: true,
          data: mockInvitation,
        },
        isLoading: false,
        error: null,
      } as any);

      mockUseAcceptInvitation.mockReturnValue({
        mutateAsync: mockAcceptMutateAsync.mockRejectedValue(error),
        isPending: false,
        isSuccess: false,
        isError: true,
        error,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <InvitationLandingPage />
        </Wrapper>
      );

      await waitFor(() => {
        expect(toast.error).toHaveBeenCalledWith(
          'This invitation is for a different email address. Please log in with the invited email.'
        );
      });
    });

    it('should show error toast when accept fails with expired invitation', async () => {
      const error = {
        code: 'TENANT_INVITE_EXPIRED',
        message: 'Expired',
        status: 422,
      };

      mockUseAuthStore.mockReturnValue({
        user: { id: '1', email: 'invited@example.com', name: 'Invited User' },
        isAuthenticated: true,
        checkAuth: mockCheckAuth,
        logout: mockLogout,
      } as any);

      mockUsePublicInvitation.mockReturnValue({
        data: {
          success: true,
          data: mockInvitation,
        },
        isLoading: false,
        error: null,
      } as any);

      mockUseAcceptInvitation.mockReturnValue({
        mutateAsync: mockAcceptMutateAsync.mockRejectedValue(error),
        isPending: false,
        isSuccess: false,
        isError: true,
        error,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <InvitationLandingPage />
        </Wrapper>
      );

      await waitFor(() => {
        expect(toast.error).toHaveBeenCalledWith('This invitation has expired.');
      });
    });
  });
});

