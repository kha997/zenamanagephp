import { useState, useEffect } from 'react'
import { useWebSocket } from '../services/websocketService'
import { Wifi, WifiOff, Send, MessageSquare, Clock, CheckCircle, XCircle } from 'lucide-react'

interface Message {
  id: string
  type: string
  data: any
  timestamp: Date
  direction: 'sent' | 'received'
}

export default function WebSocketTest() {
  const [messages, setMessages] = useState<Message[]>([])
  const [isConnected, setIsConnected] = useState(false)
  const [latency, setLatency] = useState(0)
  const [messageCount, setMessageCount] = useState(0)
  const [errorCount, setErrorCount] = useState(0)
  const [testMessage, setTestMessage] = useState('Hello WebSocket!')
  
  const { subscribe, emit, isConnected: wsConnected } = useWebSocket()

  useEffect(() => {
    const checkConnection = () => {
      const connected = wsConnected()
      setIsConnected(connected)
    }

    checkConnection()
    const interval = setInterval(checkConnection, 1000)
    
    return () => clearInterval(interval)
  }, [wsConnected])

  useEffect(() => {
    // Subscribe to all events for testing
    const unsubscribe = subscribe('*', (data: any) => {
      addMessage('received', 'event', data)
    })

    return unsubscribe
  }, [subscribe])

  const addMessage = (direction: 'sent' | 'received', type: string, data: any) => {
    const message: Message = {
      id: Math.random().toString(36).substr(2, 9),
      type,
      data,
      timestamp: new Date(),
      direction
    }
    
    setMessages(prev => [message, ...prev].slice(0, 50)) // Keep last 50 messages
  }

  const sendTestMessage = () => {
    if (!isConnected) return

    const startTime = performance.now()
    
    emit('test_message', {
      message: testMessage,
      timestamp: Date.now()
    })

    addMessage('sent', 'test_message', { message: testMessage })
    setMessageCount(prev => prev + 1)

    // Simulate latency measurement
    setTimeout(() => {
      const endTime = performance.now()
      setLatency(endTime - startTime)
    }, 100)
  }

  const sendPing = () => {
    if (!isConnected) return

    const startTime = performance.now()
    
    emit('ping', { timestamp: Date.now() })
    addMessage('sent', 'ping', { timestamp: Date.now() })

    // Listen for pong
    const unsubscribe = subscribe('pong', (data: any) => {
      const endTime = performance.now()
      setLatency(endTime - startTime)
      addMessage('received', 'pong', data)
      unsubscribe()
    })

    setMessageCount(prev => prev + 1)
  }

  const sendProjectEvent = () => {
    if (!isConnected) return

    const projectData = {
      id: 'test-project-' + Date.now(),
      name: 'Test Project',
      description: 'This is a test project',
      status: 'planning',
      progress: 0,
      created_at: new Date().toISOString()
    }

    emit('project_created', projectData)
    addMessage('sent', 'project_created', projectData)
    setMessageCount(prev => prev + 1)
  }

  const sendTaskEvent = () => {
    if (!isConnected) return

    const taskData = {
      id: 'test-task-' + Date.now(),
      name: 'Test Task',
      description: 'This is a test task',
      status: 'pending',
      priority: 'medium',
      created_at: new Date().toISOString()
    }

    emit('task_created', taskData)
    addMessage('sent', 'task_created', taskData)
    setMessageCount(prev => prev + 1)
  }

  const sendNotification = () => {
    if (!isConnected) return

    const notificationData = {
      type: 'info',
      title: 'Test Notification',
      message: 'This is a test notification',
      timestamp: new Date().toISOString()
    }

    emit('notification', notificationData)
    addMessage('sent', 'notification', notificationData)
    setMessageCount(prev => prev + 1)
  }

  const clearMessages = () => {
    setMessages([])
    setMessageCount(0)
    setErrorCount(0)
  }

  const formatTimestamp = (date: Date) => {
    return date.toLocaleTimeString()
  }

  const getMessageIcon = (type: string) => {
    switch (type) {
      case 'ping':
      case 'pong':
        return <Clock className="h-4 w-4" />
      case 'test_message':
        return <MessageSquare className="h-4 w-4" />
      case 'project_created':
        return <CheckCircle className="h-4 w-4" />
      case 'task_created':
        return <CheckCircle className="h-4 w-4" />
      case 'notification':
        return <MessageSquare className="h-4 w-4" />
      default:
        return <MessageSquare className="h-4 w-4" />
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
            WebSocket Testing
          </h3>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Test WebSocket connection and message handling
          </p>
        </div>
        <div className="flex items-center space-x-4">
          <div className={`flex items-center space-x-2 ${isConnected ? 'text-green-600' : 'text-red-600'}`}>
            {isConnected ? <Wifi className="h-5 w-5" /> : <WifiOff className="h-5 w-5" />}
            <span className="text-sm font-medium">
              {isConnected ? 'Connected' : 'Disconnected'}
            </span>
          </div>
          <button
            onClick={clearMessages}
            className="btn btn-outline btn-sm"
          >
            Clear Messages
          </button>
        </div>
      </div>

      {/* Connection Stats */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="card">
          <div className="card-content text-center">
            <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">
              {messageCount}
            </div>
            <div className="text-sm text-gray-500 dark:text-gray-400">Messages Sent</div>
          </div>
        </div>

        <div className="card">
          <div className="card-content text-center">
            <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">
              {messages.length}
            </div>
            <div className="text-sm text-gray-500 dark:text-gray-400">Total Messages</div>
          </div>
        </div>

        <div className="card">
          <div className="card-content text-center">
            <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">
              {latency.toFixed(2)}ms
            </div>
            <div className="text-sm text-gray-500 dark:text-gray-400">Latency</div>
          </div>
        </div>

        <div className="card">
          <div className="card-content text-center">
            <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">
              {errorCount}
            </div>
            <div className="text-sm text-gray-500 dark:text-gray-400">Errors</div>
          </div>
        </div>
      </div>

      {/* Test Controls */}
      <div className="card">
        <div className="card-content">
          <h4 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
            Test Controls
          </h4>
          
          <div className="space-y-4">
            {/* Custom Message */}
            <div className="flex items-center space-x-4">
              <input
                type="text"
                value={testMessage}
                onChange={(e) => setTestMessage(e.target.value)}
                className="input flex-1"
                placeholder="Enter test message..."
              />
              <button
                onClick={sendTestMessage}
                disabled={!isConnected}
                className="btn btn-primary"
              >
                <Send className="h-4 w-4 mr-2" />
                Send Message
              </button>
            </div>

            {/* Quick Tests */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <button
                onClick={sendPing}
                disabled={!isConnected}
                className="btn btn-outline"
              >
                <Clock className="h-4 w-4 mr-2" />
                Send Ping
              </button>

              <button
                onClick={sendProjectEvent}
                disabled={!isConnected}
                className="btn btn-outline"
              >
                <CheckCircle className="h-4 w-4 mr-2" />
                Project Event
              </button>

              <button
                onClick={sendTaskEvent}
                disabled={!isConnected}
                className="btn btn-outline"
              >
                <CheckCircle className="h-4 w-4 mr-2" />
                Task Event
              </button>

              <button
                onClick={sendNotification}
                disabled={!isConnected}
                className="btn btn-outline"
              >
                <MessageSquare className="h-4 w-4 mr-2" />
                Notification
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Message Log */}
      <div className="card">
        <div className="card-content">
          <h4 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
            Message Log
          </h4>
          
          <div className="space-y-2 max-h-96 overflow-y-auto">
            {messages.length === 0 ? (
              <div className="text-center py-8 text-gray-500 dark:text-gray-400">
                No messages yet. Send a test message to see activity.
              </div>
            ) : (
              messages.map((message) => (
                <div
                  key={message.id}
                  className={`flex items-start space-x-3 p-3 rounded-lg border ${
                    message.direction === 'sent'
                      ? 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20'
                      : 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20'
                  }`}
                >
                  <div className="flex-shrink-0">
                    {getMessageIcon(message.type)}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between">
                      <div className="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {message.type}
                      </div>
                      <div className="text-xs text-gray-500 dark:text-gray-400">
                        {formatTimestamp(message.timestamp)}
                      </div>
                    </div>
                    <div className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                      {JSON.stringify(message.data, null, 2)}
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
