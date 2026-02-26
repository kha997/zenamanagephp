import React, { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { 
  Menu, 
  X, 
  Search, 
  Filter, 
  Plus, 
  ChevronDown,
  ChevronUp,
  SwipeUp,
  SwipeDown,
  TouchIcon,
  Smartphone,
  Tablet,
  Monitor,
  Wifi,
  WifiOff,
  Battery,
  BatteryLow,
  Volume2,
  VolumeX
} from 'lucide-react'
import { cn } from '../lib/utils'
import { slideUpMobile, slideDownMobile, touchFeedback, swipeAnimation } from '../utils/animations'
import { useTouchGestures, SWIPE_DIRECTIONS, GESTURE_THRESHOLDS } from '../services/touchGestureService'
import pwaService from '../services/pwaService'

interface ResponsiveLayoutProps {
  children: React.ReactNode
  className?: string
  showMobileHeader?: boolean
  showMobileFooter?: boolean
  enableGestures?: boolean
  enablePWA?: boolean
}

export const ResponsiveLayout: React.FC<ResponsiveLayoutProps> = ({
  children,
  className,
  showMobileHeader = true,
  showMobileFooter = true,
  enableGestures = true,
  enablePWA = true
}) => {
  const [isMobile, setIsMobile] = useState(false)
  const [isTablet, setIsTablet] = useState(false)
  const [isDesktop, setIsDesktop] = useState(false)
  const [screenSize, setScreenSize] = useState({ width: 0, height: 0 })
  const [orientation, setOrientation] = useState<'portrait' | 'landscape'>('portrait')
  const [isOnline, setIsOnline] = useState(navigator.onLine)
  const [batteryInfo, setBatteryInfo] = useState<any>(null)
  const [isMuted, setIsMuted] = useState(false)

  // Responsive breakpoints
  const breakpoints = {
    mobile: 768,
    tablet: 1024,
    desktop: 1200
  }

  // Update screen size and device type
  useEffect(() => {
    const updateScreenInfo = () => {
      const width = window.innerWidth
      const height = window.innerHeight
      
      setScreenSize({ width, height })
      setIsMobile(width < breakpoints.mobile)
      setIsTablet(width >= breakpoints.mobile && width < breakpoints.tablet)
      setIsDesktop(width >= breakpoints.desktop)
      setOrientation(width > height ? 'landscape' : 'portrait')
    }

    updateScreenInfo()
    window.addEventListener('resize', updateScreenInfo)
    window.addEventListener('orientationchange', updateScreenInfo)

    return () => {
      window.removeEventListener('resize', updateScreenInfo)
      window.removeEventListener('orientationchange', updateScreenInfo)
    }
  }, [])

  // Online/offline status
  useEffect(() => {
    const handleOnline = () => setIsOnline(true)
    const handleOffline = () => setIsOnline(false)

    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)

    return () => {
      window.removeEventListener('online', handleOnline)
      window.removeEventListener('offline', handleOffline)
    }
  }, [])

  // Battery info
  useEffect(() => {
    const getBatteryInfo = async () => {
      const battery = await pwaService.getBatteryInfo()
      setBatteryInfo(battery)
    }

    getBatteryInfo()
  }, [])

  // Touch gestures
  const gestureRef = useTouchGestures(
    {
      onSwipe: (gesture) => {
        if (isMobile && enableGestures) {
          handleSwipeGesture(gesture)
        }
      },
      onPinch: (gesture) => {
        if (isMobile && enableGestures) {
          handlePinchGesture(gesture)
        }
      },
      onTap: (point) => {
        if (isMobile && enableGestures) {
          handleTapGesture(point)
        }
      },
      onDoubleTap: (point) => {
        if (isMobile && enableGestures) {
          handleDoubleTapGesture(point)
        }
      },
      onLongPress: (point) => {
        if (isMobile && enableGestures) {
          handleLongPressGesture(point)
        }
      }
    },
    {
      swipeThreshold: GESTURE_THRESHOLDS.SWIPE,
      pinchThreshold: GESTURE_THRESHOLDS.PINCH,
      longPressDelay: GESTURE_THRESHOLDS.LONG_PRESS,
      doubleTapDelay: GESTURE_THRESHOLDS.DOUBLE_TAP
    }
  )

  const handleSwipeGesture = (gesture: any) => {
    console.log('Swipe gesture:', gesture)
    
    switch (gesture.direction) {
      case SWIPE_DIRECTIONS.LEFT:
        // Swipe left - go back or close modal
        break
      case SWIPE_DIRECTIONS.RIGHT:
        // Swipe right - go forward or open menu
        break
      case SWIPE_DIRECTIONS.UP:
        // Swipe up - scroll up or open bottom sheet
        break
      case SWIPE_DIRECTIONS.DOWN:
        // Swipe down - scroll down or close modal
        break
    }
  }

  const handlePinchGesture = (gesture: any) => {
    console.log('Pinch gesture:', gesture)
    
    if (gesture.scale > 1) {
      // Pinch out - zoom in
    } else {
      // Pinch in - zoom out
    }
  }

  const handleTapGesture = (point: any) => {
    console.log('Tap gesture:', point)
  }

  const handleDoubleTapGesture = (point: any) => {
    console.log('Double tap gesture:', point)
  }

  const handleLongPressGesture = (point: any) => {
    console.log('Long press gesture:', point)
  }

  // Device info component
  const DeviceInfo = () => (
    <div className="fixed top-4 left-4 z-50 flex items-center gap-2">
      <Badge variant="outline" className="text-xs">
        {isMobile ? <Smartphone className="h-3 w-3 mr-1" /> : 
         isTablet ? <Tablet className="h-3 w-3 mr-1" /> : 
         <Monitor className="h-3 w-3 mr-1" />}
        {isMobile ? 'Mobile' : isTablet ? 'Tablet' : 'Desktop'}
      </Badge>
      
      <Badge variant="outline" className="text-xs">
        {orientation === 'portrait' ? 'Portrait' : 'Landscape'}
      </Badge>
      
      <Badge variant="outline" className="text-xs">
        {screenSize.width}x{screenSize.height}
      </Badge>
      
      {isOnline ? (
        <Badge variant="outline" className="text-xs text-green-600">
          <Wifi className="h-3 w-3 mr-1" />
          Online
        </Badge>
      ) : (
        <Badge variant="outline" className="text-xs text-red-600">
          <WifiOff className="h-3 w-3 mr-1" />
          Offline
        </Badge>
      )}
      
      {batteryInfo && (
        <Badge variant="outline" className="text-xs">
          {batteryInfo.level < 0.2 ? (
            <BatteryLow className="h-3 w-3 mr-1" />
          ) : (
            <Battery className="h-3 w-3 mr-1" />
          )}
          {Math.round(batteryInfo.level * 100)}%
        </Badge>
      )}
    </div>
  )

  // Mobile header
  const MobileHeader = () => (
    <motion.header
      initial={{ y: -100 }}
      animate={{ y: 0 }}
      transition={{ duration: 0.3 }}
      className="fixed top-0 left-0 right-0 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 z-40"
    >
      <div className="flex items-center justify-between px-4 py-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" className="p-2">
            <Menu className="h-5 w-5" />
          </Button>
          <h1 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
            ZenaManage
          </h1>
        </div>
        
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="sm" className="p-2">
            <Search className="h-5 w-5" />
          </Button>
          <Button variant="ghost" size="sm" className="p-2">
            <Filter className="h-5 w-5" />
          </Button>
        </div>
      </div>
    </motion.header>
  )

  // Mobile footer
  const MobileFooter = () => (
    <motion.footer
      initial={{ y: 100 }}
      animate={{ y: 0 }}
      transition={{ duration: 0.3 }}
      className="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 z-40"
    >
      <div className="flex items-center justify-around py-2">
        {[
          { label: 'Dashboard', icon: <Monitor className="h-5 w-5" />, active: true },
          { label: 'Projects', icon: <Tablet className="h-5 w-5" />, active: false },
          { label: 'Tasks', icon: <Smartphone className="h-5 w-5" />, active: false },
          { label: 'Users', icon: <TouchIcon className="h-5 w-5" />, active: false }
        ].map((item, index) => (
          <motion.div
            key={index}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.1 }}
            className="flex flex-col items-center p-2"
          >
            <div className={cn(
              'p-2 rounded-lg transition-colors',
              item.active ? 'bg-blue-100 text-blue-600' : 'text-gray-600'
            )}>
              {item.icon}
            </div>
            <span className={cn(
              'text-xs mt-1',
              item.active ? 'text-blue-600 font-medium' : 'text-gray-500'
            )}>
              {item.label}
            </span>
          </motion.div>
        ))}
      </div>
    </motion.footer>
  )

  // Gesture hints
  const GestureHints = () => (
    <AnimatePresence>
      {isMobile && enableGestures && (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: 20 }}
          className="fixed bottom-20 left-4 right-4 z-30"
        >
          <Card className="bg-blue-50 border-blue-200">
            <CardContent className="p-3">
              <div className="flex items-center gap-2 text-blue-700 text-sm">
                <TouchIcon className="h-4 w-4" />
                <span>Swipe left/right to navigate • Pinch to zoom • Long press for options</span>
              </div>
            </CardContent>
          </Card>
        </motion.div>
      )}
    </AnimatePresence>
  )

  // PWA install prompt
  const PWAInstallPrompt = () => {
    const [showInstallPrompt, setShowInstallPrompt] = useState(false)

    useEffect(() => {
      if (enablePWA) {
        const appState = pwaService.getAppState()
        if (appState.canInstall && !appState.isInstalled) {
          setShowInstallPrompt(true)
        }
      }
    }, [enablePWA])

    const handleInstall = async () => {
      await pwaService.installApp()
      setShowInstallPrompt(false)
    }

    return (
      <AnimatePresence>
        {showInstallPrompt && (
          <motion.div
            initial={{ opacity: 0, y: 100 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: 100 }}
            className="fixed bottom-4 left-4 right-4 z-50"
          >
            <Card className="bg-gradient-to-r from-blue-500 to-purple-600 text-white border-0">
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="font-semibold">Install ZenaManage</h3>
                    <p className="text-sm opacity-90">Get the full app experience</p>
                  </div>
                  <div className="flex gap-2">
                    <Button
                      onClick={() => setShowInstallPrompt(false)}
                      variant="ghost"
                      size="sm"
                      className="text-white hover:bg-white/20"
                    >
                      <X className="h-4 w-4" />
                    </Button>
                    <Button
                      onClick={handleInstall}
                      size="sm"
                      className="bg-white text-blue-600 hover:bg-gray-100"
                    >
                      Install
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          </motion.div>
        )}
      </AnimatePresence>
    )
  }

  return (
    <div
      ref={gestureRef}
      className={cn(
        'min-h-screen bg-gray-50 dark:bg-gray-900',
        isMobile && 'pb-20', // Space for mobile footer
        isMobile && showMobileHeader && 'pt-16', // Space for mobile header
        className
      )}
    >
      {/* Device Info (Development only) */}
      {process.env.NODE_ENV === 'development' && <DeviceInfo />}
      
      {/* Mobile Header */}
      {isMobile && showMobileHeader && <MobileHeader />}
      
      {/* Main Content */}
      <main className="container mx-auto px-4 py-6">
        {children}
      </main>
      
      {/* Mobile Footer */}
      {isMobile && showMobileFooter && <MobileFooter />}
      
      {/* Gesture Hints */}
      <GestureHints />
      
      {/* PWA Install Prompt */}
      <PWAInstallPrompt />
    </div>
  )
}

export default ResponsiveLayout
