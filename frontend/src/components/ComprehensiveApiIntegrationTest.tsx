import React, { useState, useEffect } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from './ui/card'
import { Button } from './ui/button'
import { Badge } from './ui/badge'
import { Progress } from './ui/progress'
import { Alert, AlertDescription } from './ui/alert'
import { CheckCircle, XCircle, Clock, AlertTriangle } from 'lucide-react'
import { apiClient } from '../lib/api/client'
import { AuthService } from '../lib/api/auth.service'
import { ProjectsService } from '../lib/api/projects.service'
import { TasksService } from '../lib/api/tasks.service'
import { ChangeRequestsService } from '../lib/api/changeRequests.service'
import { DocumentsService } from '../lib/api/documents.service'
import { UsersService } from '../lib/api/users.service'

interface TestResult {
  name: string
  passed: boolean
  duration?: number
  error?: string
  details?: any
  category: 'auth' | 'projects' | 'tasks' | 'users' | 'documents' | 'change-requests' | 'system'
}

interface TestCategory {
  name: string
  tests: TestResult[]
  passed: number
  total: number
  passRate: number
}

const ComprehensiveApiIntegrationTest: React.FC = () => {
  const [testResults, setTestResults] = useState<TestCategory[]>([])
  const [isRunning, setIsRunning] = useState(false)
  const [overallProgress, setOverallProgress] = useState(0)
  const [currentTest, setCurrentTest] = useState('')
  const [testCredentials, setTestCredentials] = useState({
    email: 'admin@example.com',
    password: 'password'
  })

  const runComprehensiveTests = async () => {
    setIsRunning(true)
    setTestResults([])
    setOverallProgress(0)
    
    const categories: TestCategory[] = []
    let totalTests = 0
    let completedTests = 0

    // Test 1: Authentication Tests
    setCurrentTest('Running Authentication Tests...')
    const authTests = await runAuthenticationTests()
    categories.push(authTests)
    totalTests += authTests.total
    completedTests += authTests.total
    setOverallProgress((completedTests / totalTests) * 100)

    // Test 2: System Health Tests
    setCurrentTest('Running System Health Tests...')
    const systemTests = await runSystemHealthTests()
    categories.push(systemTests)
    totalTests += systemTests.total
    completedTests += systemTests.total
    setOverallProgress((completedTests / totalTests) * 100)

    // Test 3: User Management Tests
    setCurrentTest('Running User Management Tests...')
    const userTests = await runUserManagementTests()
    categories.push(userTests)
    totalTests += userTests.total
    completedTests += userTests.total
    setOverallProgress((completedTests / totalTests) * 100)

    // Test 4: Project Management Tests
    setCurrentTest('Running Project Management Tests...')
    const projectTests = await runProjectManagementTests()
    categories.push(projectTests)
    totalTests += projectTests.total
    completedTests += projectTests.total
    setOverallProgress((completedTests / totalTests) * 100)

    // Test 5: Task Management Tests
    setCurrentTest('Running Task Management Tests...')
    const taskTests = await runTaskManagementTests()
    categories.push(taskTests)
    totalTests += taskTests.total
    completedTests += taskTests.total
    setOverallProgress((completedTests / totalTests) * 100)

    // Test 6: Document Management Tests
    setCurrentTest('Running Document Management Tests...')
    const documentTests = await runDocumentManagementTests()
    categories.push(documentTests)
    totalTests += documentTests.total
    completedTests += documentTests.total
    setOverallProgress((completedTests / totalTests) * 100)

    // Test 7: Change Request Tests
    setCurrentTest('Running Change Request Tests...')
    const changeRequestTests = await runChangeRequestTests()
    categories.push(changeRequestTests)
    totalTests += changeRequestTests.total
    completedTests += changeRequestTests.total
    setOverallProgress((completedTests / totalTests) * 100)

    setTestResults(categories)
    setIsRunning(false)
    setCurrentTest('')
    setOverallProgress(100)
  }

  const runAuthenticationTests = async (): Promise<TestCategory> => {
    const tests: TestResult[] = []

    // Test 1: API Health Check
    try {
      const startTime = performance.now()
      const response = await apiClient.get('/health')
      const duration = performance.now() - startTime
      
      tests.push({
        name: 'API Health Check',
        passed: true,
        duration,
        details: 'API server is reachable',
        category: 'auth'
      })
    } catch (error: any) {
      tests.push({
        name: 'API Health Check',
        passed: false,
        error: error.message || 'Connection failed',
        details: 'API server is not reachable',
        category: 'auth'
      })
    }

    // Test 2: User Login
    try {
      const startTime = performance.now()
      const response = await AuthService.login(testCredentials.email, testCredentials.password)
      const duration = performance.now() - startTime
      
      tests.push({
        name: 'User Login',
        passed: true,
        duration,
        details: `Logged in as ${response.user.name}`,
        category: 'auth'
      })
    } catch (error: any) {
      tests.push({
        name: 'User Login',
        passed: false,
        error: error.message || 'Authentication failed',
        details: 'Login failed with provided credentials',
        category: 'auth'
      })
    }

    // Test 3: Get Current User
    try {
      const startTime = performance.now()
      const user = await AuthService.getCurrentUser()
      const duration = performance.now() - startTime
      
      tests.push({
        name: 'Get Current User',
        passed: true,
        duration,
        details: `Retrieved user: ${user.name}`,
        category: 'auth'
      })
    } catch (error: any) {
      tests.push({
        name: 'Get Current User',
        passed: false,
        error: error.message || 'Failed to get user',
        details: 'Could not retrieve current user data',
        category: 'auth'
      })
    }

    // Test 4: Token Refresh
    try {
      const startTime = performance.now()
      const response = await AuthService.refreshToken()
      const duration = performance.now() - startTime
      
      tests.push({
        name: 'Token Refresh',
        passed: true,
        duration,
        details: 'Token refreshed successfully',
        category: 'auth'
      })
    } catch (error: any) {
      tests.push({
        name: 'Token Refresh',
        passed: false,
        error: error.message || 'Token refresh failed',
        details: 'Could not refresh authentication token',
        category: 'auth'
      })
    }

    const passed = tests.filter(t => t.passed).length
    const total = tests.length
    const passRate = total > 0 ? Math.round((passed / total) * 100) : 0

    return {
      name: 'Authentication',
      tests,
      passed,
      total,
      passRate
    }
  }

  const runSystemHealthTests = async (): Promise<TestCategory> => {
    const tests: TestResult[] = []

    // Test 1: API Status
    try {
      const startTime = performance.now()
      const response = await apiClient.get('/status')
      const duration = performance.now() - startTime
      
      tests.push({
        name: 'API Status',
        passed: true,
        duration,
        details: 'API status endpoint working',
        category: 'system'
      })
    } catch (error: any) {
      tests.push({
        name: 'API Status',
        passed: false,
        error: error.message || 'Status check failed',
        details: 'API status endpoint not working',
        category: 'system'
      })
    }

    // Test 2: API Info
    try {
      const startTime = performance.now()
      const response = await apiClient.get('/info')
      const duration = performance.now() - startTime
      
      tests.push({
        name: 'API Info',
        passed: true,
        duration,
        details: `API version: ${response.data?.api_version}`,
        category: 'system'
      })
    } catch (error: any) {
      tests.push({
        name: 'API Info',
        passed: false,
        error: error.message || 'Info check failed',
        details: 'API info endpoint not working',
        category: 'system'
      })
    }

    const passed = tests.filter(t => t.passed).length
    const total = tests.length
    const passRate = total > 0 ? Math.round((passed / total) * 100) : 0

    return {
      name: 'System Health',
      tests,
      passed,
      total,
      passRate
    }
  }

  const runUserManagementTests = async (): Promise<TestCategory> => {
    const tests: TestResult[] = []

    // Test 1: Get Users List
    try {
      const startTime = performance.now()
      const response = await UsersService.getUsers({}, 1, 5)
      const duration = performance.now() - startTime
      
      tests.push({
        name: 'Get Users List',
        passed: true,
        duration,
        details: `Retrieved ${response.data.length} users`,
        category: 'users'
      })
    } catch (error: any) {
      tests.push({
        name: 'Get Users List',
        passed: false,
        error: error.message || 'Failed to get users',
        details: 'Could not retrieve users list',
        category: 'users'
      })
    }

    // Test 2: Get User Profile
    try {
      const startTime = performance.now()
      const user = await AuthService.getCurrentUser()
      const response = await UsersService.getUser(user.id)
      const duration = performance.now() - startTime
      
      tests.push({
        name: 'Get User Profile',
        passed: true,
        duration,
        details: `Retrieved profile for ${response.name}`,
        category: 'users'
      })
    } catch (error: any) {
      tests.push({
        name: 'Get User Profile',
        passed: false,
        error: error.message || 'Failed to get user profile',
        details: 'Could not retrieve user profile',
        category: 'users'
      })
    }

    const passed = tests.filter(t => t.passed).length
    const total = tests.length
    const passRate = total > 0 ? Math.round((passed / total) * 100) : 0

    return {
      name: 'User Management',
      tests,
      passed,
      total,
      passRate
    }
  }

  const runProjectManagementTests = async (): Promise<TestCategory> => {
    const tests: TestResult[] = []

    // Test 1: Get Projects List
    try {
      const startTime = performance.now()
      const response = await ProjectsService.getProjects({}, 1, 5)
      const duration = performance.now() - startTime
      
      tests.push({
        name: 'Get Projects List',
        passed: true,
        duration,
        details: `Retrieved ${response.data.length} projects`,
        category: 'projects'
      })
    } catch (error: any) {
      tests.push({
        name: 'Get Projects List',
        passed: false,
        error: error.message || 'Failed to get projects',
        details: 'Could not retrieve projects list',
        category: 'projects'
      })
    }

    // Test 2: Create Project (if we have a test project)
    try {
      const startTime = performance.now()
      const projectData = {
        name: 'Test Project - API Integration',
        description: 'Test project for API integration testing',
        status: 'active',
        start_date: new Date().toISOString(),
        end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString()
      }
      
      const response = await ProjectsService.createProject(projectData)
      const duration = performance.now() - startTime
      
      tests.push({
        name: 'Create Project',
        passed: true,
        duration,
        details: `Created project: ${response.name}`,
        category: 'projects'
      })
    } catch (error: any) {
      tests.push({
        name: 'Create Project',
        passed: false,
        error: error.message || 'Failed to create project',
        details: 'Could not create new project',
        category: 'projects'
      })
    }

    const passed = tests.filter(t => t.passed).length
    const total = tests.length
    const passRate = total > 0 ? Math.round((passed / total) * 100) : 0

    return {
      name: 'Project Management',
      tests,
      passed,
      total,
      passRate
    }
  }

  const runTaskManagementTests = async (): Promise<TestCategory> => {
    const tests: TestResult[] = []

    // Test 1: Get Tasks List
    try {
      const startTime = performance.now()
      const response = await TasksService.getTasks('test-project-id', {})
      const duration = performance.now() - startTime
      
      tests.push({
        name: 'Get Tasks List',
        passed: true,
        duration,
        details: `Retrieved ${response.data.length} tasks`,
        category: 'tasks'
      })
    } catch (error: any) {
      tests.push({
        name: 'Get Tasks List',
        passed: false,
        error: error.message || 'Failed to get tasks',
        details: 'Could not retrieve tasks list',
        category: 'tasks'
      })
    }

    const passed = tests.filter(t => t.passed).length
    const total = tests.length
    const passRate = total > 0 ? Math.round((passed / total) * 100) : 0

    return {
      name: 'Task Management',
      tests,
      passed,
      total,
      passRate
    }
  }

  const runDocumentManagementTests = async (): Promise<TestCategory> => {
    const tests: TestResult[] = []

    // Test 1: Get Documents List
    try {
      const startTime = performance.now()
      const response = await DocumentsService.getDocuments({}, 1, 5)
      const duration = performance.now() - startTime
      
      tests.push({
        name: 'Get Documents List',
        passed: true,
        duration,
        details: `Retrieved ${response.data.length} documents`,
        category: 'documents'
      })
    } catch (error: any) {
      tests.push({
        name: 'Get Documents List',
        passed: false,
        error: error.message || 'Failed to get documents',
        details: 'Could not retrieve documents list',
        category: 'documents'
      })
    }

    const passed = tests.filter(t => t.passed).length
    const total = tests.length
    const passRate = total > 0 ? Math.round((passed / total) * 100) : 0

    return {
      name: 'Document Management',
      tests,
      passed,
      total,
      passRate
    }
  }

  const runChangeRequestTests = async (): Promise<TestCategory> => {
    const tests: TestResult[] = []

    // Test 1: Get Change Requests List
    try {
      const startTime = performance.now()
      const response = await ChangeRequestsService.getChangeRequests({}, 1, 5)
      const duration = performance.now() - startTime
      
      tests.push({
        name: 'Get Change Requests List',
        passed: true,
        duration,
        details: `Retrieved ${response.data.length} change requests`,
        category: 'change-requests'
      })
    } catch (error: any) {
      tests.push({
        name: 'Get Change Requests List',
        passed: false,
        error: error.message || 'Failed to get change requests',
        details: 'Could not retrieve change requests list',
        category: 'change-requests'
      })
    }

    const passed = tests.filter(t => t.passed).length
    const total = tests.length
    const passRate = total > 0 ? Math.round((passed / total) * 100) : 0

    return {
      name: 'Change Requests',
      tests,
      passed,
      total,
      passRate
    }
  }

  const getOverallStats = () => {
    const totalTests = testResults.reduce((sum, category) => sum + category.total, 0)
    const passedTests = testResults.reduce((sum, category) => sum + category.passed, 0)
    const overallPassRate = totalTests > 0 ? Math.round((passedTests / totalTests) * 100) : 0
    
    return { totalTests, passedTests, overallPassRate }
  }

  const getStatusIcon = (passed: boolean) => {
    return passed ? (
      <CheckCircle className="h-4 w-4 text-green-500" />
    ) : (
      <XCircle className="h-4 w-4 text-red-500" />
    )
  }

  const getStatusBadge = (passRate: number) => {
    if (passRate >= 90) {
      return <Badge className="bg-green-100 text-green-800">Excellent</Badge>
    } else if (passRate >= 70) {
      return <Badge className="bg-yellow-100 text-yellow-800">Good</Badge>
    } else {
      return <Badge className="bg-red-100 text-red-800">Needs Improvement</Badge>
    }
  }

  const stats = getOverallStats()

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <span>🧪 Comprehensive API Integration Test</span>
            {isRunning && <Clock className="h-4 w-4 animate-spin" />}
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center gap-4">
            <Button 
              onClick={runComprehensiveTests} 
              disabled={isRunning}
              className="flex items-center gap-2"
            >
              {isRunning ? (
                <>
                  <Clock className="h-4 w-4 animate-spin" />
                  Running Tests...
                </>
              ) : (
                'Run Comprehensive Tests'
              )}
            </Button>
            
            {isRunning && (
              <div className="flex-1">
                <div className="flex items-center justify-between text-sm text-gray-600 mb-1">
                  <span>Overall Progress</span>
                  <span>{Math.round(overallProgress)}%</span>
                </div>
                <Progress value={overallProgress} className="h-2" />
                {currentTest && (
                  <p className="text-sm text-gray-500 mt-1">{currentTest}</p>
                )}
              </div>
            )}
          </div>

          {testResults.length > 0 && (
            <div className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card>
                  <CardContent className="pt-6">
                    <div className="text-2xl font-bold">{stats.totalTests}</div>
                    <p className="text-xs text-gray-600">Total Tests</p>
                  </CardContent>
                </Card>
                <Card>
                  <CardContent className="pt-6">
                    <div className="text-2xl font-bold text-green-600">{stats.passedTests}</div>
                    <p className="text-xs text-gray-600">Passed</p>
                  </CardContent>
                </Card>
                <Card>
                  <CardContent className="pt-6">
                    <div className="text-2xl font-bold">{stats.overallPassRate}%</div>
                    <p className="text-xs text-gray-600">Pass Rate</p>
                  </CardContent>
                </Card>
              </div>

              {stats.overallPassRate < 90 && (
                <Alert>
                  <AlertTriangle className="h-4 w-4" />
                  <AlertDescription>
                    Some tests are failing. Please check the individual test results below and ensure the backend API is running and accessible.
                  </AlertDescription>
                </Alert>
              )}

              <div className="space-y-4">
                {testResults.map((category) => (
                  <Card key={category.name}>
                    <CardHeader>
                      <div className="flex items-center justify-between">
                        <CardTitle className="text-lg">{category.name}</CardTitle>
                        <div className="flex items-center gap-2">
                          {getStatusBadge(category.passRate)}
                          <span className="text-sm text-gray-600">
                            {category.passed}/{category.total} tests passed
                          </span>
                        </div>
                      </div>
                    </CardHeader>
                    <CardContent>
                      <div className="space-y-2">
                        {category.tests.map((test, index) => (
                          <div key={index} className="flex items-center justify-between p-3 border rounded-lg">
                            <div className="flex items-center gap-3">
                              {getStatusIcon(test.passed)}
                              <div>
                                <p className="font-medium">{test.name}</p>
                                {test.details && (
                                  <p className="text-sm text-gray-600">{test.details}</p>
                                )}
                                {test.error && (
                                  <p className="text-sm text-red-600">{test.error}</p>
                                )}
                              </div>
                            </div>
                            {test.duration && (
                              <span className="text-sm text-gray-500">
                                {Math.round(test.duration)}ms
                              </span>
                            )}
                          </div>
                        ))}
                      </div>
                    </CardContent>
                  </Card>
                ))}
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

export default ComprehensiveApiIntegrationTest
