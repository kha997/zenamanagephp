import type { HTMLAttributes, ReactNode } from 'react'

export function TooltipProvider({ children }: { children: ReactNode }) {
  return <>{children}</>
}

export function Tooltip({ children }: { children: ReactNode }) {
  return <>{children}</>
}

export function TooltipTrigger({ children }: { children: ReactNode }) {
  return <>{children}</>
}

export function TooltipContent({ children, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div {...props}>{children}</div>
}
