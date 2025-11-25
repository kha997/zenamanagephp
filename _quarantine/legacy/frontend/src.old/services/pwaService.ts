import { toast } from 'react-hot-toast'

interface PWAInstallPrompt {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

interface PWAEvent extends Event {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

class PWAService {
  private deferredPrompt: PWAEvent | null = null
  private isInstalled = false
  private isStandalone = false
  private isOnline = navigator.onLine
  private registration: ServiceWorkerRegistration | null = null

  constructor() {
    this.init()
  }

  private init() {
    // Check if app is already installed
    this.isInstalled = window.matchMedia('(display-mode: standalone)').matches
    this.isStandalone = window.matchMedia('(display-mode: standalone)').matches

    // Listen for install prompt
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault()
      this.deferredPrompt = e as PWAEvent
      this.showInstallButton()
    })

    // Listen for app installed
    window.addEventListener('appinstalled', () => {
      this.isInstalled = true
      this.deferredPrompt = null
      toast.success('App installed successfully!')
    })

    // Listen for online/offline status
    window.addEventListener('online', () => {
      this.isOnline = true
      this.handleOnlineStatus()
    })

    window.addEventListener('offline', () => {
      this.isOnline = false
      this.handleOfflineStatus()
    })

    // Register service worker
    this.registerServiceWorker()
  }

  private async registerServiceWorker() {
    if ('serviceWorker' in navigator) {
      try {
        this.registration = await navigator.serviceWorker.register('/sw.js')
        console.log('Service Worker registered successfully:', this.registration)
        
        // Listen for service worker updates
        this.registration.addEventListener('updatefound', () => {
          const newWorker = this.registration!.installing
          if (newWorker) {
            newWorker.addEventListener('statechange', () => {
              if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                this.showUpdateAvailable()
              }
            })
          }
        })
      } catch (error) {
        console.error('Service Worker registration failed:', error)
      }
    }
  }

  private showInstallButton() {
    // Create install button if not exists
    if (!document.getElementById('pwa-install-button')) {
      const installButton = document.createElement('button')
      installButton.id = 'pwa-install-button'
      installButton.innerHTML = '📱 Install App'
      installButton.className = 'fixed bottom-4 right-4 bg-blue-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 hover:bg-blue-600 transition-colors'
      installButton.onclick = () => this.installApp()
      
      document.body.appendChild(installButton)
      
      // Auto-hide after 10 seconds
      setTimeout(() => {
        if (installButton.parentNode) {
          installButton.parentNode.removeChild(installButton)
        }
      }, 10000)
    }
  }

  private showUpdateAvailable() {
    toast.success('App update available! Refreshing...', {
      duration: 3000,
      action: {
        label: 'Update',
        onClick: () => this.updateApp()
      }
    })
  }

  private handleOnlineStatus() {
    toast.success('Back online!', { duration: 2000 })
    
    // Sync any pending data
    this.syncPendingData()
  }

  private handleOfflineStatus() {
    toast.error('You are offline. Some features may be limited.', { duration: 5000 })
  }

  private async syncPendingData() {
    if (this.registration && this.registration.sync) {
      try {
        await this.registration.sync.register('background-sync')
      } catch (error) {
        console.error('Background sync registration failed:', error)
      }
    }
  }

  // Public methods
  async installApp(): Promise<boolean> {
    if (!this.deferredPrompt) {
      toast.error('App installation not available')
      return false
    }

    try {
      await this.deferredPrompt.prompt()
      const choiceResult = await this.deferredPrompt.userChoice
      
      if (choiceResult.outcome === 'accepted') {
        toast.success('Installing app...')
        return true
      } else {
        toast.info('App installation cancelled')
        return false
      }
    } catch (error) {
      console.error('App installation failed:', error)
      toast.error('App installation failed')
      return false
    }
  }

  async updateApp(): Promise<void> {
    if (this.registration && this.registration.waiting) {
      this.registration.waiting.postMessage({ type: 'SKIP_WAITING' })
      
      // Reload the page after update
      window.location.reload()
    }
  }

  async uninstallApp(): Promise<void> {
    // This would typically involve server-side tracking
    toast.info('App uninstalled. Thank you for using ZenaManage!')
  }

  // Cache management
  async clearCache(): Promise<void> {
    if ('caches' in window) {
      const cacheNames = await caches.keys()
      await Promise.all(
        cacheNames.map(cacheName => caches.delete(cacheName))
      )
      toast.success('Cache cleared successfully')
    }
  }

  async getCacheSize(): Promise<number> {
    if (!('caches' in window)) return 0

    let totalSize = 0
    const cacheNames = await caches.keys()
    
    for (const cacheName of cacheNames) {
      const cache = await caches.open(cacheName)
      const requests = await cache.keys()
      
      for (const request of requests) {
        const response = await cache.match(request)
        if (response) {
          const blob = await response.blob()
          totalSize += blob.size
        }
      }
    }
    
    return totalSize
  }

  // Offline data management
  async storeOfflineData(key: string, data: any): Promise<void> {
    try {
      const offlineData = JSON.parse(localStorage.getItem('offline-data') || '{}')
      offlineData[key] = {
        data,
        timestamp: Date.now()
      }
      localStorage.setItem('offline-data', JSON.stringify(offlineData))
    } catch (error) {
      console.error('Failed to store offline data:', error)
    }
  }

  async getOfflineData(key: string): Promise<any> {
    try {
      const offlineData = JSON.parse(localStorage.getItem('offline-data') || '{}')
      return offlineData[key]?.data || null
    } catch (error) {
      console.error('Failed to get offline data:', error)
      return null
    }
  }

  async clearOfflineData(): Promise<void> {
    localStorage.removeItem('offline-data')
    toast.success('Offline data cleared')
  }

  // Push notifications
  async requestNotificationPermission(): Promise<boolean> {
    if (!('Notification' in window)) {
      toast.error('Notifications not supported')
      return false
    }

    if (Notification.permission === 'granted') {
      return true
    }

    const permission = await Notification.requestPermission()
    return permission === 'granted'
  }

  async sendNotification(title: string, options?: NotificationOptions): Promise<void> {
    if (await this.requestNotificationPermission()) {
      new Notification(title, {
        icon: '/icons/icon-192x192.png',
        badge: '/icons/badge-72x72.png',
        ...options
      })
    }
  }

  // Background sync
  async registerBackgroundSync(tag: string): Promise<void> {
    if (this.registration && this.registration.sync) {
      try {
        await this.registration.sync.register(tag)
      } catch (error) {
        console.error('Background sync registration failed:', error)
      }
    }
  }

  // App state management
  getAppState() {
    return {
      isInstalled: this.isInstalled,
      isStandalone: this.isStandalone,
      isOnline: this.isOnline,
      canInstall: !!this.deferredPrompt,
      hasServiceWorker: !!this.registration
    }
  }

  // Utility methods
  isMobile(): boolean {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
  }

  isIOS(): boolean {
    return /iPad|iPhone|iPod/.test(navigator.userAgent)
  }

  isAndroid(): boolean {
    return /Android/.test(navigator.userAgent)
  }

  getDeviceInfo() {
    return {
      userAgent: navigator.userAgent,
      platform: navigator.platform,
      isMobile: this.isMobile(),
      isIOS: this.isIOS(),
      isAndroid: this.isAndroid(),
      screenWidth: window.screen.width,
      screenHeight: window.screen.height,
      viewportWidth: window.innerWidth,
      viewportHeight: window.innerHeight
    }
  }

  // Share API
  async share(data: ShareData): Promise<boolean> {
    if (navigator.share) {
      try {
        await navigator.share(data)
        return true
      } catch (error) {
        console.error('Share failed:', error)
        return false
      }
    } else {
      // Fallback to clipboard
      if (data.url) {
        await navigator.clipboard.writeText(data.url)
        toast.success('Link copied to clipboard')
      }
      return false
    }
  }

  // File handling
  async openFile(accept: string = '*'): Promise<File | null> {
    return new Promise((resolve) => {
      const input = document.createElement('input')
      input.type = 'file'
      input.accept = accept
      input.onchange = (e) => {
        const file = (e.target as HTMLInputElement).files?.[0] || null
        resolve(file)
      }
      input.click()
    })
  }

  // Vibration API
  vibrate(pattern: number | number[]): void {
    if ('vibrate' in navigator) {
      navigator.vibrate(pattern)
    }
  }

  // Battery API
  async getBatteryInfo(): Promise<any> {
    if ('getBattery' in navigator) {
      try {
        const battery = await (navigator as any).getBattery()
        return {
          level: battery.level,
          charging: battery.charging,
          chargingTime: battery.chargingTime,
          dischargingTime: battery.dischargingTime
        }
      } catch (error) {
        console.error('Battery API not supported:', error)
        return null
      }
    }
    return null
  }
}

// Create singleton instance
const pwaService = new PWAService()

export default pwaService
export { PWAService }
