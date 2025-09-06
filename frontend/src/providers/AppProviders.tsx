import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import { ReactNode } from 'react'
import { queryClient } from '@/lib/queryClient'
import { Toaster } from '@/components/ui/toaster'

interface AppProvidersProps {
  children: ReactNode
}

/**
 * Tập trung tất cả providers của ứng dụng
 * Bao gồm: QueryClient, Theme, Toast notifications
 */
export function AppProviders({ children }: AppProvidersProps) {
  return (
    <QueryClientProvider client={queryClient}>
      {children}
      <Toaster />
      {import.meta.env.DEV && import.meta.env.VITE_ENABLE_DEVTOOLS === 'true' && (
        <ReactQueryDevtools initialIsOpen={false} />
      )}
    </QueryClientProvider>
  )
}