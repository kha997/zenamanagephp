import React, { useState } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/Input'
import { 
  Play, 
  CheckCircle, 
  XCircle, 
  Clock, 
  BarChart3,
  Activity,
  Zap,
  Database,
  Globe,
  Shield,
  Users,
  FileText,
  Settings,
  Server,
  Wifi,
  WifiOff
} from 'lucide-react'
import { api } from '../services/api'
import { authService } from '../services/authService'
import { dataService } from '../services/dataService'
import { ApiError } from '../services/api'

interface ApiTestResult {
  name: string
  passed: boolean
  error?: string
  duration?: number
  details?: any
}

const ApiIntegrationTest: React.FC = () => {
  const [testResults, setTestResults] = useState<ApiTestResult[]>([])
  const [isRunning, setIsRunning] = useState(false)
  const [testCredentials, setTestCredentials] = useState({
    email: 'admin@example.com',
    password: 'password'
  })

  const runApiTests = async () => {
    setIsRunning(true)
    const results: ApiTestResult[] = []

    // Test 1: API Connection
    try {
      const startTime = performance.now()
      const response = await api.get('/health')
      const duration = performance.now() - startTime
      
      results.push({
        name: 'API Connection',
        passed: true,
        duration,
        details: 'API server is reachable'
      })
    } catch (error) {
      results.push({
        name: 'API Connection',
        passed: false,
        error: error instanceof ApiError ? error.message : 'Connection failed',
        details: 'API server is not reachable'
      })
    }

    // Test 2: Authentication
    try {
      const startTime = performance.now()
      const response = await authService.login(testCredentials)
      const duration = performance.now() - startTime
      
      results.push({
        name: 'Authentication',
        passed: true,
        duration,
        details: `Logged in as ${response.user.name}`
      })
    } catch (error) {
      results.push({
        name: 'Authentication',
        passed: false,
        error: error instanceof ApiError ? error.message : 'Authentication failed',
        details: 'Login failed with provided credentials'
      })
    }

    // Test 3: Get Current User
    try {
      const startTime = performance.now()
      const user = await authService.getCurrentUser()
      const duration = performance.now() - startTime
      
      results.push({
        name: 'Get Current User',
        passed: true,
        duration,
        details: `Retrieved user: ${user.name}`
      })
    } catch (error) {
      results.push({
        name: 'Get Current User',
        passed: false,
        error: error instanceof ApiError ? error.message : 'Failed to get user',
        details: 'Could not retrieve current user data'
      })
    }

    // Test 4: Get Dashboard Stats
    try {
      const startTime = performance.now()
      const stats = await dataService.getDashboardStats()
      const duration = performance.now() - startTime
      
      results.push({
        name: 'Dashboard Stats',
        passed: true,
        duration,
        details: `Retrieved stats: ${stats.projects.total} projects, ${stats.tasks.total} tasks`
      })
    } catch (error) {
      results.push({
        name: 'Dashboard Stats',
        passed: false,
        error: error instanceof ApiError ? error.message : 'Failed to get stats',
        details: 'Could not retrieve dashboard statistics'
      })
    }

    // Test 5: Get Users
    try {
      const startTime = performance.now()
      const users = await dataService.getUsers({}, 1, 5)
      const duration = performance.now() - startTime
      
      results.push({
        name: 'Get Users',
        passed: true,
        duration,
        details: `Retrieved ${users.data.length} users`
      })
    } catch (error) {
      results.push({
        name: 'Get Users',
        passed: false,
        error: error instanceof ApiError ? error.message : 'Failed to get users',
        details: 'Could not retrieve users list'
      })
    }

    // Test 6: Get Projects
    try {
      const startTime = performance.now()
      const projects = await dataService.getProjects({}, 1, 5)
      const duration = performance.now() - startTime
      
      results.push({
        name: 'Get Projects',
        passed: true,
        duration,
        details: `Retrieved ${projects.data.length} projects`
      })
    } catch (error) {
      results.push({
        name: 'Get Projects',
        passed: false,
        error: error instanceof ApiError ? error.message : 'Failed to get projects',
        details: 'Could not retrieve projects list'
      })
    }

    // Test 7: Get Tasks
    try {
      const startTime = performance.now()
      const tasks = await dataService.getTasks({}, 1, 5)
      const duration = performance.now() - startTime
      
      results.push({
        name: 'Get Tasks',
        passed: true,
        duration,
        details: `Retrieved ${tasks.data.length} tasks`
      })
    } catch (error) {
      results.push({
        name: 'Get Tasks',
        passed: false,
        error: error instanceof ApiError ? error.message : 'Failed to get tasks',
        details: 'Could not retrieve tasks list'
      })
    }

    // Test 8: Error Handling
    try {
      const startTime = performance.now()
      await api.get('/non-existent-endpoint')
      const duration = performance.now() - startTime
      
      results.push({
        name: 'Error Handling',
        passed: false,
        duration,
        error: 'Expected error but got success',
        details: 'Error handling test failed'
      })
    } catch (error) {
      const duration = performance.now() - startTime
      results.push({
        name: 'Error Handling',
        passed: true,
        duration,
        details: 'Properly handled 404 error'
      })
    }

    // Test 9: Token Refresh
    try {
      const startTime = performance.now()
      const response = await authService.refreshToken()
      const duration = performance.now() - startTime
      
      results.push({
        name: 'Token Refresh',
        passed: true,
        duration,
        details: 'Token refreshed successfully'
      })
    } catch (error) {
      results.push({
        name: 'Token Refresh',
        passed: false,
        error: error instanceof ApiError ? error.message : 'Token refresh failed',
        details: 'Could not refresh authentication token'
      })
    }

    // Test 10: Logout
    try {
      const startTime = performance.now()
      await authService.logout()
      const duration = performance.now() - startTime
      
      results.push({
        name: 'Logout',
        passed: true,
        duration,
        details: 'Logged out successfully'
      })
    } catch (error) {
      results.push({
        name: 'Logout',
        passed: false,
        error: error instanceof ApiError ? error.message : 'Logout failed',
        details: 'Could not logout properly'
      })
    }

    setTestResults(results)
    setIsRunning(false)
  }

  const getStatusIcon = (passed: boolean) => {
    return passed ? (
      <CheckCircle className="h-4 w-4 text-green-500" />
    ) : (
      <XCircle className="h-4 w-4 text-red-500" />
    )
  }

  const getStatusColor = (passed: boolean) => {
    return passed ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
  }

  const getOverallStats = () => {
    const totalTests = testResults.length
    const passedTests = testResults.filter(test => test.passed).length
    const failedTests = totalTests - passedTests
    const totalDuration = testResults.reduce((sum, test) => sum + (test.duration || 0), 0)

    return { totalTests, passedTests, failedTests, totalDuration }
  }

  const stats = getOverallStats()

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Server className="h-5 w-5" />
            API Integration Test Suite
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {/* Test Credentials */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-1">Test Email</label>
                <Input
                  value={testCredentials.email}
                  onChange={(e) => setTestCredentials(prev => ({ ...prev, email: e.target.value }))}
                  placeholder="admin@example.com"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Test Password</label>
                <Input
                  type="password"
                  value={testCredentials.password}
                  onChange={(e) => setTestCredentials(prev => ({ ...prev, password: e.target.value }))}
                  placeholder="password"
                />
              </div>
            </div>

            {/* Test Control */}
            <div className="flex items-center gap-4">
              <Button 
                onClick={runApiTests} 
                disabled={isRunning}
                className="flex items-center gap-2"
              >
                <Play className="h-4 w-4" />
                {isRunning ? 'Running Tests...' : 'Run API Tests'}
              </Button>
              {isRunning && (
                <Badge className="bg-blue-100 text-blue-800">
                  <Clock className="h-3 w-3 mr-1 animate-spin" />
                  Testing...
                </Badge>
              )}
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Overall Stats */}
      {testResults.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <BarChart3 className="h-5 w-5" />
              Test Results Summary
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div className="text-center">
                <div className="text-2xl font-bold text-gray-900">{stats.totalTests}</div>
                <div className="text-sm text-gray-500">Total Tests</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-green-600">{stats.passedTests}</div>
                <div className="text-sm text-gray-500">Passed</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-red-600">{stats.failedTests}</div>
                <div className="text-sm text-gray-500">Failed</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-blue-600">{Math.round(stats.totalDuration)}ms</div>
                <div className="text-sm text-gray-500">Duration</div>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Test Results */}
      {testResults.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>Test Results</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {testResults.map((result, index) => (
                <div key={index} className="flex items-center justify-between p-3 bg-gray-50 rounded">
                  <div className="flex items-center gap-3">
                    {getStatusIcon(result.passed)}
                    <span className="font-medium">{result.name}</span>
                    {result.duration && (
                      <span className="text-sm text-gray-500">{result.duration.toFixed(1)}ms</span>
                    )}
                  </div>
                  <div className="flex items-center gap-2">
                    <Badge className={getStatusColor(result.passed)}>
                      {result.passed ? 'Passed' : 'Failed'}
                    </Badge>
                    {result.error && (
                      <Badge variant="destructive" className="text-xs">
                        {result.error}
                      </Badge>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* API Endpoints */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Globe className="h-5 w-5" />
            API Endpoints Tested
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-3">
              <h3 className="font-medium text-gray-900">Authentication</h3>
              <div className="space-y-2">
                <div className="flex items-center gap-2">
                  <CheckCircle className="h-4 w-4 text-green-500" />
                  <span className="text-sm">POST /auth/login</span>
                </div>
                <div className="flex items-center gap-2">
                  <CheckCircle className="h-4 w-4 text-green-500" />
                  <span className="text-sm">GET /auth/me</span>
                </div>
                <div className="flex items-center gap-2">
                  <CheckCircle className="h-4 w-4 text-green-500" />
                  <span className="text-sm">POST /auth/refresh</span>
                </div>
                <div className="flex items-center gap-2">
                  <CheckCircle className="h-4 w-4 text-green-500" />
                  <span className="text-sm">POST /auth/logout</span>
                </div>
              </div>
            </div>
            
            <div className="space-y-3">
              <h3 className="font-medium text-gray-900">Data Endpoints</h3>
              <div className="space-y-2">
                <div className="flex items-center gap-2">
                  <CheckCircle className="h-4 w-4 text-green-500" />
                  <span className="text-sm">GET /dashboard/stats</span>
                </div>
                <div className="flex items-center gap-2">
                  <CheckCircle className="h-4 w-4 text-green-500" />
                  <span className="text-sm">GET /users</span>
                </div>
                <div className="flex items-center gap-2">
                  <CheckCircle className="h-4 w-4 text-green-500" />
                  <span className="text-sm">GET /projects</span>
                </div>
                <div className="flex items-center gap-2">
                  <CheckCircle className="h-4 w-4 text-green-500" />
                  <span className="text-sm">GET /tasks</span>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

export default ApiIntegrationTest
