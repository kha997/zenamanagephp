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
  TouchIcon
} from 'lucide-react'
import { cn } from '../lib/utils'
import { slideUpMobile, slideDownMobile, touchFeedback, swipeAnimation } from '../utils/animations'

// Mobile Navigation Drawer
interface MobileDrawerProps {
  isOpen: boolean
  onClose: () => void
  children: React.ReactNode
  title?: string
}

export const MobileDrawer: React.FC<MobileDrawerProps> = ({
  isOpen,
  onClose,
  children,
  title = 'Menu'
}) => {
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden'
    } else {
      document.body.style.overflow = 'unset'
    }
    
    return () => {
      document.body.style.overflow = 'unset'
    }
  }, [isOpen])

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
            className="fixed inset-0 bg-black bg-opacity-50 z-40"
            onClick={onClose}
          />
          
          {/* Drawer */}
          <motion.div
            variants={slideUpMobile}
            initial="initial"
            animate="animate"
            exit="exit"
            className="fixed bottom-0 left-0 right-0 bg-white rounded-t-xl shadow-2xl z-50 max-h-[80vh] overflow-hidden"
          >
            {/* Handle */}
            <div className="flex justify-center py-2">
              <div className="w-12 h-1 bg-gray-300 rounded-full" />
            </div>
            
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-3 border-b">
              <h2 className="text-lg font-semibold text-gray-900">{title}</h2>
              <Button
                variant="ghost"
                size="sm"
                onClick={onClose}
                className="p-2"
              >
                <X className="h-5 w-5" />
              </Button>
            </div>
            
            {/* Content */}
            <div className="overflow-y-auto max-h-[calc(80vh-80px)]">
              {children}
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  )
}

// Mobile Search Bar
interface MobileSearchProps {
  placeholder?: string
  onSearch: (query: string) => void
  className?: string
}

export const MobileSearch: React.FC<MobileSearchProps> = ({
  placeholder = 'Search...',
  onSearch,
  className
}) => {
  const [isExpanded, setIsExpanded] = useState(false)
  const [query, setQuery] = useState('')

  const handleSearch = () => {
    onSearch(query)
    setIsExpanded(false)
  }

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      handleSearch()
    }
  }

  return (
    <div className={cn('relative', className)}>
      <AnimatePresence>
        {isExpanded ? (
          <motion.div
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.9 }}
            transition={{ duration: 0.2 }}
            className="flex items-center gap-2"
          >
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                type="text"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                onKeyPress={handleKeyPress}
                placeholder={placeholder}
                className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                autoFocus
              />
            </div>
            <Button
              onClick={handleSearch}
              size="sm"
              className="px-4"
            >
              Search
            </Button>
            <Button
              onClick={() => setIsExpanded(false)}
              variant="ghost"
              size="sm"
              className="p-2"
            >
              <X className="h-4 w-4" />
            </Button>
          </motion.div>
        ) : (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
          >
            <Button
              onClick={() => setIsExpanded(true)}
              variant="outline"
              className="w-full justify-start text-gray-500"
            >
              <Search className="h-4 w-4 mr-2" />
              {placeholder}
            </Button>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}

// Mobile Filter Sheet
interface MobileFilterProps {
  isOpen: boolean
  onClose: () => void
  children: React.ReactNode
  onApply: () => void
  onReset: () => void
}

export const MobileFilter: React.FC<MobileFilterProps> = ({
  isOpen,
  onClose,
  children,
  onApply,
  onReset
}) => {
  return (
    <MobileDrawer
      isOpen={isOpen}
      onClose={onClose}
      title="Filters"
    >
      <div className="p-4 space-y-4">
        {children}
        
        <div className="flex gap-3 pt-4 border-t">
          <Button
            onClick={onReset}
            variant="outline"
            className="flex-1"
          >
            Reset
          </Button>
          <Button
            onClick={() => {
              onApply()
              onClose()
            }}
            className="flex-1"
          >
            Apply Filters
          </Button>
        </div>
      </div>
    </MobileDrawer>
  )
}

// Mobile Action Sheet
interface MobileActionSheetProps {
  isOpen: boolean
  onClose: () => void
  actions: Array<{
    label: string
    icon?: React.ReactNode
    onClick: () => void
    variant?: 'default' | 'destructive'
  }>
  title?: string
}

export const MobileActionSheet: React.FC<MobileActionSheetProps> = ({
  isOpen,
  onClose,
  actions,
  title = 'Actions'
}) => {
  return (
    <MobileDrawer
      isOpen={isOpen}
      onClose={onClose}
      title={title}
    >
      <div className="p-4 space-y-2">
        {actions.map((action, index) => (
          <motion.div
            key={index}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.05 }}
          >
            <Button
              onClick={() => {
                action.onClick()
                onClose()
              }}
              variant={action.variant === 'destructive' ? 'destructive' : 'ghost'}
              className="w-full justify-start h-12"
            >
              {action.icon && <span className="mr-3">{action.icon}</span>}
              {action.label}
            </Button>
          </motion.div>
        ))}
      </div>
    </MobileDrawer>
  )
}

// Mobile Card with Swipe Actions
interface MobileCardProps {
  children: React.ReactNode
  onSwipeLeft?: () => void
  onSwipeRight?: () => void
  leftAction?: React.ReactNode
  rightAction?: React.ReactNode
  className?: string
}

export const MobileCard: React.FC<MobileCardProps> = ({
  children,
  onSwipeLeft,
  onSwipeRight,
  leftAction,
  rightAction,
  className
}) => {
  const [dragX, setDragX] = useState(0)
  const [isDragging, setIsDragging] = useState(false)

  const handleDragEnd = () => {
    if (dragX > 50 && onSwipeRight) {
      onSwipeRight()
    } else if (dragX < -50 && onSwipeLeft) {
      onSwipeLeft()
    }
    setDragX(0)
    setIsDragging(false)
  }

  return (
    <div className={cn('relative overflow-hidden', className)}>
      {/* Actions */}
      {(leftAction || rightAction) && (
        <div className="absolute inset-0 flex items-center justify-between px-4 pointer-events-none">
          <div className="flex items-center">
            {leftAction}
          </div>
          <div className="flex items-center">
            {rightAction}
          </div>
        </div>
      )}
      
      {/* Card */}
      <motion.div
        drag="x"
        dragConstraints={{ left: 0, right: 0 }}
        dragElastic={0.2}
        onDrag={(_, info) => {
          setDragX(info.offset.x)
          setIsDragging(true)
        }}
        onDragEnd={handleDragEnd}
        animate={{ x: dragX }}
        transition={{ type: 'spring', stiffness: 300, damping: 30 }}
        className="relative z-10"
      >
        <Card className={cn(
          'transition-all duration-200',
          isDragging && 'shadow-lg'
        )}>
          {children}
        </Card>
      </motion.div>
    </div>
  )
}

// Mobile Pull to Refresh
interface MobilePullToRefreshProps {
  onRefresh: () => Promise<void>
  children: React.ReactNode
  threshold?: number
}

export const MobilePullToRefresh: React.FC<MobilePullToRefreshProps> = ({
  onRefresh,
  children,
  threshold = 80
}) => {
  const [isRefreshing, setIsRefreshing] = useState(false)
  const [pullDistance, setPullDistance] = useState(0)
  const [startY, setStartY] = useState(0)

  const handleTouchStart = (e: React.TouchEvent) => {
    setStartY(e.touches[0].clientY)
  }

  const handleTouchMove = (e: React.TouchEvent) => {
    if (window.scrollY === 0) {
      const currentY = e.touches[0].clientY
      const distance = Math.max(0, currentY - startY)
      setPullDistance(distance)
    }
  }

  const handleTouchEnd = async () => {
    if (pullDistance > threshold && !isRefreshing) {
      setIsRefreshing(true)
      await onRefresh()
      setIsRefreshing(false)
    }
    setPullDistance(0)
  }

  return (
    <div className="relative">
      {/* Pull indicator */}
      <AnimatePresence>
        {pullDistance > 0 && (
          <motion.div
            initial={{ opacity: 0, scale: 0.8 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.8 }}
            className="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-10"
            style={{ marginTop: `${Math.min(pullDistance, threshold)}px` }}
          >
            <div className="flex items-center justify-center w-12 h-12 bg-blue-500 text-white rounded-full shadow-lg">
              {pullDistance > threshold ? (
                <motion.div
                  animate={spinAnimation.animate}
                >
                  <SwipeUp className="h-6 w-6" />
                </motion.div>
              ) : (
                <SwipeDown className="h-6 w-6" />
              )}
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Content */}
      <div
        onTouchStart={handleTouchStart}
        onTouchMove={handleTouchMove}
        onTouchEnd={handleTouchEnd}
        style={{ transform: `translateY(${Math.min(pullDistance * 0.5, threshold * 0.5)}px)` }}
      >
        {children}
      </div>
    </div>
  )
}

// Mobile Bottom Navigation
interface MobileBottomNavProps {
  items: Array<{
    label: string
    icon: React.ReactNode
    href: string
    isActive?: boolean
  }>
  className?: string
}

export const MobileBottomNav: React.FC<MobileBottomNavProps> = ({
  items,
  className
}) => {
  return (
    <motion.div
      initial={{ y: 100 }}
      animate={{ y: 0 }}
      transition={{ duration: 0.3, ease: 'easeOut' }}
      className={cn(
        'fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50',
        className
      )}
    >
      <div className="flex items-center justify-around py-2">
        {items.map((item, index) => (
          <motion.div
            key={index}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.1 }}
            className="flex flex-col items-center p-2"
          >
            <div className={cn(
              'p-2 rounded-lg transition-colors',
              item.isActive ? 'bg-blue-100 text-blue-600' : 'text-gray-600'
            )}>
              {item.icon}
            </div>
            <span className={cn(
              'text-xs mt-1',
              item.isActive ? 'text-blue-600 font-medium' : 'text-gray-500'
            )}>
              {item.label}
            </span>
          </motion.div>
        ))}
      </div>
    </motion.div>
  )
}

// Mobile Gesture Hint
interface MobileGestureHintProps {
  gesture: 'swipe' | 'pull' | 'tap' | 'longPress'
  direction?: 'left' | 'right' | 'up' | 'down'
  text: string
  className?: string
}

export const MobileGestureHint: React.FC<MobileGestureHintProps> = ({
  gesture,
  direction = 'right',
  text,
  className
}) => {
  const getGestureIcon = () => {
    switch (gesture) {
      case 'swipe':
        return direction === 'left' ? <SwipeDown className="h-4 w-4" /> : <SwipeUp className="h-4 w-4" />
      case 'pull':
        return <SwipeUp className="h-4 w-4" />
      case 'tap':
        return <TouchIcon className="h-4 w-4" />
      case 'longPress':
        return <TouchIcon className="h-4 w-4" />
      default:
        return <TouchIcon className="h-4 w-4" />
    }
  }

  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.9 }}
      animate={{ opacity: 1, scale: 1 }}
      exit={{ opacity: 0, scale: 0.9 }}
      className={cn(
        'flex items-center gap-2 px-3 py-2 bg-blue-50 text-blue-700 rounded-lg text-sm',
        className
      )}
    >
      <motion.div
        animate={bounceAnimation.animate}
      >
        {getGestureIcon()}
      </motion.div>
      <span>{text}</span>
    </motion.div>
  )
}

export default {
  MobileDrawer,
  MobileSearch,
  MobileFilter,
  MobileActionSheet,
  MobileCard,
  MobilePullToRefresh,
  MobileBottomNav,
  MobileGestureHint,
}
