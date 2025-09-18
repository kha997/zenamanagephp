import { io, Socket } from 'socket.io-client'
import { useAuthStore } from '../stores/authStore'
import toast from 'react-hot-toast'

export interface WebSocketEvent {
  type: 'project_created' | 'project_updated' | 'project_deleted' | 
        'task_created' | 'task_updated' | 'task_deleted' | 'task_assigned' |
        'user_online' | 'user_offline' | 'notification'
  data: any
  timestamp: string
  userId?: string
}

class WebSocketService {
  private socket: Socket | null = null
  private reconnectAttempts = 0
  private maxReconnectAttempts = 5
  private reconnectDelay = 1000
  private eventListeners: Map<string, Function[]> = new Map()

  constructor() {
    this.connect()
  }

  private connect() {
    const token = localStorage.getItem('auth_token')
    
    if (!token) {
      console.log('No auth token, skipping WebSocket connection')
      return
    }

    // Temporarily disable WebSocket connection until server is ready
    console.log('WebSocket connection disabled - server not ready yet')
    return

    try {
      this.socket = io('ws://localhost:8000', {
        auth: {
          token: token
        },
        transports: ['websocket', 'polling'],
        timeout: 5000,
        forceNew: true
      })

      this.setupEventListeners()
    } catch (error) {
      console.log('WebSocket connection failed, continuing without real-time updates:', error)
    }
  }

  private setupEventListeners() {
    if (!this.socket) return

    // Connection events
    this.socket.on('connect', () => {
      console.log('WebSocket connected')
      this.reconnectAttempts = 0
      toast.success('Connected to real-time updates')
    })

    this.socket.on('disconnect', (reason) => {
      console.log('WebSocket disconnected:', reason)
      toast.error('Disconnected from real-time updates')
      this.handleReconnect()
    })

    this.socket.on('connect_error', (error) => {
      console.error('WebSocket connection error:', error)
      this.handleReconnect()
    })

    // Real-time events
    this.socket.on('project_created', (data) => {
      this.handleEvent('project_created', data)
      toast.success(`New project: ${data.name}`)
    })

    this.socket.on('project_updated', (data) => {
      this.handleEvent('project_updated', data)
      toast.success(`Project updated: ${data.name}`)
    })

    this.socket.on('project_deleted', (data) => {
      this.handleEvent('project_deleted', data)
      toast.success(`Project deleted: ${data.name}`)
    })

    this.socket.on('task_created', (data) => {
      this.handleEvent('task_created', data)
      toast.success(`New task: ${data.name}`)
    })

    this.socket.on('task_updated', (data) => {
      this.handleEvent('task_updated', data)
      toast.success(`Task updated: ${data.name}`)
    })

    this.socket.on('task_deleted', (data) => {
      this.handleEvent('task_deleted', data)
      toast.success(`Task deleted: ${data.name}`)
    })

    this.socket.on('task_assigned', (data) => {
      this.handleEvent('task_assigned', data)
      toast.success(`Task assigned: ${data.task_name}`)
    })

    this.socket.on('user_online', (data) => {
      this.handleEvent('user_online', data)
    })

    this.socket.on('user_offline', (data) => {
      this.handleEvent('user_offline', data)
    })

    this.socket.on('notification', (data) => {
      this.handleEvent('notification', data)
      toast(data.message, {
        icon: data.type === 'success' ? '✅' : data.type === 'error' ? '❌' : 'ℹ️',
        duration: 4000
      })
    })
  }

  private handleReconnect() {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.log('Max reconnection attempts reached')
      return
    }

    this.reconnectAttempts++
    const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1)
    
    setTimeout(() => {
      console.log(`Attempting to reconnect (${this.reconnectAttempts}/${this.maxReconnectAttempts})`)
      this.connect()
    }, delay)
  }

  private handleEvent(eventType: string, data: any) {
    const listeners = this.eventListeners.get(eventType) || []
    listeners.forEach(listener => {
      try {
        listener(data)
      } catch (error) {
        console.error(`Error in event listener for ${eventType}:`, error)
      }
    })
  }

  // Public methods
  public subscribe(eventType: string, callback: Function) {
    if (!this.eventListeners.has(eventType)) {
      this.eventListeners.set(eventType, [])
    }
    this.eventListeners.get(eventType)!.push(callback)

    // Return unsubscribe function
    return () => {
      const listeners = this.eventListeners.get(eventType) || []
      const index = listeners.indexOf(callback)
      if (index > -1) {
        listeners.splice(index, 1)
      }
    }
  }

  public emit(eventType: string, data: any) {
    if (this.socket && this.socket.connected) {
      this.socket.emit(eventType, data)
    }
  }

  public joinRoom(room: string) {
    if (this.socket && this.socket.connected) {
      this.socket.emit('join_room', room)
    }
  }

  public leaveRoom(room: string) {
    if (this.socket && this.socket.connected) {
      this.socket.emit('leave_room', room)
    }
  }

  public isConnected(): boolean {
    return this.socket ? this.socket.connected : false
  }

  public disconnect() {
    if (this.socket) {
      this.socket.disconnect()
      this.socket = null
    }
  }

  public reconnect() {
    this.disconnect()
    this.reconnectAttempts = 0
    this.connect()
  }
}

// Singleton instance
export const websocketService = new WebSocketService()

// React hook for WebSocket
export function useWebSocket() {
  return {
    subscribe: websocketService.subscribe.bind(websocketService),
    emit: websocketService.emit.bind(websocketService),
    joinRoom: websocketService.joinRoom.bind(websocketService),
    leaveRoom: websocketService.leaveRoom.bind(websocketService),
    isConnected: websocketService.isConnected.bind(websocketService),
    reconnect: websocketService.reconnect.bind(websocketService)
  }
}
