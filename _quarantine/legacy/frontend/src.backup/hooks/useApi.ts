import { useState, useEffect, useCallback } from 'react'
import { dataService, Project, Task, User, ProjectFilters, TaskFilters, UserFilters } from '../services/dataService'
import { ApiError } from '../services/api'

// Generic hook for data fetching with loading and error states
export function useApiCall<T>(
  apiCall: () => Promise<T>,
  dependencies: any[] = []
) {
  const [data, setData] = useState<T | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const fetchData = useCallback(async () => {
    try {
      setLoading(true)
      setError(null)
      const result = await apiCall()
      setData(result)
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message)
      } else {
        setError('An unexpected error occurred')
      }
    } finally {
      setLoading(false)
    }
  }, dependencies)

  useEffect(() => {
    fetchData()
  }, [fetchData])

  const refetch = useCallback(() => {
    fetchData()
  }, [fetchData])

  return { data, loading, error, refetch }
}

// Projects hooks
export function useProjects(filters?: ProjectFilters, page = 1, perPage = 15) {
  return useApiCall(
    () => dataService.getProjects(filters, page, perPage),
    [filters, page, perPage]
  )
}

export function useProject(id: string) {
  return useApiCall(
    () => dataService.getProject(id),
    [id]
  )
}

// Tasks hooks
export function useTasks(filters?: TaskFilters, page = 1, perPage = 15) {
  return useApiCall(
    () => dataService.getTasks(filters, page, perPage),
    [filters, page, perPage]
  )
}

export function useTask(id: string) {
  return useApiCall(
    () => dataService.getTask(id),
    [id]
  )
}

// Users hooks
export function useUsers(filters?: UserFilters, page = 1, perPage = 15) {
  return useApiCall(
    () => dataService.getUsers(filters, page, perPage),
    [filters, page, perPage]
  )
}

export function useUser(id: string) {
  return useApiCall(
    () => dataService.getUser(id),
    [id]
  )
}

// Dashboard hooks
export function useDashboardStats() {
  return useApiCall(
    () => dataService.getDashboardStats(),
    []
  )
}

// Mutation hooks for create/update/delete operations
export function useMutation<T, P = any>(
  mutationFn: (params: P) => Promise<T>,
  onSuccess?: (data: T) => void,
  onError?: (error: string) => void
) {
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const mutate = useCallback(async (params: P) => {
    try {
      setLoading(true)
      setError(null)
      const result = await mutationFn(params)
      onSuccess?.(result)
      return result
    } catch (err) {
      const errorMessage = err instanceof ApiError ? err.message : 'An unexpected error occurred'
      setError(errorMessage)
      onError?.(errorMessage)
      throw err
    } finally {
      setLoading(false)
    }
  }, [mutationFn, onSuccess, onError])

  return { mutate, loading, error }
}

// Project mutations
export function useCreateProject() {
  return useMutation(
    dataService.createProject,
    undefined,
    undefined
  )
}

export function useUpdateProject() {
  return useMutation(
    dataService.updateProject,
    undefined,
    undefined
  )
}

export function useDeleteProject() {
  return useMutation(
    dataService.deleteProject,
    undefined,
    undefined
  )
}

// Task mutations
export function useCreateTask() {
  return useMutation(
    dataService.createTask,
    undefined,
    undefined
  )
}

export function useUpdateTask() {
  return useMutation(
    dataService.updateTask,
    undefined,
    undefined
  )
}

export function useDeleteTask() {
  return useMutation(
    dataService.deleteTask,
    undefined,
    undefined
  )
}

export function useUpdateTaskStatus() {
  return useMutation(
    ({ id, status }: { id: string; status: string }) => dataService.updateTaskStatus(id, status),
    undefined,
    undefined
  )
}

export function useUpdateTaskProgress() {
  return useMutation(
    ({ id, progress }: { id: string; progress: number }) => dataService.updateTaskProgress(id, progress),
    undefined,
    undefined
  )
}

// User mutations
export function useCreateUser() {
  return useMutation(
    dataService.createUser,
    undefined,
    undefined
  )
}

export function useUpdateUser() {
  return useMutation(
    dataService.updateUser,
    undefined,
    undefined
  )
}

export function useDeleteUser() {
  return useMutation(
    dataService.deleteUser,
    undefined,
    undefined
  )
}

export function useToggleUserStatus() {
  return useMutation(
    dataService.toggleUserStatus,
    undefined,
    undefined
  )
}

// Pagination hook
export function usePagination<T>(
  fetchFn: (page: number, perPage: number) => Promise<{ data: T[]; total: number; current_page: number; last_page: number }>,
  perPage = 15
) {
  const [page, setPage] = useState(1)
  const [data, setData] = useState<T[]>([])
  const [total, setTotal] = useState(0)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const fetchData = useCallback(async (pageNum: number) => {
    try {
      setLoading(true)
      setError(null)
      const result = await fetchFn(pageNum, perPage)
      setData(result.data)
      setTotal(result.total)
      setPage(result.current_page)
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message)
      } else {
        setError('An unexpected error occurred')
      }
    } finally {
      setLoading(false)
    }
  }, [fetchFn, perPage])

  useEffect(() => {
    fetchData(page)
  }, [fetchData, page])

  const goToPage = useCallback((pageNum: number) => {
    setPage(pageNum)
  }, [])

  const nextPage = useCallback(() => {
    if (page < Math.ceil(total / perPage)) {
      setPage(page + 1)
    }
  }, [page, total, perPage])

  const prevPage = useCallback(() => {
    if (page > 1) {
      setPage(page - 1)
    }
  }, [page])

  const refetch = useCallback(() => {
    fetchData(page)
  }, [fetchData, page])

  return {
    data,
    loading,
    error,
    page,
    total,
    totalPages: Math.ceil(total / perPage),
    goToPage,
    nextPage,
    prevPage,
    refetch,
    hasNextPage: page < Math.ceil(total / perPage),
    hasPrevPage: page > 1,
  }
}

// Search hook with debouncing
export function useSearch<T>(
  searchFn: (query: string) => Promise<T[]>,
  delay = 300
) {
  const [query, setQuery] = useState('')
  const [results, setResults] = useState<T[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!query.trim()) {
      setResults([])
      return
    }

    const timeoutId = setTimeout(async () => {
      try {
        setLoading(true)
        setError(null)
        const searchResults = await searchFn(query)
        setResults(searchResults)
      } catch (err) {
        if (err instanceof ApiError) {
          setError(err.message)
        } else {
          setError('Search failed')
        }
        setResults([])
      } finally {
        setLoading(false)
      }
    }, delay)

    return () => clearTimeout(timeoutId)
  }, [query, searchFn, delay])

  const clearSearch = useCallback(() => {
    setQuery('')
    setResults([])
    setError(null)
  }, [])

  return {
    query,
    setQuery,
    results,
    loading,
    error,
    clearSearch,
  }
}