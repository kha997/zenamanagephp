import React, { useState, useRef, useEffect } from 'react'
import { createPortal } from 'react-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { cn } from '../lib/utils'

interface TooltipProps {
  children: React.ReactNode
  content: React.ReactNode
  placement?: 'top' | 'bottom' | 'left' | 'right'
  delay?: number
  disabled?: boolean
  className?: string
  tooltipClassName?: string
  trigger?: 'hover' | 'click' | 'focus'
  size?: 'sm' | 'md' | 'lg'
  variant?: 'default' | 'dark' | 'light' | 'success' | 'warning' | 'error'
}

const Tooltip: React.FC<TooltipProps> = ({
  children,
  content,
  placement = 'top',
  delay = 300,
  disabled = false,
  className,
  tooltipClassName,
  trigger = 'hover',
  size = 'md',
  variant = 'default',
}) => {
  const [isVisible, setIsVisible] = useState(false)
  const [position, setPosition] = useState({ x: 0, y: 0 })
  const triggerRef = useRef<HTMLDivElement>(null)
  const tooltipRef = useRef<HTMLDivElement>(null)
  const timeoutRef = useRef<NodeJS.Timeout>()

  const sizeClasses = {
    sm: 'text-xs px-2 py-1',
    md: 'text-sm px-3 py-2',
    lg: 'text-base px-4 py-3',
  }

  const variantClasses = {
    default: 'bg-gray-900 text-white',
    dark: 'bg-gray-800 text-white',
    light: 'bg-white text-gray-900 border border-gray-200',
    success: 'bg-green-600 text-white',
    warning: 'bg-yellow-500 text-white',
    error: 'bg-red-600 text-white',
  }

  const placementClasses = {
    top: 'bottom-full left-1/2 transform -translate-x-1/2 mb-2',
    bottom: 'top-full left-1/2 transform -translate-x-1/2 mt-2',
    left: 'right-full top-1/2 transform -translate-y-1/2 mr-2',
    right: 'left-full top-1/2 transform -translate-y-1/2 ml-2',
  }

  const arrowClasses = {
    top: 'top-full left-1/2 transform -translate-x-1/2 border-l-transparent border-r-transparent border-b-transparent border-t-gray-900',
    bottom: 'bottom-full left-1/2 transform -translate-x-1/2 border-l-transparent border-r-transparent border-t-transparent border-b-gray-900',
    left: 'left-full top-1/2 transform -translate-y-1/2 border-t-transparent border-b-transparent border-r-transparent border-l-gray-900',
    right: 'right-full top-1/2 transform -translate-y-1/2 border-t-transparent border-b-transparent border-l-transparent border-r-gray-900',
  }

  const calculatePosition = () => {
    if (!triggerRef.current || !tooltipRef.current) return

    const triggerRect = triggerRef.current.getBoundingClientRect()
    const tooltipRect = tooltipRef.current.getBoundingClientRect()
    const scrollX = window.pageXOffset || document.documentElement.scrollLeft
    const scrollY = window.pageYOffset || document.documentElement.scrollTop

    let x = 0
    let y = 0

    switch (placement) {
      case 'top':
        x = triggerRect.left + triggerRect.width / 2 - tooltipRect.width / 2 + scrollX
        y = triggerRect.top - tooltipRect.height - 8 + scrollY
        break
      case 'bottom':
        x = triggerRect.left + triggerRect.width / 2 - tooltipRect.width / 2 + scrollX
        y = triggerRect.bottom + 8 + scrollY
        break
      case 'left':
        x = triggerRect.left - tooltipRect.width - 8 + scrollX
        y = triggerRect.top + triggerRect.height / 2 - tooltipRect.height / 2 + scrollY
        break
      case 'right':
        x = triggerRect.right + 8 + scrollX
        y = triggerRect.top + triggerRect.height / 2 - tooltipRect.height / 2 + scrollY
        break
    }

    setPosition({ x, y })
  }

  const showTooltip = () => {
    if (disabled) return
    
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current)
    }
    
    timeoutRef.current = setTimeout(() => {
      setIsVisible(true)
      calculatePosition()
    }, delay)
  }

  const hideTooltip = () => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current)
    }
    setIsVisible(false)
  }

  const handleClick = () => {
    if (trigger === 'click') {
      setIsVisible(!isVisible)
      calculatePosition()
    }
  }

  const handleFocus = () => {
    if (trigger === 'focus') {
      showTooltip()
    }
  }

  const handleBlur = () => {
    if (trigger === 'focus') {
      hideTooltip()
    }
  }

  useEffect(() => {
    if (isVisible) {
      calculatePosition()
      const handleResize = () => calculatePosition()
      const handleScroll = () => calculatePosition()
      
      window.addEventListener('resize', handleResize)
      window.addEventListener('scroll', handleScroll)
      
      return () => {
        window.removeEventListener('resize', handleResize)
        window.removeEventListener('scroll', handleScroll)
      }
    }
  }, [isVisible])

  useEffect(() => {
    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current)
      }
    }
  }, [])

  const tooltipElement = (
    <AnimatePresence>
      {isVisible && (
        <motion.div
          ref={tooltipRef}
          initial={{ opacity: 0, scale: 0.8 }}
          animate={{ opacity: 1, scale: 1 }}
          exit={{ opacity: 0, scale: 0.8 }}
          transition={{ duration: 0.2, ease: 'easeOut' }}
          className={cn(
            'fixed z-50 rounded-md shadow-lg pointer-events-none',
            sizeClasses[size],
            variantClasses[variant],
            tooltipClassName
          )}
          style={{
            left: position.x,
            top: position.y,
          }}
        >
          {content}
          <div
            className={cn(
              'absolute w-0 h-0 border-4',
              arrowClasses[placement]
            )}
          />
        </motion.div>
      )}
    </AnimatePresence>
  )

  return (
    <>
      <div
        ref={triggerRef}
        className={cn('inline-block', className)}
        onMouseEnter={trigger === 'hover' ? showTooltip : undefined}
        onMouseLeave={trigger === 'hover' ? hideTooltip : undefined}
        onClick={handleClick}
        onFocus={handleFocus}
        onBlur={handleBlur}
      >
        {children}
      </div>
      {createPortal(tooltipElement, document.body)}
    </>
  )
}

// Help Text Component
interface HelpTextProps {
  children: React.ReactNode
  text: string
  placement?: 'top' | 'bottom' | 'left' | 'right'
  className?: string
}

export const HelpText: React.FC<HelpTextProps> = ({
  children,
  text,
  placement = 'top',
  className,
}) => {
  return (
    <Tooltip
      content={text}
      placement={placement}
      trigger="hover"
      size="sm"
      variant="light"
      className={className}
    >
      {children}
    </Tooltip>
  )
}

// Info Icon Component
interface InfoIconProps {
  text: string
  placement?: 'top' | 'bottom' | 'left' | 'right'
  className?: string
}

export const InfoIcon: React.FC<InfoIconProps> = ({
  text,
  placement = 'top',
  className,
}) => {
  return (
    <Tooltip
      content={text}
      placement={placement}
      trigger="hover"
      size="sm"
      variant="light"
      className={className}
    >
      <div className="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-200 text-gray-600 hover:bg-gray-300 cursor-help">
        <svg
          className="w-3 h-3"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fillRule="evenodd"
            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
            clipRule="evenodd"
          />
        </svg>
      </div>
    </Tooltip>
  )
}

// Question Mark Icon Component
interface QuestionMarkProps {
  text: string
  placement?: 'top' | 'bottom' | 'left' | 'right'
  className?: string
}

export const QuestionMark: React.FC<QuestionMarkProps> = ({
  text,
  placement = 'top',
  className,
}) => {
  return (
    <Tooltip
      content={text}
      placement={placement}
      trigger="hover"
      size="sm"
      variant="light"
      className={className}
    >
      <div className="inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-100 text-blue-600 hover:bg-blue-200 cursor-help">
        <svg
          className="w-3 h-3"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fillRule="evenodd"
            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z"
            clipRule="evenodd"
          />
        </svg>
      </div>
    </Tooltip>
  )
}

export default Tooltip
