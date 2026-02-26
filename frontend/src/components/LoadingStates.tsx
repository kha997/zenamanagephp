import React from 'react'
import { motion } from 'framer-motion'
import { Card, CardContent } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { 
  Loader2, 
  AlertTriangle, 
  RefreshCw, 
  WifiOff,
  Server,
  Database
} from 'lucide-react'
import { pulseAnimation, spinAnimation } from '../utils/animations'

interface LoadingSpinnerProps {
  size?: 'sm' | 'md' | 'lg'
  text?: string
  className?: string
}

export const LoadingSpinner: React.FC<LoadingSpinnerProps> = ({ 
  size = 'md', 
  text,
  className = '' 
}) => {
  const sizeClasses = {
    sm: 'h-4 w-4',
    md: 'h-6 w-6',
    lg: 'h-8 w-8'
  }

  return (
    <motion.div 
      className={`flex items-center justify-center gap-2 ${className}`}
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      transition={{ duration: 0.3 }}
    >
      <motion.div
        animate={spinAnimation.animate}
        className={`${sizeClasses[size]} text-blue-500`}
      >
        <Loader2 className="w-full h-full" />
      </motion.div>
      {text && (
        <motion.span 
          className="text-gray-600"
          initial={{ opacity: 0, x: -10 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ delay: 0.2 }}
        >
          {text}
        </motion.span>
      )}
    </motion.div>
  )
}

interface ErrorBoundaryProps {
  children: React.ReactNode
  fallback?: React.ReactNode
}

interface ErrorBoundaryState {
  hasError: boolean
  error?: Error
}

export class ErrorBoundary extends React.Component<ErrorBoundaryProps, ErrorBoundaryState> {
  constructor(props: ErrorBoundaryProps) {
    super(props)
    this.state = { hasError: false }
  }

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { hasError: true, error }
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
    console.error('Error caught by boundary:', error, errorInfo)
  }

  render() {
    if (this.state.hasError) {
      return this.props.fallback || <ErrorFallback error={this.state.error} />
    }

    return this.props.children
  }
}

interface ErrorFallbackProps {
  error?: Error
  onRetry?: () => void
}

export const ErrorFallback: React.FC<ErrorFallbackProps> = ({ error, onRetry }) => {
  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.9 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={{ duration: 0.3 }}
    >
      <Card className="border-red-200 bg-red-50">
        <CardContent className="p-6 text-center">
          <motion.div
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            transition={{ delay: 0.2, type: 'spring', stiffness: 200 }}
          >
            <AlertTriangle className="h-12 w-12 mx-auto mb-4 text-red-500" />
          </motion.div>
          <motion.h3 
            className="text-lg font-medium text-red-900 mb-2"
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
          >
            Something went wrong
          </motion.h3>
          <motion.p 
            className="text-red-700 mb-4"
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.4 }}
          >
            {error?.message || 'An unexpected error occurred'}
          </motion.p>
          {onRetry && (
            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.5 }}
            >
              <Button onClick={onRetry} variant="outline" className="text-red-700 border-red-300">
                <RefreshCw className="h-4 w-4 mr-2" />
                Try Again
              </Button>
            </motion.div>
          )}
        </CardContent>
      </Card>
    </motion.div>
  )
}

interface ErrorMessageProps {
  error: string | Error
  onRetry?: () => void
  className?: string
}

export const ErrorMessage: React.FC<ErrorMessageProps> = ({ 
  error, 
  onRetry, 
  className = '' 
}) => {
  const errorMessage = typeof error === 'string' ? error : error.message

  return (
    <div className={`flex items-center justify-center p-4 ${className}`}>
      <Card className="border-red-200 bg-red-50 w-full max-w-md">
        <CardContent className="p-4">
          <div className="flex items-center gap-3">
            <AlertTriangle className="h-5 w-5 text-red-500 flex-shrink-0" />
            <div className="flex-1">
              <p className="text-red-800 text-sm">{errorMessage}</p>
            </div>
            {onRetry && (
              <Button 
                onClick={onRetry} 
                size="sm" 
                variant="outline"
                className="text-red-700 border-red-300"
              >
                <RefreshCw className="h-3 w-3" />
              </Button>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

interface LoadingStateProps {
  loading: boolean
  error?: string | Error | null
  onRetry?: () => void
  children: React.ReactNode
  loadingText?: string
  className?: string
}

export const LoadingState: React.FC<LoadingStateProps> = ({
  loading,
  error,
  onRetry,
  children,
  loadingText = 'Loading...',
  className = ''
}) => {
  if (loading) {
    return (
      <div className={`flex items-center justify-center p-8 ${className}`}>
        <LoadingSpinner size="lg" text={loadingText} />
      </div>
    )
  }

  if (error) {
    return (
      <ErrorMessage 
        error={error} 
        onRetry={onRetry}
        className={className}
      />
    )
  }

  return <>{children}</>
}

interface EmptyStateProps {
  title: string
  description?: string
  icon?: React.ReactNode
  action?: React.ReactNode
  className?: string
}

export const EmptyState: React.FC<EmptyStateProps> = ({
  title,
  description,
  icon,
  action,
  className = ''
}) => {
  return (
    <div className={`text-center py-12 ${className}`}>
      {icon && (
        <div className="mx-auto mb-4 text-gray-400">
          {icon}
        </div>
      )}
      <h3 className="text-lg font-medium text-gray-900 mb-2">{title}</h3>
      {description && (
        <p className="text-gray-500 mb-6 max-w-sm mx-auto">{description}</p>
      )}
      {action && <div>{action}</div>}
    </div>
  )
}

interface ConnectionErrorProps {
  onRetry?: () => void
  className?: string
}

export const ConnectionError: React.FC<ConnectionErrorProps> = ({ 
  onRetry, 
  className = '' 
}) => {
  return (
    <Card className={`border-red-200 bg-red-50 ${className}`}>
      <CardContent className="p-6 text-center">
        <WifiOff className="h-12 w-12 mx-auto mb-4 text-red-500" />
        <h3 className="text-lg font-medium text-red-900 mb-2">Connection Error</h3>
        <p className="text-red-700 mb-4">
          Unable to connect to the server. Please check your internet connection.
        </p>
        {onRetry && (
          <Button onClick={onRetry} variant="outline" className="text-red-700 border-red-300">
            <RefreshCw className="h-4 w-4 mr-2" />
            Retry Connection
          </Button>
        )}
      </CardContent>
    </Card>
  )
}

interface ServerErrorProps {
  onRetry?: () => void
  className?: string
}

export const ServerError: React.FC<ServerErrorProps> = ({ 
  onRetry, 
  className = '' 
}) => {
  return (
    <Card className={`border-red-200 bg-red-50 ${className}`}>
      <CardContent className="p-6 text-center">
        <Server className="h-12 w-12 mx-auto mb-4 text-red-500" />
        <h3 className="text-lg font-medium text-red-900 mb-2">Server Error</h3>
        <p className="text-red-700 mb-4">
          The server encountered an error. Please try again later.
        </p>
        {onRetry && (
          <Button onClick={onRetry} variant="outline" className="text-red-700 border-red-300">
            <RefreshCw className="h-4 w-4 mr-2" />
            Try Again
          </Button>
        )}
      </CardContent>
    </Card>
  )
}

interface DatabaseErrorProps {
  onRetry?: () => void
  className?: string
}

export const DatabaseError: React.FC<DatabaseErrorProps> = ({ 
  onRetry, 
  className = '' 
}) => {
  return (
    <Card className={`border-red-200 bg-red-50 ${className}`}>
      <CardContent className="p-6 text-center">
        <Database className="h-12 w-12 mx-auto mb-4 text-red-500" />
        <h3 className="text-lg font-medium text-red-900 mb-2">Database Error</h3>
        <p className="text-red-700 mb-4">
          Unable to access the database. Please contact support if this persists.
        </p>
        {onRetry && (
          <Button onClick={onRetry} variant="outline" className="text-red-700 border-red-300">
            <RefreshCw className="h-4 w-4 mr-2" />
            Retry
          </Button>
        )}
      </CardContent>
    </Card>
  )
}

// Skeleton loaders with animations
export const SkeletonCard: React.FC<{ className?: string }> = ({ className = '' }) => (
  <motion.div
    initial={{ opacity: 0 }}
    animate={{ opacity: 1 }}
    transition={{ duration: 0.3 }}
  >
    <Card className={`overflow-hidden ${className}`}>
      <CardContent className="p-6">
        <div className="space-y-3">
          <motion.div 
            className="h-4 bg-gray-200 rounded w-3/4"
            animate={pulseAnimation.animate}
          />
          <motion.div 
            className="h-3 bg-gray-200 rounded w-1/2"
            animate={pulseAnimation.animate}
            transition={{ delay: 0.1 }}
          />
          <motion.div 
            className="h-3 bg-gray-200 rounded w-2/3"
            animate={pulseAnimation.animate}
            transition={{ delay: 0.2 }}
          />
        </div>
      </CardContent>
    </Card>
  </motion.div>
)

export const SkeletonTable: React.FC<{ rows?: number; className?: string }> = ({ 
  rows = 5, 
  className = '' 
}) => (
  <div className={`animate-pulse ${className}`}>
    <div className="space-y-3">
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="flex space-x-4">
          <div className="h-4 bg-gray-200 rounded w-1/4"></div>
          <div className="h-4 bg-gray-200 rounded w-1/4"></div>
          <div className="h-4 bg-gray-200 rounded w-1/4"></div>
          <div className="h-4 bg-gray-200 rounded w-1/4"></div>
        </div>
      ))}
    </div>
  </div>
)

export const SkeletonList: React.FC<{ items?: number; className?: string }> = ({ 
  items = 3, 
  className = '' 
}) => (
  <div className={`space-y-3 ${className}`}>
    {Array.from({ length: items }).map((_, i) => (
      <div key={i} className="flex items-center space-x-3 animate-pulse">
        <div className="h-10 w-10 bg-gray-200 rounded-full"></div>
        <div className="flex-1 space-y-2">
          <div className="h-4 bg-gray-200 rounded w-3/4"></div>
          <div className="h-3 bg-gray-200 rounded w-1/2"></div>
        </div>
      </div>
    ))}
  </div>
)
