// Test utilities for real-time features
export const testUtils = {
  // Mock WebSocket events for testing
  mockWebSocketEvents: {
    project_created: {
      id: 'test-project-1',
      name: 'Test Project',
      description: 'Test project description',
      status: 'planning',
      progress: 0,
      start_date: '2024-01-01',
      end_date: '2024-12-31',
      actual_cost: 0,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    },
    project_updated: {
      id: 'test-project-1',
      name: 'Updated Test Project',
      description: 'Updated test project description',
      status: 'active',
      progress: 50,
      start_date: '2024-01-01',
      end_date: '2024-12-31',
      actual_cost: 5000,
      created_at: '2024-01-01T00:00:00Z',
      updated_at: new Date().toISOString()
    },
    project_deleted: {
      id: 'test-project-1',
      name: 'Test Project'
    },
    task_created: {
      id: 'test-task-1',
      name: 'Test Task',
      description: 'Test task description',
      status: 'pending',
      priority: 'medium',
      start_date: '2024-01-01',
      end_date: '2024-01-15',
      project_id: 'test-project-1',
      user_id: 'test-user-1',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    },
    task_updated: {
      id: 'test-task-1',
      name: 'Updated Test Task',
      description: 'Updated test task description',
      status: 'in_progress',
      priority: 'high',
      start_date: '2024-01-01',
      end_date: '2024-01-15',
      project_id: 'test-project-1',
      user_id: 'test-user-1',
      created_at: '2024-01-01T00:00:00Z',
      updated_at: new Date().toISOString()
    },
    task_deleted: {
      id: 'test-task-1',
      name: 'Test Task'
    },
    task_assigned: {
      task_id: 'test-task-1',
      task_name: 'Test Task',
      user_id: 'test-user-1',
      user: {
        id: 'test-user-1',
        name: 'Test User',
        email: 'test@example.com'
      }
    },
    user_online: {
      id: 'test-user-1',
      name: 'Test User',
      last_seen: new Date().toISOString()
    },
    user_offline: {
      id: 'test-user-1',
      name: 'Test User',
      last_seen: new Date().toISOString()
    },
    notification: {
      type: 'info',
      title: 'Test Notification',
      message: 'This is a test notification',
      timestamp: new Date().toISOString()
    }
  },

  // Performance testing utilities
  performance: {
    measureTime: (fn: () => void): number => {
      const start = performance.now()
      fn()
      const end = performance.now()
      return end - start
    },

    measureAsyncTime: async (fn: () => Promise<void>): Promise<number> => {
      const start = performance.now()
      await fn()
      const end = performance.now()
      return end - start
    },

    measureMemoryUsage: (): number => {
      const performanceWithMemory = performance as Performance & {
        memory?: {
          usedJSHeapSize?: number
        }
      }
      return performanceWithMemory.memory?.usedJSHeapSize ?? 0
    },

    generateLargeDataset: (size: number) => {
      return Array.from({ length: size }, (_, i) => ({
        id: `item-${i}`,
        name: `Item ${i}`,
        description: `Description for item ${i}`,
        created_at: new Date().toISOString()
      }))
    }
  },

  // WebSocket connection testing
  websocket: {
    testConnection: (ws: WebSocket): Promise<boolean> => {
      return new Promise((resolve) => {
        const timeout = setTimeout(() => resolve(false), 5000)
        
        ws.onopen = () => {
          clearTimeout(timeout)
          resolve(true)
        }
        
        ws.onerror = () => {
          clearTimeout(timeout)
          resolve(false)
        }
      })
    },

    testMessageSending: (ws: WebSocket, message: Record<string, unknown>): Promise<boolean> => {
      return new Promise((resolve) => {
        const timeout = setTimeout(() => resolve(false), 3000)
        
        ws.onmessage = () => {
          clearTimeout(timeout)
          resolve(true)
        }
        
        ws.send(JSON.stringify(message))
      })
    }
  },

  // Notification testing
  notifications: {
    testNotificationTypes: [
      { type: 'success', title: 'Success Test', message: 'This is a success notification' },
      { type: 'error', title: 'Error Test', message: 'This is an error notification' },
      { type: 'warning', title: 'Warning Test', message: 'This is a warning notification' },
      { type: 'info', title: 'Info Test', message: 'This is an info notification' }
    ],

    testNotificationActions: [
      {
        type: 'success',
        title: 'Action Test',
        message: 'This notification has an action',
        action: {
          label: 'View Details',
          onClick: () => console.log('Action clicked')
        }
      }
    ]
  },

  // Data synchronization testing
  dataSync: {
    testDataConsistency: <T>(originalData: T, syncedData: T): boolean => {
      return JSON.stringify(originalData) === JSON.stringify(syncedData)
    },

    testCacheUpdate: <T extends { id: string | number }>(
      cache: { data?: T[] } | null | undefined,
      newData: T[]
    ): boolean => {
      if (!cache?.data) {
        return false
      }

      return cache.data.some((item) =>
        newData.some((newItem) => newItem.id === item.id)
      )
    }
  }
}

// Test runner utilities
export const testRunner = {
  runTests: async (tests: Array<() => Promise<boolean>>): Promise<{ passed: number; failed: number; results: Array<{ name: string; passed: boolean; error?: string }> }> => {
    const results: Array<{ name: string; passed: boolean; error?: string }> = []
    let passed = 0
    let failed = 0

    for (const test of tests) {
      try {
        const result = await test()
        if (result) {
          passed++
          results.push({ name: test.name || 'Unknown Test', passed: true })
        } else {
          failed++
          results.push({ name: test.name || 'Unknown Test', passed: false, error: 'Test failed' })
        }
      } catch (error) {
        failed++
        results.push({ 
          name: test.name || 'Unknown Test', 
          passed: false, 
          error: error instanceof Error ? error.message : 'Unknown error' 
        })
      }
    }

    return { passed, failed, results }
  },

  logResults: (results: { passed: number; failed: number; results: Array<{ name: string; passed: boolean; error?: string }> }) => {
    console.log(`\n🧪 Test Results: ${results.passed} passed, ${results.failed} failed`)
    results.results.forEach(result => {
      const status = result.passed ? '✅' : '❌'
      console.log(`${status} ${result.name}${result.error ? ` - ${result.error}` : ''}`)
    })
  }
}
