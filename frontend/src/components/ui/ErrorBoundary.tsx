import React, { Component, ErrorInfo, ReactNode } from 'react';
import { AlertTriangle, RefreshCw } from 'lucide-react';
import { Button } from './Button';
import { Card } from './Card';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
  onError?: (error: Error, errorInfo: ErrorInfo) => void;
}

interface State {
  hasError: boolean;
  error?: Error;
}

/**
 * ErrorBoundary component để bắt và xử lý lỗi JavaScript trong component tree
 * Hiển thị UI fallback thay vì crash toàn bộ ứng dụng
 */
export class ErrorBoundary extends Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError(error: Error): State {
    // Cập nhật state để hiển thị fallback UI
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    // Log lỗi để debug
    console.error('ErrorBoundary caught an error:', error, errorInfo);
    
    // Gọi callback nếu có
    if (this.props.onError) {
      this.props.onError(error, errorInfo);
    }
  }

  handleRetry = () => {
    this.setState({ hasError: false, error: undefined });
  };

  render() {
    if (this.state.hasError) {
      // Hiển thị custom fallback nếu có
      if (this.props.fallback) {
        return this.props.fallback;
      }

      // Hiển thị default error UI
      return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4">
          <Card className="max-w-md w-full p-6 text-center">
            <div className="flex justify-center mb-4">
              <AlertTriangle className="h-12 w-12 text-red-500" />
            </div>
            <h2 className="text-xl font-semibold text-gray-900 mb-2">
              Oops! Có lỗi xảy ra
            </h2>
            <p className="text-gray-600 mb-4">
              Đã xảy ra lỗi không mong muốn. Vui lòng thử lại hoặc liên hệ hỗ trợ nếu vấn đề vẫn tiếp tục.
            </p>
            {process.env.NODE_ENV === 'development' && this.state.error && (
              <details className="text-left mb-4 p-3 bg-gray-100 rounded text-sm">
                <summary className="cursor-pointer font-medium">Chi tiết lỗi</summary>
                <pre className="mt-2 whitespace-pre-wrap text-red-600">
                  {this.state.error.stack}
                </pre>
              </details>
            )}
            <div className="flex gap-2 justify-center">
              <Button
                onClick={this.handleRetry}
                variant="primary"
                className="flex items-center gap-2"
              >
                <RefreshCw className="h-4 w-4" />
                Thử lại
              </Button>
              <Button
                onClick={() => window.location.reload()}
                variant="outline"
              >
                Tải lại trang
              </Button>
            </div>
          </Card>
        </div>
      );
    }

    return this.props.children;
  }
}

// Hook version cho functional components
export const useErrorHandler = () => {
  const handleError = React.useCallback((error: Error, errorInfo?: ErrorInfo) => {
    console.error('Error caught by useErrorHandler:', error, errorInfo);
    // Có thể gửi lỗi đến service monitoring như Sentry
  }, []);

  return handleError;
};