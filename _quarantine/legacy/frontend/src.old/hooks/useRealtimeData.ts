import { useEffect, useCallback } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useWebSocket } from '../services/websocketService'

interface UseRealtimeDataOptions {
  queryKey: string[]
  enabled?: boolean
}

export function useRealtimeData({ queryKey, enabled = true }: UseRealtimeDataOptions) {
  const queryClient = useQueryClient()
  const { subscribe, joinRoom, leaveRoom } = useWebSocket()

  const invalidateQueries = useCallback(() => {
    queryClient.invalidateQueries({ queryKey })
  }, [queryClient, queryKey])

  const refetchQueries = useCallback(() => {
    queryClient.refetchQueries({ queryKey })
  }, [queryClient, queryKey])

  useEffect(() => {
    if (!enabled) return

    // Join room for real-time updates
    const roomName = queryKey.join(':')
    joinRoom(roomName)

    // Subscribe to relevant events
    const unsubscribeProjectCreated = subscribe('project_created', (data: any) => {
      // Add new project to cache
      queryClient.setQueryData(['projects'], (oldData: any) => {
        if (!oldData) return oldData
        return {
          ...oldData,
          data: [data, ...oldData.data]
        }
      })
      invalidateQueries()
    })

    const unsubscribeProjectUpdated = subscribe('project_updated', (data: any) => {
      // Update project in cache
      queryClient.setQueryData(['projects'], (oldData: any) => {
        if (!oldData) return oldData
        return {
          ...oldData,
          data: oldData.data.map((project: any) =>
            project.id === data.id ? { ...project, ...data } : project
          )
        }
      })
      invalidateQueries()
    })

    const unsubscribeProjectDeleted = subscribe('project_deleted', (data: any) => {
      // Remove project from cache
      queryClient.setQueryData(['projects'], (oldData: any) => {
        if (!oldData) return oldData
        return {
          ...oldData,
          data: oldData.data.filter((project: any) => project.id !== data.id)
        }
      })
      invalidateQueries()
    })

    const unsubscribeTaskCreated = subscribe('task_created', (data: any) => {
      // Add new task to cache
      queryClient.setQueryData(['tasks'], (oldData: any) => {
        if (!oldData) return oldData
        return {
          ...oldData,
          data: [data, ...oldData.data]
        }
      })
      invalidateQueries()
    })

    const unsubscribeTaskUpdated = subscribe('task_updated', (data: any) => {
      // Update task in cache
      queryClient.setQueryData(['tasks'], (oldData: any) => {
        if (!oldData) return oldData
        return {
          ...oldData,
          data: oldData.data.map((task: any) =>
            task.id === data.id ? { ...task, ...data } : task
          )
        }
      })
      invalidateQueries()
    })

    const unsubscribeTaskDeleted = subscribe('task_deleted', (data: any) => {
      // Remove task from cache
      queryClient.setQueryData(['tasks'], (oldData: any) => {
        if (!oldData) return oldData
        return {
          ...oldData,
          data: oldData.data.filter((task: any) => task.id !== data.id)
        }
      })
      invalidateQueries()
    })

    const unsubscribeTaskAssigned = subscribe('task_assigned', (data: any) => {
      // Update task assignment in cache
      queryClient.setQueryData(['tasks'], (oldData: any) => {
        if (!oldData) return oldData
        return {
          ...oldData,
          data: oldData.data.map((task: any) =>
            task.id === data.task_id ? { ...task, user: data.user } : task
          )
        }
      })
      invalidateQueries()
    })

    // User events
    const unsubscribeUserCreated = subscribe('user_created', (data: any) => {
      // Add new user to cache
      queryClient.setQueryData(['users'], (oldData: any) => {
        if (!oldData) return oldData
        return {
          ...oldData,
          data: [data, ...oldData.data]
        }
      })
      invalidateQueries()
    })

    const unsubscribeUserUpdated = subscribe('user_updated', (data: any) => {
      // Update user in cache
      queryClient.setQueryData(['users'], (oldData: any) => {
        if (!oldData) return oldData
        return {
          ...oldData,
          data: oldData.data.map((user: any) =>
            user.id === data.id ? { ...user, ...data } : user
          )
        }
      })
      invalidateQueries()
    })

    const unsubscribeUserDeleted = subscribe('user_deleted', (data: any) => {
      // Remove user from cache
      queryClient.setQueryData(['users'], (oldData: any) => {
        if (!oldData) return oldData
        return {
          ...oldData,
          data: oldData.data.filter((user: any) => user.id !== data.id)
        }
      })
      invalidateQueries()
    })

    return () => {
      leaveRoom(roomName)
      unsubscribeProjectCreated()
      unsubscribeProjectUpdated()
      unsubscribeProjectDeleted()
      unsubscribeTaskCreated()
      unsubscribeTaskUpdated()
      unsubscribeTaskDeleted()
      unsubscribeTaskAssigned()
      unsubscribeUserCreated()
      unsubscribeUserUpdated()
      unsubscribeUserDeleted()
    }
  }, [enabled, queryKey, subscribe, joinRoom, leaveRoom, queryClient, invalidateQueries])

  return {
    invalidateQueries,
    refetchQueries
  }
}

// Specific hooks for different data types
export function useRealtimeProjects() {
  return useRealtimeData({ queryKey: ['projects'] })
}

export function useRealtimeTasks() {
  return useRealtimeData({ queryKey: ['tasks'] })
}

export function useRealtimeUsers() {
  return useRealtimeData({ queryKey: ['users'] })
}

export function useRealtimeProject(projectId: string) {
  return useRealtimeData({ queryKey: ['projects', projectId] })
}

export function useRealtimeTask(taskId: string) {
  return useRealtimeData({ queryKey: ['tasks', taskId] })
}
