import { useEffect, useState } from 'react'

interface PerformanceMetrics {
  // Core Web Vitals
  lcp: number | null // Largest Contentful Paint
  fid: number | null // First Input Delay
  cls: number | null // Cumulative Layout Shift
  fcp: number | null // First Contentful Paint
  ttfb: number | null // Time to First Byte
  
  // Performance Metrics
  loadTime: number | null
  domContentLoaded: number | null
  firstPaint: number | null
  firstContentfulPaint: number | null
  
  // Resource Metrics
  totalResources: number
  totalSize: number
  jsSize: number
  cssSize: number
  imageSize: number
  
  // Memory Metrics
  memoryUsage: number | null
  memoryLimit: number | null
  
  // Network Metrics
  connectionType: string | null
  effectiveType: string | null
  downlink: number | null
  rtt: number | null
  
  // User Agent
  userAgent: string
  viewport: { width: number; height: number }
  devicePixelRatio: number
  
  // Timestamp
  timestamp: number
}

class PerformanceMonitor {
  private metrics: PerformanceMetrics
  private observers: PerformanceObserver[] = []
  private isEnabled: boolean = false

  constructor() {
    this.metrics = this.initializeMetrics()
    this.isEnabled = this.shouldEnableMonitoring()
  }

  private initializeMetrics(): PerformanceMetrics {
    return {
      lcp: null,
      fid: null,
      cls: null,
      fcp: null,
      ttfb: null,
      loadTime: null,
      domContentLoaded: null,
      firstPaint: null,
      firstContentfulPaint: null,
      totalResources: 0,
      totalSize: 0,
      jsSize: 0,
      cssSize: 0,
      imageSize: 0,
      memoryUsage: null,
      memoryLimit: null,
      connectionType: null,
      effectiveType: null,
      downlink: null,
      rtt: null,
      userAgent: navigator.userAgent,
      viewport: {
        width: window.innerWidth,
        height: window.innerHeight
      },
      devicePixelRatio: window.devicePixelRatio,
      timestamp: Date.now()
    }
  }

  private shouldEnableMonitoring(): boolean {
    // Enable monitoring based on environment and sample rate
    const isProduction = process.env.NODE_ENV === 'production'
    const sampleRate = parseFloat(process.env.VITE_PERFORMANCE_SAMPLE_RATE || '0.1')
    const isEnabled = process.env.VITE_ENABLE_PERFORMANCE_MONITORING === 'true'
    
    return isEnabled && isProduction && Math.random() < sampleRate
  }

  public startMonitoring(): void {
    if (!this.isEnabled) return

    console.log('Performance monitoring started')
    
    // Monitor Core Web Vitals
    this.observeLCP()
    this.observeFID()
    this.observeCLS()
    this.observeFCP()
    
    // Monitor page load
    this.observePageLoad()
    
    // Monitor resources
    this.observeResources()
    
    // Monitor memory usage
    this.observeMemory()
    
    // Monitor network
    this.observeNetwork()
    
    // Send metrics after page load
    window.addEventListener('load', () => {
      setTimeout(() => {
        this.collectMetrics()
        this.sendMetrics()
      }, 2000)
    })
  }

  private observeLCP(): void {
    if (!('PerformanceObserver' in window)) return

    try {
      const observer = new PerformanceObserver((list) => {
        const entries = list.getEntries()
        const lastEntry = entries[entries.length - 1] as any
        this.metrics.lcp = lastEntry.startTime
      })
      
      observer.observe({ entryTypes: ['largest-contentful-paint'] })
      this.observers.push(observer)
    } catch (error) {
      console.warn('LCP observation failed:', error)
    }
  }

  private observeFID(): void {
    if (!('PerformanceObserver' in window)) return

    try {
      const observer = new PerformanceObserver((list) => {
        const entries = list.getEntries()
        entries.forEach((entry: any) => {
          this.metrics.fid = entry.processingStart - entry.startTime
        })
      })
      
      observer.observe({ entryTypes: ['first-input'] })
      this.observers.push(observer)
    } catch (error) {
      console.warn('FID observation failed:', error)
    }
  }

  private observeCLS(): void {
    if (!('PerformanceObserver' in window)) return

    try {
      let clsValue = 0
      const observer = new PerformanceObserver((list) => {
        const entries = list.getEntries()
        entries.forEach((entry: any) => {
          if (!entry.hadRecentInput) {
            clsValue += entry.value
          }
        })
        this.metrics.cls = clsValue
      })
      
      observer.observe({ entryTypes: ['layout-shift'] })
      this.observers.push(observer)
    } catch (error) {
      console.warn('CLS observation failed:', error)
    }
  }

  private observeFCP(): void {
    if (!('PerformanceObserver' in window)) return

    try {
      const observer = new PerformanceObserver((list) => {
        const entries = list.getEntries()
        entries.forEach((entry: any) => {
          if (entry.name === 'first-contentful-paint') {
            this.metrics.fcp = entry.startTime
          }
        })
      })
      
      observer.observe({ entryTypes: ['paint'] })
      this.observers.push(observer)
    } catch (error) {
      console.warn('FCP observation failed:', error)
    }
  }

  private observePageLoad(): void {
    window.addEventListener('load', () => {
      const navigation = performance.getEntriesByType('navigation')[0] as any
      if (navigation) {
        this.metrics.loadTime = navigation.loadEventEnd - navigation.loadEventStart
        this.metrics.domContentLoaded = navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart
        this.metrics.ttfb = navigation.responseStart - navigation.requestStart
      }
    })
  }

  private observeResources(): void {
    if (!('PerformanceObserver' in window)) return

    try {
      const observer = new PerformanceObserver((list) => {
        const entries = list.getEntries()
        entries.forEach((entry: any) => {
          this.metrics.totalResources++
          this.metrics.totalSize += entry.transferSize || 0
          
          if (entry.name.endsWith('.js')) {
            this.metrics.jsSize += entry.transferSize || 0
          } else if (entry.name.endsWith('.css')) {
            this.metrics.cssSize += entry.transferSize || 0
          } else if (entry.name.match(/\.(jpg|jpeg|png|gif|svg|webp)$/)) {
            this.metrics.imageSize += entry.transferSize || 0
          }
        })
      })
      
      observer.observe({ entryTypes: ['resource'] })
      this.observers.push(observer)
    } catch (error) {
      console.warn('Resource observation failed:', error)
    }
  }

  private observeMemory(): void {
    if ('memory' in performance) {
      const memory = (performance as any).memory
      this.metrics.memoryUsage = memory.usedJSHeapSize
      this.metrics.memoryLimit = memory.jsHeapSizeLimit
    }
  }

  private observeNetwork(): void {
    if ('connection' in navigator) {
      const connection = (navigator as any).connection
      this.metrics.connectionType = connection.type
      this.metrics.effectiveType = connection.effectiveType
      this.metrics.downlink = connection.downlink
      this.metrics.rtt = connection.rtt
    }
  }

  private collectMetrics(): void {
    // Collect paint metrics
    const paintEntries = performance.getEntriesByType('paint')
    paintEntries.forEach((entry) => {
      if (entry.name === 'first-paint') {
        this.metrics.firstPaint = entry.startTime
      }
      if (entry.name === 'first-contentful-paint') {
        this.metrics.firstContentfulPaint = entry.startTime
      }
    })
  }

  private async sendMetrics(): Promise<void> {
    try {
      // Send to analytics service
      if (process.env.VITE_GOOGLE_ANALYTICS_ID) {
        this.sendToGoogleAnalytics()
      }
      
      // Send to custom analytics endpoint
      if (process.env.VITE_API_BASE_URL) {
        await this.sendToCustomEndpoint()
      }
      
      // Send to error tracking service
      if (process.env.VITE_SENTRY_DSN) {
        this.sendToSentry()
      }
      
      console.log('Performance metrics sent:', this.metrics)
    } catch (error) {
      console.error('Failed to send performance metrics:', error)
    }
  }

  private sendToGoogleAnalytics(): void {
    if (typeof gtag !== 'undefined') {
      gtag('event', 'performance_metrics', {
        custom_map: {
          lcp: this.metrics.lcp,
          fid: this.metrics.fid,
          cls: this.metrics.cls,
          fcp: this.metrics.fcp,
          ttfb: this.metrics.ttfb
        }
      })
    }
  }

  private async sendToCustomEndpoint(): Promise<void> {
    try {
      await fetch(`${process.env.VITE_API_BASE_URL}/api/analytics/performance`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        },
        body: JSON.stringify({
          metrics: this.metrics,
          url: window.location.href,
          referrer: document.referrer
        })
      })
    } catch (error) {
      console.error('Failed to send metrics to custom endpoint:', error)
    }
  }

  private sendToSentry(): void {
    if (typeof Sentry !== 'undefined') {
      Sentry.addBreadcrumb({
        category: 'performance',
        message: 'Performance metrics collected',
        data: this.metrics,
        level: 'info'
      })
    }
  }

  public getMetrics(): PerformanceMetrics {
    return { ...this.metrics }
  }

  public stopMonitoring(): void {
    this.observers.forEach(observer => observer.disconnect())
    this.observers = []
    console.log('Performance monitoring stopped')
  }

  // React Hook for Performance Monitoring
  public static usePerformanceMonitoring(): PerformanceMetrics {
    const [metrics, setMetrics] = useState<PerformanceMetrics>(() => ({
      lcp: null,
      fid: null,
      cls: null,
      fcp: null,
      ttfb: null,
      loadTime: null,
      domContentLoaded: null,
      firstPaint: null,
      firstContentfulPaint: null,
      totalResources: 0,
      totalSize: 0,
      jsSize: 0,
      cssSize: 0,
      imageSize: 0,
      memoryUsage: null,
      memoryLimit: null,
      connectionType: null,
      effectiveType: null,
      downlink: null,
      rtt: null,
      userAgent: navigator.userAgent,
      viewport: {
        width: window.innerWidth,
        height: window.innerHeight
      },
      devicePixelRatio: window.devicePixelRatio,
      timestamp: Date.now()
    }))

    useEffect(() => {
      const monitor = new PerformanceMonitor()
      monitor.startMonitoring()

      const interval = setInterval(() => {
        setMetrics(monitor.getMetrics())
      }, 5000)

      return () => {
        clearInterval(interval)
        monitor.stopMonitoring()
      }
    }, [])

    return metrics
  }
}

// Create singleton instance
const performanceMonitor = new PerformanceMonitor()

export default performanceMonitor
export { PerformanceMonitor, PerformanceMetrics }
