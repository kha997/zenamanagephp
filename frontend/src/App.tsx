import { useEffect } from 'react'
import { BrowserRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import { AppRoutes } from './routes'
import { useAuthStore } from './store/auth'
import { Toaster } from './components/ui/toaster'

// Tạo QueryClient instance
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
})

/**
 * Root App component
 * Thiết lập routing, state management và providers
 */
function App() {
  const { checkAuthStatus } = useAuthStore()

  // Kiểm tra authentication status khi app khởi động
  useEffect(() => {
    checkAuthStatus()
  }, [])

  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AppRoutes />
        <Toaster />
      </BrowserRouter>
      <ReactQueryDevtools initialIsOpen={false} />
    </QueryClientProvider>
  )
}

export default App