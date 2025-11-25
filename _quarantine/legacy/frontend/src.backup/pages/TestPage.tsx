import { useState, useEffect } from 'react'
import { useWebSocket } from '../services/websocketService'
import { testUtils, testRunner } from '../utils/testUtils'
import PerformanceTest from '../components/PerformanceTest'
import WebSocketTest from '../components/WebSocketTest'
import ApiIntegrationTest from '../components/ApiIntegrationTest'
import { 
  Play, 
  CheckCircle, 
  XCircle, 
  Clock, 
  Zap, 
  Wifi, 
  WifiOff,
  Bell,
  Database,
  Activity
} from 'lucide-react'

interface TestResult {
  name: string
  passed: boolean
  error?: string
  duration?: number
}

export default function TestPage() {
  const [activeTab, setActiveTab] = useState<'overview' | 'websocket' | 'performance' | 'api'>('overview')
  const [isRunning, setIsRunning] = useState(false)
  const [testResults, setTestResults] = useState<TestResult[]>([])
  const [connectionStatus, setConnectionStatus] = useState<'connected' | 'disconnected' | 'testing'>('testing')
  const [notificationCount, setNotificationCount] = useState(0)
  const [performanceMetrics, setPerformanceMetrics] = useState<{
    memoryUsage: number
    renderTime: number
    websocketLatency: number
  }>({
    memoryUsage: 0,
    renderTime: 0,
    websocketLatency: 0
  })

  const { subscribe, emit, isConnected } = useWebSocket()

  // Test WebSocket Connection
  const testWebSocketConnection = async (): Promise<boolean> => {
    try {
      const connected = isConnected()
      setConnectionStatus(connected ? 'connected' : 'disconnected')
      return connected
    } catch (error) {
      console.error('WebSocket connection test failed:', error)
      return false
    }
  }

  // Test Real-time Events
  const testRealtimeEvents = async (): Promise<boolean> => {
    try {
      let eventReceived = false
      
      const unsubscribe = subscribe('test_event', (data) => {
        eventReceived = true
      })

      // Emit test event
      emit('test_event', { message: 'Test event' })

      // Wait for event
      await new Promise(resolve => setTimeout(resolve, 1000))

      unsubscribe()
      return eventReceived
    } catch (error) {
      console.error('Real-time events test failed:', error)
      return false
    }
  }

  // Test Notification System
  const testNotificationSystem = async (): Promise<boolean> => {
    try {
      let notificationReceived = false
      
      const unsubscribe = subscribe('notification', (data) => {
        notificationReceived = true
        setNotificationCount(prev => prev + 1)
      })

      // Emit test notification
      emit('notification', {
        type: 'info',
        title: 'Test Notification',
        message: 'This is a test notification'
      })

      // Wait for notification
      await new Promise(resolve => setTimeout(resolve, 1000))

      unsubscribe()
      return notificationReceived
    } catch (error) {
      console.error('Notification system test failed:', error)
      return false
    }
  }

  // Test Data Synchronization
  const testDataSynchronization = async (): Promise<boolean> => {
    try {
      // Test cache update simulation
      const originalData = { id: '1', name: 'Test Item' }
      const updatedData = { id: '1', name: 'Updated Test Item' }
      
      const isConsistent = testUtils.dataSync.testDataConsistency(originalData, updatedData)
      return !isConsistent // Should be false for different data
    } catch (error) {
      console.error('Data synchronization test failed:', error)
      return false
    }
  }

  // Test Performance
  const testPerformance = async (): Promise<boolean> => {
    try {
      const startTime = performance.now()
      
      // Test render performance
      const renderTime = testUtils.performance.measureTime(() => {
        // Simulate render work
        for (let i = 0; i < 1000; i++) {
          Math.random()
        }
      })

      // Test memory usage
      const memoryUsage = testUtils.performance.measureMemoryUsage()

      // Test WebSocket latency
      const wsStart = performance.now()
      emit('ping', { timestamp: Date.now() })
      const wsEnd = performance.now()
      const websocketLatency = wsEnd - wsStart

      setPerformanceMetrics({
        memoryUsage,
        renderTime,
        websocketLatency
      })

      return renderTime < 100 && websocketLatency < 1000 // Performance thresholds
    } catch (error) {
      console.error('Performance test failed:', error)
      return false
    }
  }

  // Test Project Events
  const testProjectEvents = async (): Promise<boolean> => {
    try {
      let projectEventReceived = false
      
      const unsubscribe = subscribe('project_created', (data) => {
        projectEventReceived = true
      })

      // Emit test project event
      emit('project_created', testUtils.mockWebSocketEvents.project_created)

      // Wait for event
      await new Promise(resolve => setTimeout(resolve, 1000))

      unsubscribe()
      return projectEventReceived
    } catch (error) {
      console.error('Project events test failed:', error)
      return false
    }
  }

  // Test Task Events
  const testTaskEvents = async (): Promise<boolean> => {
    try {
      let taskEventReceived = false
      
      const unsubscribe = subscribe('task_created', (data) => {
        taskEventReceived = true
      })

      // Emit test task event
      emit('task_created', testUtils.mockWebSocketEvents.task_created)

      // Wait for event
      await new Promise(resolve => setTimeout(resolve, 1000))

      unsubscribe()
      return taskEventReceived
    } catch (error) {
      console.error('Task events test failed:', error)
      return false
    }
  }

  // Run all tests
  const runAllTests = async () => {
    setIsRunning(true)
    setTestResults([])

    const tests = [
      { name: 'WebSocket Connection', fn: testWebSocketConnection },
      { name: 'Real-time Events', fn: testRealtimeEvents },
      { name: 'Notification System', fn: testNotificationSystem },
      { name: 'Data Synchronization', fn: testDataSynchronization },
      { name: 'Performance', fn: testPerformance },
      { name: 'Project Events', fn: testProjectEvents },
      { name: 'Task Events', fn: testTaskEvents }
    ]

    const results: TestResult[] = []

    for (const test of tests) {
      const startTime = performance.now()
      try {
        const passed = await test.fn()
        const duration = performance.now() - startTime
        
        results.push({
          name: test.name,
          passed,
          duration
        })
      } catch (error) {
        const duration = performance.now() - startTime
        results.push({
          name: test.name,
          passed: false,
          error: error instanceof Error ? error.message : 'Unknown error',
          duration
        })
      }
    }

    setTestResults(results)
    setIsRunning(false)
  }

  // Stop tests
  const stopTests = () => {
    setIsRunning(false)
  }

  // Clear results
  const clearResults = () => {
    setTestResults([])
    setNotificationCount(0)
  }

  const passedTests = testResults.filter(r => r.passed).length
  const totalTests = testResults.length
  const successRate = totalTests > 0 ? (passedTests / totalTests) * 100 : 0

  return (
    <div className="space-y-6 animate-fade-in">
      {/* Header */}
      <div className="flex items-center justify-between animate-slide-up">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Real-time Testing</h1>
          <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Test WebSocket connection, notifications, and performance
          </p>
        </div>
        <div className="flex items-center space-x-4">
          <button
            onClick={clearResults}
            className="btn btn-outline"
            disabled={isRunning}
          >
            Clear Results
          </button>
          <button
            onClick={isRunning ? stopTests : runAllTests}
            className={cn(
              'btn',
              isRunning ? 'btn-danger' : 'btn-primary'
            )}
          >
            {isRunning ? (
              <>
                <Stop className="h-4 w-4 mr-2" />
                Stop Tests
              </>
            ) : (
              <>
                <Play className="h-4 w-4 mr-2" />
                Run Tests
              </>
            )}
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200 dark:border-gray-700">
        <nav className="-mb-px flex space-x-8">
          {[
            { id: 'overview', name: 'Overview', icon: Activity },
            { id: 'websocket', name: 'WebSocket', icon: Wifi },
            { id: 'performance', name: 'Performance', icon: Zap },
            { id: 'api', name: 'API Integration', icon: Database }
          ].map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={cn(
                'flex items-center space-x-2 py-2 px-1 border-b-2 font-medium text-sm',
                activeTab === tab.id
                  ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
              )}
            >
              <tab.icon className="h-4 w-4" />
              <span>{tab.name}</span>
            </button>
          ))}
        </nav>
      </div>

      {/* Tab Content */}
      {activeTab === 'overview' && (
        <>
          {/* Connection Status */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 animate-slide-up" style={{ animationDelay: '100ms' }}>
            <div className="card">
              <div className="card-content">
                <div className="flex items-center space-x-3">
                  {connectionStatus === 'connected' ? (
                    <Wifi className="h-8 w-8 text-green-500" />
                  ) : connectionStatus === 'disconnected' ? (
                    <WifiOff className="h-8 w-8 text-red-500" />
                  ) : (
                    <Activity className="h-8 w-8 text-yellow-500 animate-pulse" />
                  )}
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                      WebSocket Status
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400 capitalize">
                      {connectionStatus}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div className="card">
              <div className="card-content">
                <div className="flex items-center space-x-3">
                  <Bell className="h-8 w-8 text-blue-500" />
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                      Notifications
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                      {notificationCount} received
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div className="card">
              <div className="card-content">
                <div className="flex items-center space-x-3">
                  <Database className="h-8 w-8 text-purple-500" />
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                      Memory Usage
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                      {Math.round(performanceMetrics.memoryUsage / 1024 / 1024)} MB
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Performance Metrics */}
          <div className="card animate-slide-up" style={{ animationDelay: '200ms' }}>
            <div className="card-content">
              <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Performance Metrics
              </h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="text-center">
                  <div className="text-2xl font-bold text-primary-600 dark:text-primary-400">
                    {performanceMetrics.renderTime.toFixed(2)}ms
                  </div>
                  <div className="text-sm text-gray-500 dark:text-gray-400">Render Time</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-green-600 dark:text-green-400">
                    {performanceMetrics.websocketLatency.toFixed(2)}ms
                  </div>
                  <div className="text-sm text-gray-500 dark:text-gray-400">WebSocket Latency</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-purple-600 dark:text-purple-400">
                    {Math.round(performanceMetrics.memoryUsage / 1024 / 1024)}MB
                  </div>
                  <div className="text-sm text-gray-500 dark:text-gray-400">Memory Usage</div>
                </div>
              </div>
            </div>
          </div>

          {/* Test Results */}
          {testResults.length > 0 && (
            <div className="card animate-slide-up" style={{ animationDelay: '300ms' }}>
              <div className="card-content">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Test Results
                  </h3>
                  <div className="flex items-center space-x-4">
                    <div className="text-sm text-gray-500 dark:text-gray-400">
                      {passedTests}/{totalTests} passed
                    </div>
                    <div className="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {successRate.toFixed(1)}% success rate
                    </div>
                  </div>
                </div>

                <div className="space-y-2">
                  {testResults.map((result, index) => (
                    <div
                      key={index}
                      className="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700"
                    >
                      <div className="flex items-center space-x-3">
                        {result.passed ? (
                          <CheckCircle className="h-5 w-5 text-green-500" />
                        ) : (
                          <XCircle className="h-5 w-5 text-red-500" />
                        )}
                        <div>
                          <div className="font-medium text-gray-900 dark:text-gray-100">
                            {result.name}
                          </div>
                          {result.error && (
                            <div className="text-sm text-red-600 dark:text-red-400">
                              {result.error}
                            </div>
                          )}
                        </div>
                      </div>
                      <div className="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                        {result.duration && (
                          <>
                            <Clock className="h-4 w-4" />
                            <span>{result.duration.toFixed(2)}ms</span>
                          </>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}
        </>
      )}

      {/* WebSocket Tab */}
      {activeTab === 'websocket' && <WebSocketTest />}

      {/* Performance Tab */}
      {activeTab === 'performance' && <PerformanceTest />}
      {activeTab === 'api' && <ApiIntegrationTest />}

      {/* Test Controls */}
      <div className="card animate-slide-up" style={{ animationDelay: '400ms' }}>
        <div className="card-content">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
            Test Controls
          </h3>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <button
              onClick={() => testWebSocketConnection()}
              className="btn btn-outline"
              disabled={isRunning}
            >
              <Wifi className="h-4 w-4 mr-2" />
              Test Connection
            </button>
            <button
              onClick={() => testNotificationSystem()}
              className="btn btn-outline"
              disabled={isRunning}
            >
              <Bell className="h-4 w-4 mr-2" />
              Test Notifications
            </button>
            <button
              onClick={() => testPerformance()}
              className="btn btn-outline"
              disabled={isRunning}
            >
              <Zap className="h-4 w-4 mr-2" />
              Test Performance
            </button>
            <button
              onClick={() => testRealtimeEvents()}
              className="btn btn-outline"
              disabled={isRunning}
            >
              <Activity className="h-4 w-4 mr-2" />
              Test Events
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

function cn(...classes: (string | undefined | null | false)[]): string {
  return classes.filter(Boolean).join(' ')
}
