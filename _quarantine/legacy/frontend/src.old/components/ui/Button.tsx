import React from 'react'
import { motion } from 'framer-motion'
import { cn } from '../../lib/utils'
import { hoverScale, touchFeedback, focusAnimation } from '@/utils/animations'

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link'
  size?: 'default' | 'sm' | 'lg' | 'icon'
  children: React.ReactNode
}

export const Button: React.FC<ButtonProps> = ({
  variant = 'default',
  size = 'default',
  className,
  children,
  ...props
}) => {
  const baseClasses = 'inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50'
  
  const variantClasses = {
    default: 'bg-primary text-primary-foreground hover:bg-primary/90',
    destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
    outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
    secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
    ghost: 'hover:bg-accent hover:text-accent-foreground',
    link: 'text-primary underline-offset-4 hover:underline'
  }
  
  const sizeClasses = {
    default: 'h-10 px-4 py-2',
    sm: 'h-9 rounded-md px-3',
    lg: 'h-11 rounded-md px-8',
    icon: 'h-10 w-10'
  }

  return (
    <motion.button
      {...(props as any)}
      className={cn(
        baseClasses,
        variantClasses[variant],
        sizeClasses[size],
        className
      )}
      whileHover={{
        ...hoverScale.whileHover,
        transition: {
          ...hoverScale.whileHover.transition,
          ease: [0.42, 0, 0.58, 1], // cubic-bezier for 'easeInOut'
        },
      }}
      whileTap={{
        ...touchFeedback.whileTap,
        transition: {
          ...touchFeedback.whileTap.transition,
          ease: [0.42, 0, 0.58, 1],
        },
      }}
      whileFocus={{
        ...focusAnimation.whileFocus,
        transition: {
          ...focusAnimation.whileFocus.transition,
          ease: [0.42, 0, 0.58, 1],
        },
      }}
      transition={{ duration: 0.2, ease: [0.42, 0, 0.58, 1] }}
      {...props}
    >
      {children}
    </motion.button>
  )
}