// Animation utilities for UI components
export const hoverScale = {
  whileHover: {
    scale: 1.02,
    transition: {
      duration: 0.2,
      ease: "easeInOut" as const
    }
  }
}

export const hoverLift = {
  whileHover: {
    y: -2,
    transition: {
      duration: 0.2,
      ease: "easeInOut" as const
    }
  }
}

export const touchFeedback = {
  whileTap: {
    scale: 0.98,
    transition: {
      duration: 0.1,
      ease: "easeInOut" as const
    }
  }
}

export const focusAnimation = {
  whileFocus: {
    scale: 1.01,
    transition: {
      duration: 0.15,
      ease: "easeInOut" as const
    }
  }
}

export const fadeInUp = {
  initial: { opacity: 0, y: 20 },
  animate: { opacity: 1, y: 0 },
  transition: { duration: 0.3, ease: "easeOut" as const }
}

export const staggerContainer = {
  animate: {
    transition: {
      staggerChildren: 0.1
    }
  }
}

export const staggerItem = {
  initial: { opacity: 0, y: 20 },
  animate: { opacity: 1, y: 0 },
  transition: { duration: 0.3, ease: "easeOut" as const }
}

export const slideUpMobile = {
  initial: { y: "100%", opacity: 0 },
  animate: { y: 0, opacity: 1 },
  exit: { y: "100%", opacity: 0 },
  transition: { duration: 0.3, ease: "easeInOut" as const }
}

export const slideDownMobile = {
  initial: { y: "-100%", opacity: 0 },
  animate: { y: 0, opacity: 1 },
  exit: { y: "-100%", opacity: 0 },
  transition: { duration: 0.3, ease: "easeInOut" as const }
}

export const swipeAnimation = {
  initial: { x: 0 },
  animate: { x: 0 },
  transition: { duration: 0.2, ease: "easeInOut" as const }
}

export const pulseAnimation = {
  animate: {
    scale: [1, 1.05, 1],
    transition: {
      duration: 1.5,
      repeat: Infinity,
      ease: "easeInOut" as const
    }
  }
}

export const spinAnimation = {
  animate: {
    rotate: 360,
    transition: {
      duration: 1,
      repeat: Infinity,
      ease: "linear" as const
    }
  }
}

export const bounceAnimation = {
  animate: {
    y: [0, -10, 0],
    transition: {
      duration: 0.6,
      repeat: Infinity,
      ease: "easeInOut" as const
    }
  }
}
