import React, { Component, ErrorInfo, ReactNode } from 'react';
import { Button } from '../ui/primitives/Button';
import { spacing } from '../../shared/tokens/spacing';
import { radius } from '../../shared/tokens/radius';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
  onError?: (error: Error, errorInfo: ErrorInfo) => void;
}

interface State {
  hasError: boolean;
  error: Error | null;
}

/**
 * ErrorBoundary - Catches React errors and displays fallback UI
 * 
 * Follows Apple-style design spec with tokens and spacing.
 */
export class ErrorBoundary extends Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    // Log error to monitoring service
    console.error('ErrorBoundary caught an error:', error, errorInfo);
    
    if (this.props.onError) {
      this.props.onError(error, errorInfo);
    }
  }

  handleReset = () => {
    this.setState({ hasError: false, error: null });
  };

  render() {
    if (this.state.hasError) {
      if (this.props.fallback) {
        return this.props.fallback;
      }

      return (
        <div
          style={{
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            minHeight: '400px',
            padding: spacing.xl,
            textAlign: 'center',
          }}
          data-testid="error-boundary"
        >
          <div
            style={{
              maxWidth: '500px',
              padding: spacing.xl,
              backgroundColor: 'var(--surface)',
              border: '1px solid var(--border)',
              borderRadius: radius.lg,
            }}
          >
            <h2 style={{ fontSize: '24px', fontWeight: 600, marginBottom: spacing.md }}>
              Something went wrong
            </h2>
            <p style={{ color: 'var(--muted)', marginBottom: spacing.lg }}>
              {this.state.error?.message || 'An unexpected error occurred'}
            </p>
            {process.env.NODE_ENV === 'development' && this.state.error && (
              <pre
                style={{
                  padding: spacing.md,
                  backgroundColor: 'var(--muted-surface)',
                  borderRadius: radius.sm,
                  fontSize: '12px',
                  textAlign: 'left',
                  overflow: 'auto',
                  marginBottom: spacing.lg,
                }}
              >
                {this.state.error.stack}
              </pre>
            )}
            <div className="flex gap-2 justify-center">
              <Button onClick={this.handleReset}>Try Again</Button>
              <Button
                onClick={() => (window.location.href = '/app')}
                style={{ backgroundColor: 'var(--muted-surface)' }}
              >
                Go Home
              </Button>
            </div>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

