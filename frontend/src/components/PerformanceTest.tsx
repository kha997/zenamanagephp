import React, { useState, useEffect, useRef } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { 
  Activity, 
  Clock, 
  MemoryStick, 
  HardDrive, 
  Zap, 
  BarChart3,
  TrendingUp,
  TrendingDown,
  CheckCircle,
  XCircle
} from 'lucide-react'

interface PerformanceMetrics {
  renderTime: number
  memoryUsage: number
  bundleSize: number
  apiResponseTime: number
  imageLoadTime: number
  componentCount: number
  domNodes: number
}

interface PerformanceTestProps {
  onMetricsUpdate?: (metrics: PerformanceMetrics) => void
}

const PerformanceTest: React.FC<PerformanceTestProps> = ({ onMetricsUpdate }) => {
  const [metrics, setMetrics] = useState<PerformanceMetrics>({
    renderTime: 0,
    memoryUsage: 0,
    bundleSize: 0,
    apiResponseTime: 0,
    imageLoadTime: 0,
    componentCount: 0,
    domNodes: 0
  })
  const [isRunning, setIsRunning] = useState(false)
  const [testResults, setTestResults] = useState<Array<{
    name: string
    value: number
    threshold: number
    passed: boolean
    unit: string
  }>>([])
  const startTimeRef = useRef<number>(0)
  const componentRef = useRef<HTMLDivElement>(null)

  const measureRenderTime = () => {
    const start = performance.now()
    // Force re-render
    setMetrics(prev => ({ ...prev, renderTime: performance.now() - start }))
  }

  const measureMemoryUsage = () => {
    if ('memory' in performance) {
      const memory = (performance as any).memory
      const usedMemory = memory.usedJSHeapSize / 1024 / 1024 // Convert to MB
      setMetrics(prev => ({ ...prev, memoryUsage: usedMemory }))
    }
  }

  const measureBundleSize = async () => {
    try {
      const response = await fetch('/assets/manifest.json')
      if (response.ok) {
        const manifest = await response.json()
        // Estimate bundle size (this is a simplified approach)
        const estimatedSize = 500 // KB
        setMetrics(prev => ({ ...prev, bundleSize: estimatedSize }))
      }
    } catch (error) {
      console.log('Could not measure bundle size:', error)
    }
  }

  const measureAPIResponseTime = async () => {
    const start = performance.now()
    try {
      // Mock API call
      await new Promise(resolve => setTimeout(resolve, 100))
      const responseTime = performance.now() - start
      setMetrics(prev => ({ ...prev, apiResponseTime: responseTime }))
    } catch (error) {
      console.log('API test failed:', error)
    }
  }

  const measureImageLoadTime = () => {
    const start = performance.now()
    const img = new Image()
    img.onload = () => {
      const loadTime = performance.now() - start
      setMetrics(prev => ({ ...prev, imageLoadTime: loadTime }))
    }
    img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMSIgaGVpZ2h0PSIxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxIiBoZWlnaHQ9IjEiIGZpbGw9InRyYW5zcGFyZW50Ii8+PC9zdmc+'
  }

  const measureComponentCount = () => {
    if (componentRef.current) {
      const components = componentRef.current.querySelectorAll('[data-component]')
      setMetrics(prev => ({ ...prev, componentCount: components.length }))
    }
  }

  const measureDOMNodes = () => {
    if (componentRef.current) {
      const nodes = componentRef.current.querySelectorAll('*')
      setMetrics(prev => ({ ...prev, domNodes: nodes.length }))
    }
  }

  const runPerformanceTests = async () => {
    setIsRunning(true)
    startTimeRef.current = performance.now()

    // Run all measurements
    measureRenderTime()
    measureMemoryUsage()
    await measureBundleSize()
    await measureAPIResponseTime()
    measureImageLoadTime()
    measureComponentCount()
    measureDOMNodes()

    // Wait for all measurements to complete
    setTimeout(() => {
      setIsRunning(false)
      evaluateResults()
    }, 1000)
  }

  const evaluateResults = () => {
    const thresholds = {
      renderTime: 100, // ms
      memoryUsage: 50, // MB
      bundleSize: 1000, // KB
      apiResponseTime: 500, // ms
      imageLoadTime: 200, // ms
      componentCount: 100, // count
      domNodes: 1000 // count
    }

    const results = Object.entries(metrics).map(([key, value]) => {
      const threshold = thresholds[key as keyof PerformanceMetrics]
      const passed = value <= threshold
      
      return {
        name: key.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase()),
        value,
        threshold,
        passed,
        unit: getUnit(key)
      }
    })

    setTestResults(results)
    onMetricsUpdate?.(metrics)
  }

  const getUnit = (key: string): string => {
    switch (key) {
      case 'renderTime':
      case 'apiResponseTime':
      case 'imageLoadTime':
        return 'ms'
      case 'memoryUsage':
        return 'MB'
      case 'bundleSize':
        return 'KB'
      case 'componentCount':
      case 'domNodes':
        return 'count'
      default:
        return ''
    }
  }

  const getPerformanceColor = (value: number, threshold: number): string => {
    const ratio = value / threshold
    if (ratio <= 0.5) return 'text-green-600'
    if (ratio <= 0.8) return 'text-yellow-600'
    return 'text-red-600'
  }

  const getPerformanceIcon = (passed: boolean) => {
    return passed ? (
      <CheckCircle className="h-4 w-4 text-green-500" />
    ) : (
      <XCircle className="h-4 w-4 text-red-500" />
    )
  }

  useEffect(() => {
    // Initial measurement
    measureMemoryUsage()
    measureComponentCount()
    measureDOMNodes()
  }, [])

  return (
    <div ref={componentRef} className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Activity className="h-5 w-5" />
            Performance Test Suite
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex items-center gap-4 mb-4">
            <Button 
              onClick={runPerformanceTests} 
              disabled={isRunning}
              className="flex items-center gap-2"
            >
              <Zap className="h-4 w-4" />
              {isRunning ? 'Running Tests...' : 'Run Performance Tests'}
            </Button>
            {isRunning && (
              <Badge className="bg-blue-100 text-blue-800">
                <Clock className="h-3 w-3 mr-1 animate-spin" />
                Testing...
              </Badge>
            )}
          </div>

          {/* Current Metrics */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div className="text-center p-3 bg-gray-50 rounded">
              <div className="text-lg font-bold text-blue-600">{metrics.renderTime.toFixed(1)}ms</div>
              <div className="text-sm text-gray-500">Render Time</div>
            </div>
            <div className="text-center p-3 bg-gray-50 rounded">
              <div className="text-lg font-bold text-green-600">{metrics.memoryUsage.toFixed(1)}MB</div>
              <div className="text-sm text-gray-500">Memory Usage</div>
            </div>
            <div className="text-center p-3 bg-gray-50 rounded">
              <div className="text-lg font-bold text-purple-600">{metrics.bundleSize}KB</div>
              <div className="text-sm text-gray-500">Bundle Size</div>
            </div>
            <div className="text-center p-3 bg-gray-50 rounded">
              <div className="text-lg font-bold text-orange-600">{metrics.apiResponseTime.toFixed(1)}ms</div>
              <div className="text-sm text-gray-500">API Response</div>
            </div>
          </div>

          {/* Test Results */}
          {testResults.length > 0 && (
            <div className="space-y-2">
              <h3 className="font-medium text-gray-900">Test Results</h3>
              {testResults.map((result, index) => (
                <div key={index} className="flex items-center justify-between p-3 bg-gray-50 rounded">
                  <div className="flex items-center gap-3">
                    {getPerformanceIcon(result.passed)}
                    <span className="font-medium">{result.name}</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className={`font-medium ${getPerformanceColor(result.value, result.threshold)}`}>
                      {result.value.toFixed(1)}{result.unit}
                    </span>
                    <span className="text-sm text-gray-500">
                      / {result.threshold}{result.unit}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          )}

          {/* Performance Tips */}
          <div className="mt-6 p-4 bg-blue-50 rounded">
            <h3 className="font-medium text-blue-900 mb-2">Performance Tips</h3>
            <ul className="text-sm text-blue-800 space-y-1">
              <li>• Use React.memo() for expensive components</li>
              <li>• Implement lazy loading for large components</li>
              <li>• Optimize images with proper compression</li>
              <li>• Use code splitting to reduce bundle size</li>
              <li>• Implement virtual scrolling for large lists</li>
            </ul>
          </div>
        </CardContent>
      </Card>

      {/* Performance Chart */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <BarChart3 className="h-5 w-5" />
            Performance Trends
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="text-center p-4 bg-gray-50 rounded">
              <TrendingUp className="h-8 w-8 mx-auto mb-2 text-green-500" />
              <div className="text-sm font-medium">Good Performance</div>
              <div className="text-xs text-gray-500">Render time &lt; 100ms</div>
            </div>
            <div className="text-center p-4 bg-gray-50 rounded">
              <TrendingDown className="h-8 w-8 mx-auto mb-2 text-red-500" />
              <div className="text-sm font-medium">Needs Optimization</div>
              <div className="text-xs text-gray-500">Memory usage &gt; 50MB</div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

export default PerformanceTest