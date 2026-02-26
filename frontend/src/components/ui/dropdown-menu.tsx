import type { ButtonHTMLAttributes, HTMLAttributes, ReactNode } from 'react'

export function DropdownMenu({ children }: { children: ReactNode }) {
  return <>{children}</>
}

export function DropdownMenuTrigger({ children, ...props }: ButtonHTMLAttributes<HTMLButtonElement>) {
  return <button type="button" {...props}>{children}</button>
}

export function DropdownMenuContent({ children, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div {...props}>{children}</div>
}

export function DropdownMenuItem({ children, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div role="menuitem" {...props}>{children}</div>
}
