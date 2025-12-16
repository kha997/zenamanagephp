import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { NoWorkspacePage } from '../NoWorkspacePage';
import { useAuthStore } from '../../../auth/store';

// Mock the auth store
vi.mock('../../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

// Mock useNavigate
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

const mockUseAuthStore = vi.mocked(useAuthStore);

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
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

describe('NoWorkspacePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockNavigate.mockClear();

    // Default mock: logout is async
    const mockLogout = vi.fn().mockResolvedValue(undefined);

    mockUseAuthStore.mockReturnValue({
      logout: mockLogout,
    } as any);
  });

  it('should render the no-workspace page with correct content', () => {
    const Wrapper = createWrapper();
    render(
      <Wrapper>
        <NoWorkspacePage />
      </Wrapper>
    );

    expect(screen.getByTestId('no-workspace-page')).toBeInTheDocument();
    expect(screen.getByText('No workspace yet')).toBeInTheDocument();
    expect(screen.getByText(/You are not currently part of any workspace/)).toBeInTheDocument();
    expect(screen.getByText(/You haven't been added to any workspace yet/)).toBeInTheDocument();
  });

  it('should have logout button with correct test id', () => {
    const Wrapper = createWrapper();
    render(
      <Wrapper>
        <NoWorkspacePage />
      </Wrapper>
    );

    const logoutButton = screen.getByTestId('no-workspace-logout');
    expect(logoutButton).toBeInTheDocument();
    expect(logoutButton).toHaveTextContent('Log out');
  });

  it('should have home button with correct test id', () => {
    const Wrapper = createWrapper();
    render(
      <Wrapper>
        <NoWorkspacePage />
      </Wrapper>
    );

    const homeButton = screen.getByTestId('no-workspace-home');
    expect(homeButton).toBeInTheDocument();
    expect(homeButton).toHaveTextContent('Go to Home');
  });

  it('should call logout and navigate to /login when logout button is clicked', async () => {
    const user = userEvent.setup();
    const mockLogout = vi.fn().mockResolvedValue(undefined);

    mockUseAuthStore.mockReturnValue({
      logout: mockLogout,
    } as any);

    const Wrapper = createWrapper();
    render(
      <Wrapper>
        <NoWorkspacePage />
      </Wrapper>
    );

    const logoutButton = screen.getByTestId('no-workspace-logout');
    await user.click(logoutButton);

    await waitFor(() => {
      expect(mockLogout).toHaveBeenCalledTimes(1);
    });

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('/login', { replace: true });
    });
  });

  it('should navigate to home when home button is clicked', async () => {
    const user = userEvent.setup();

    // Mock window.location.href
    const originalLocation = window.location;
    delete (window as any).location;
    window.location = { ...originalLocation, href: '' };

    const Wrapper = createWrapper();
    render(
      <Wrapper>
        <NoWorkspacePage />
      </Wrapper>
    );

    const homeButton = screen.getByTestId('no-workspace-home');
    await user.click(homeButton);

    // Note: window.location.href assignment triggers a full page navigation
    // In a real browser, this would navigate. In tests, we can verify the assignment
    // but the actual navigation won't happen. This is expected behavior.
    expect(window.location.href).toBe('/');

    // Restore window.location
    window.location = originalLocation;
  });

  it('should display helpful instructions', () => {
    const Wrapper = createWrapper();
    render(
      <Wrapper>
        <NoWorkspacePage />
      </Wrapper>
    );

    expect(screen.getByText(/Ask an administrator to invite you via email/)).toBeInTheDocument();
    expect(screen.getByText(/Or contact support if you believe this is an error/)).toBeInTheDocument();
  });
});

