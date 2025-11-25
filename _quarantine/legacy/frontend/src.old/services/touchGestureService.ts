import { useState, useEffect, useRef, useCallback } from 'react'

interface TouchPoint {
  x: number
  y: number
  timestamp: number
}

interface SwipeGesture {
  direction: 'left' | 'right' | 'up' | 'down'
  distance: number
  velocity: number
  duration: number
}

interface PinchGesture {
  scale: number
  center: { x: number; y: number }
  distance: number
}

interface TouchGestureCallbacks {
  onSwipe?: (gesture: SwipeGesture) => void
  onPinch?: (gesture: PinchGesture) => void
  onTap?: (point: TouchPoint) => void
  onDoubleTap?: (point: TouchPoint) => void
  onLongPress?: (point: TouchPoint) => void
  onTouchStart?: (point: TouchPoint) => void
  onTouchMove?: (point: TouchPoint) => void
  onTouchEnd?: (point: TouchPoint) => void
}

interface TouchGestureOptions {
  swipeThreshold?: number
  pinchThreshold?: number
  longPressDelay?: number
  doubleTapDelay?: number
  preventDefault?: boolean
  passive?: boolean
}

class TouchGestureService {
  private element: HTMLElement | null = null
  private callbacks: TouchGestureCallbacks = {}
  private options: TouchGestureOptions = {}
  
  private touchStartTime = 0
  private touchStartPoint: TouchPoint | null = null
  private lastTapTime = 0
  private lastTapPoint: TouchPoint | null = null
  private longPressTimer: NodeJS.Timeout | null = null
  private isLongPress = false
  private isDragging = false
  private initialPinchDistance = 0
  private initialPinchCenter: { x: number; y: number } | null = null

  constructor(element: HTMLElement | null, callbacks: TouchGestureCallbacks, options: TouchGestureOptions = {}) {
    this.element = element
    this.callbacks = callbacks
    this.options = {
      swipeThreshold: 50,
      pinchThreshold: 0.1,
      longPressDelay: 500,
      doubleTapDelay: 300,
      preventDefault: true,
      passive: false,
      ...options
    }
    
    this.init()
  }

  private init() {
    if (!this.element) return

    this.element.addEventListener('touchstart', this.handleTouchStart, { passive: this.options.passive })
    this.element.addEventListener('touchmove', this.handleTouchMove, { passive: this.options.passive })
    this.element.addEventListener('touchend', this.handleTouchEnd, { passive: this.options.passive })
    this.element.addEventListener('touchcancel', this.handleTouchEnd, { passive: this.options.passive })
  }

  private handleTouchStart = (event: TouchEvent) => {
    if (this.options.preventDefault) {
      event.preventDefault()
    }

    const touch = event.touches[0]
    const point: TouchPoint = {
      x: touch.clientX,
      y: touch.clientY,
      timestamp: Date.now()
    }

    this.touchStartTime = point.timestamp
    this.touchStartPoint = point
    this.isDragging = false
    this.isLongPress = false

    // Handle multi-touch for pinch
    if (event.touches.length === 2) {
      this.handlePinchStart(event)
    }

    // Start long press timer
    this.longPressTimer = setTimeout(() => {
      if (this.touchStartPoint && !this.isDragging) {
        this.isLongPress = true
        this.callbacks.onLongPress?.(this.touchStartPoint)
      }
    }, this.options.longPressDelay!)

    this.callbacks.onTouchStart?.(point)
  }

  private handleTouchMove = (event: TouchEvent) => {
    if (this.options.preventDefault) {
      event.preventDefault()
    }

    const touch = event.touches[0]
    const point: TouchPoint = {
      x: touch.clientX,
      y: touch.clientY,
      timestamp: Date.now()
    }

    // Check if dragging
    if (this.touchStartPoint) {
      const distance = Math.sqrt(
        Math.pow(point.x - this.touchStartPoint.x, 2) + 
        Math.pow(point.y - this.touchStartPoint.y, 2)
      )
      
      if (distance > 10) {
        this.isDragging = true
        this.clearLongPressTimer()
      }
    }

    // Handle multi-touch for pinch
    if (event.touches.length === 2) {
      this.handlePinchMove(event)
    }

    this.callbacks.onTouchMove?.(point)
  }

  private handleTouchEnd = (event: TouchEvent) => {
    if (this.options.preventDefault) {
      event.preventDefault()
    }

    const touch = event.changedTouches[0]
    const point: TouchPoint = {
      x: touch.clientX,
      y: touch.clientY,
      timestamp: Date.now()
    }

    this.clearLongPressTimer()

    // Handle swipe gesture
    if (this.touchStartPoint && this.isDragging) {
      this.handleSwipe(this.touchStartPoint, point)
    } else if (this.touchStartPoint && !this.isLongPress) {
      // Handle tap gestures
      this.handleTap(this.touchStartPoint, point)
    }

    this.callbacks.onTouchEnd?.(point)
  }

  private handlePinchStart(event: TouchEvent) {
    const touch1 = event.touches[0]
    const touch2 = event.touches[1]
    
    this.initialPinchDistance = Math.sqrt(
      Math.pow(touch2.clientX - touch1.clientX, 2) + 
      Math.pow(touch2.clientY - touch1.clientY, 2)
    )
    
    this.initialPinchCenter = {
      x: (touch1.clientX + touch2.clientX) / 2,
      y: (touch1.clientY + touch2.clientY) / 2
    }
  }

  private handlePinchMove(event: TouchEvent) {
    if (event.touches.length !== 2) return

    const touch1 = event.touches[0]
    const touch2 = event.touches[1]
    
    const currentDistance = Math.sqrt(
      Math.pow(touch2.clientX - touch1.clientX, 2) + 
      Math.pow(touch2.clientY - touch1.clientY, 2)
    )
    
    const scale = currentDistance / this.initialPinchDistance
    
    if (Math.abs(scale - 1) > this.options.pinchThreshold!) {
      const gesture: PinchGesture = {
        scale,
        center: this.initialPinchCenter!,
        distance: currentDistance
      }
      
      this.callbacks.onPinch?.(gesture)
    }
  }

  private handleSwipe(startPoint: TouchPoint, endPoint: TouchPoint) {
    const deltaX = endPoint.x - startPoint.x
    const deltaY = endPoint.y - startPoint.y
    const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY)
    const duration = endPoint.timestamp - startPoint.timestamp
    const velocity = distance / duration

    if (distance < this.options.swipeThreshold!) return

    let direction: 'left' | 'right' | 'up' | 'down'
    
    if (Math.abs(deltaX) > Math.abs(deltaY)) {
      direction = deltaX > 0 ? 'right' : 'left'
    } else {
      direction = deltaY > 0 ? 'down' : 'up'
    }

    const gesture: SwipeGesture = {
      direction,
      distance,
      velocity,
      duration
    }

    this.callbacks.onSwipe?.(gesture)
  }

  private handleTap(startPoint: TouchPoint, endPoint: TouchPoint) {
    const distance = Math.sqrt(
      Math.pow(endPoint.x - startPoint.x, 2) + 
      Math.pow(endPoint.y - startPoint.y, 2)
    )

    if (distance > 10) return // Not a tap

    const currentTime = Date.now()
    const timeSinceLastTap = currentTime - this.lastTapTime

    if (this.lastTapPoint && timeSinceLastTap < this.options.doubleTapDelay!) {
      // Double tap
      this.callbacks.onDoubleTap?.(endPoint)
      this.lastTapTime = 0
      this.lastTapPoint = null
    } else {
      // Single tap
      this.callbacks.onTap?.(endPoint)
      this.lastTapTime = currentTime
      this.lastTapPoint = endPoint
    }
  }

  private clearLongPressTimer() {
    if (this.longPressTimer) {
      clearTimeout(this.longPressTimer)
      this.longPressTimer = null
    }
  }

  public destroy() {
    if (!this.element) return

    this.element.removeEventListener('touchstart', this.handleTouchStart)
    this.element.removeEventListener('touchmove', this.handleTouchMove)
    this.element.removeEventListener('touchend', this.handleTouchEnd)
    this.element.removeEventListener('touchcancel', this.handleTouchEnd)
    
    this.clearLongPressTimer()
  }

  public updateCallbacks(callbacks: TouchGestureCallbacks) {
    this.callbacks = { ...this.callbacks, ...callbacks }
  }

  public updateOptions(options: TouchGestureOptions) {
    this.options = { ...this.options, ...options }
  }
}

// React Hook for Touch Gestures
export const useTouchGestures = (
  callbacks: TouchGestureCallbacks,
  options?: TouchGestureOptions
) => {
  const elementRef = useRef<HTMLElement>(null)
  const gestureServiceRef = useRef<TouchGestureService | null>(null)

  useEffect(() => {
    if (elementRef.current) {
      gestureServiceRef.current = new TouchGestureService(
        elementRef.current,
        callbacks,
        options
      )
    }

    return () => {
      gestureServiceRef.current?.destroy()
    }
  }, [])

  useEffect(() => {
    gestureServiceRef.current?.updateCallbacks(callbacks)
  }, [callbacks])

  useEffect(() => {
    gestureServiceRef.current?.updateOptions(options || {})
  }, [options])

  return elementRef
}

// Utility functions
export const getSwipeDirection = (gesture: SwipeGesture): string => {
  return gesture.direction
}

export const getSwipeDistance = (gesture: SwipeGesture): number => {
  return gesture.distance
}

export const getSwipeVelocity = (gesture: SwipeGesture): number => {
  return gesture.velocity
}

export const isSwipeFast = (gesture: SwipeGesture, threshold: number = 0.5): boolean => {
  return gesture.velocity > threshold
}

export const getPinchScale = (gesture: PinchGesture): number => {
  return gesture.scale
}

export const isPinchIn = (gesture: PinchGesture): boolean => {
  return gesture.scale < 1
}

export const isPinchOut = (gesture: PinchGesture): boolean => {
  return gesture.scale > 1
}

// Gesture constants
export const SWIPE_DIRECTIONS = {
  LEFT: 'left' as const,
  RIGHT: 'right' as const,
  UP: 'up' as const,
  DOWN: 'down' as const
} as const

export const GESTURE_THRESHOLDS = {
  SWIPE: 50,
  PINCH: 0.1,
  LONG_PRESS: 500,
  DOUBLE_TAP: 300
} as const

export default TouchGestureService
