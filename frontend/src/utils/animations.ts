import { Variants } from 'framer-motion'

// Animation variants for Framer Motion
export const fadeInUp: Variants = {
  initial: {
    opacity: 0,
    y: 20,
  },
  animate: {
    opacity: 1,
    y: 0,
    transition: {
      duration: 0.5,
      ease: 'easeOut',
    },
  },
  exit: {
    opacity: 0,
    y: -20,
    transition: {
      duration: 0.3,
      ease: 'easeIn',
    },
  },
}

export const fadeInLeft: Variants = {
  initial: {
    opacity: 0,
    x: -20,
  },
  animate: {
    opacity: 1,
    x: 0,
    transition: {
      duration: 0.5,
      ease: 'easeOut',
    },
  },
  exit: {
    opacity: 0,
    x: 20,
    transition: {
      duration: 0.3,
      ease: 'easeIn',
    },
  },
}

export const fadeInRight: Variants = {
  initial: {
    opacity: 0,
    x: 20,
  },
  animate: {
    opacity: 1,
    x: 0,
    transition: {
      duration: 0.5,
      ease: 'easeOut',
    },
  },
  exit: {
    opacity: 0,
    x: -20,
    transition: {
      duration: 0.3,
      ease: 'easeIn',
    },
  },
}

export const scaleIn: Variants = {
  initial: {
    opacity: 0,
    scale: 0.9,
  },
  animate: {
    opacity: 1,
    scale: 1,
    transition: {
      duration: 0.3,
      ease: 'easeOut',
    },
  },
  exit: {
    opacity: 0,
    scale: 0.9,
    transition: {
      duration: 0.2,
      ease: 'easeIn',
    },
  },
}

export const slideInFromTop: Variants = {
  initial: {
    opacity: 0,
    y: -50,
  },
  animate: {
    opacity: 1,
    y: 0,
    transition: {
      duration: 0.4,
      ease: 'easeOut',
    },
  },
  exit: {
    opacity: 0,
    y: -50,
    transition: {
      duration: 0.3,
      ease: 'easeIn',
    },
  },
}

export const slideInFromBottom: Variants = {
  initial: {
    opacity: 0,
    y: 50,
  },
  animate: {
    opacity: 1,
    y: 0,
    transition: {
      duration: 0.4,
      ease: 'easeOut',
    },
  },
  exit: {
    opacity: 0,
    y: 50,
    transition: {
      duration: 0.3,
      ease: 'easeIn',
    },
  },
}

export const staggerContainer: Variants = {
  initial: {},
  animate: {
    transition: {
      staggerChildren: 0.1,
      delayChildren: 0.1,
    },
  },
}

export const staggerItem: Variants = {
  initial: {
    opacity: 0,
    y: 20,
  },
  animate: {
    opacity: 1,
    y: 0,
    transition: {
      duration: 0.5,
      ease: 'easeOut',
    },
  },
}

// Hover animations
export const hoverScale = {
  whileHover: {
    scale: 1.05,
    transition: {
      duration: 0.2,
      ease: 'easeInOut',
    },
  },
  whileTap: {
    scale: 0.95,
    transition: {
      duration: 0.1,
      ease: 'easeInOut',
    },
  },
}

export const hoverLift = {
  whileHover: {
    y: -5,
    boxShadow: '0 10px 25px rgba(0, 0, 0, 0.1)',
    transition: {
      duration: 0.2,
      ease: 'easeInOut',
    },
  },
}

export const hoverGlow = {
  whileHover: {
    boxShadow: '0 0 20px rgba(59, 130, 246, 0.3)',
    transition: {
      duration: 0.3,
      ease: 'easeInOut',
    },
  },
}

// Loading animations
export const pulseAnimation = {
  animate: {
    scale: [1, 1.1, 1],
    opacity: [0.7, 1, 0.7],
    transition: {
      duration: 1.5,
      repeat: Infinity,
      ease: 'easeInOut',
    },
  },
}

export const spinAnimation = {
  animate: {
    rotate: 360,
    transition: {
      duration: 1,
      repeat: Infinity,
      ease: 'linear',
    },
  },
}

export const bounceAnimation = {
  animate: {
    y: [0, -10, 0],
    transition: {
      duration: 0.6,
      repeat: Infinity,
      ease: 'easeInOut',
    },
  },
}

// Page transition animations
export const pageTransition = {
  initial: {
    opacity: 0,
    x: 20,
  },
  animate: {
    opacity: 1,
    x: 0,
    transition: {
      duration: 0.3,
      ease: 'easeOut',
    },
  },
  exit: {
    opacity: 0,
    x: -20,
    transition: {
      duration: 0.2,
      ease: 'easeIn',
    },
  },
}

// Modal animations
export const modalAnimation = {
  initial: {
    opacity: 0,
    scale: 0.9,
  },
  animate: {
    opacity: 1,
    scale: 1,
    transition: {
      duration: 0.2,
      ease: 'easeOut',
    },
  },
  exit: {
    opacity: 0,
    scale: 0.9,
    transition: {
      duration: 0.15,
      ease: 'easeIn',
    },
  },
}

export const backdropAnimation = {
  initial: {
    opacity: 0,
  },
  animate: {
    opacity: 1,
    transition: {
      duration: 0.2,
      ease: 'easeOut',
    },
  },
  exit: {
    opacity: 0,
    transition: {
      duration: 0.15,
      ease: 'easeIn',
    },
  },
}

// List animations
export const listAnimation = {
  initial: {
    opacity: 0,
  },
  animate: {
    opacity: 1,
    transition: {
      staggerChildren: 0.05,
      delayChildren: 0.1,
    },
  },
}

export const listItemAnimation = {
  initial: {
    opacity: 0,
    x: -20,
  },
  animate: {
    opacity: 1,
    x: 0,
    transition: {
      duration: 0.3,
      ease: 'easeOut',
    },
  },
}

// Notification animations
export const notificationAnimation = {
  initial: {
    opacity: 0,
    x: 300,
    scale: 0.8,
  },
  animate: {
    opacity: 1,
    x: 0,
    scale: 1,
    transition: {
      duration: 0.3,
      ease: 'easeOut',
    },
  },
  exit: {
    opacity: 0,
    x: 300,
    scale: 0.8,
    transition: {
      duration: 0.2,
      ease: 'easeIn',
    },
  },
}

// Progress bar animation
export const progressAnimation = {
  initial: {
    width: 0,
  },
  animate: {
    width: '100%',
    transition: {
      duration: 0.5,
      ease: 'easeOut',
    },
  },
}

// Typing animation
export const typingAnimation = {
  animate: {
    opacity: [0, 1, 0],
    transition: {
      duration: 1,
      repeat: Infinity,
      ease: 'easeInOut',
    },
  },
}

// Shake animation for errors
export const shakeAnimation = {
  animate: {
    x: [0, -10, 10, -10, 10, 0],
    transition: {
      duration: 0.5,
      ease: 'easeInOut',
    },
  },
}

// Slide animations for mobile
export const slideUpMobile = {
  initial: {
    opacity: 0,
    y: 100,
  },
  animate: {
    opacity: 1,
    y: 0,
    transition: {
      duration: 0.3,
      ease: 'easeOut',
    },
  },
  exit: {
    opacity: 0,
    y: 100,
    transition: {
      duration: 0.2,
      ease: 'easeIn',
    },
  },
}

export const slideDownMobile = {
  initial: {
    opacity: 0,
    y: -100,
  },
  animate: {
    opacity: 1,
    y: 0,
    transition: {
      duration: 0.3,
      ease: 'easeOut',
    },
  },
  exit: {
    opacity: 0,
    y: -100,
    transition: {
      duration: 0.2,
      ease: 'easeIn',
    },
  },
}

// Gesture animations for mobile
export const swipeAnimation = {
  drag: 'x',
  dragConstraints: { left: -100, right: 100 },
  dragElastic: 0.2,
  whileDrag: {
    scale: 1.05,
    transition: {
      duration: 0.1,
      ease: 'easeInOut',
    },
  },
}

// Touch feedback animations
export const touchFeedback = {
  whileTap: {
    scale: 0.95,
    transition: {
      duration: 0.1,
      ease: 'easeInOut',
    },
  },
}

// Focus animations
export const focusAnimation = {
  whileFocus: {
    scale: 1.02,
    boxShadow: '0 0 0 3px rgba(59, 130, 246, 0.1)',
    transition: {
      duration: 0.2,
      ease: 'easeInOut',
    },
  },
}

// Utility functions for animations
export const getStaggerDelay = (index: number, delay: number = 0.1) => ({
  transition: {
    delay: index * delay,
  },
})

export const getEasingFunction = (type: 'ease' | 'easeIn' | 'easeOut' | 'easeInOut') => {
  const easingMap = {
    ease: 'ease',
    easeIn: 'easeIn',
    easeOut: 'easeOut',
    easeInOut: 'easeInOut',
  }
  return easingMap[type]
}

export const createSpringAnimation = (stiffness: number = 300, damping: number = 30) => ({
  type: 'spring',
  stiffness,
  damping,
})

export const createTweenAnimation = (duration: number = 0.3, ease: string = 'easeOut') => ({
  duration,
  ease,
})

// Animation presets for common use cases
export const animationPresets = {
  // Page transitions
  pageEnter: fadeInUp,
  pageExit: fadeInUp,
  
  // Modal animations
  modalEnter: modalAnimation,
  modalExit: modalAnimation,
  
  // List animations
  listEnter: listAnimation,
  listItemEnter: listItemAnimation,
  
  // Button animations
  buttonHover: hoverScale,
  buttonTap: touchFeedback,
  
  // Card animations
  cardHover: hoverLift,
  cardEnter: fadeInUp,
  
  // Mobile animations
  mobileSlideUp: slideUpMobile,
  mobileSlideDown: slideDownMobile,
  
  // Loading animations
  loadingPulse: pulseAnimation,
  loadingSpin: spinAnimation,
  loadingBounce: bounceAnimation,
  
  // Error animations
  errorShake: shakeAnimation,
  
  // Success animations
  successScale: scaleIn,
  
  // Notification animations
  notificationEnter: notificationAnimation,
  notificationExit: notificationAnimation,
}

export default animationPresets
