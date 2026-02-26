import { type ClassValue, clsx } from "clsx"
import { twMerge } from "tailwind-merge"
import { format } from "date-fns"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatCurrency(value: number | string): string {
  const amount = typeof value === "string" ? Number(value) : value
  if (!Number.isFinite(amount)) {
    return "0 VND"
  }

  return `${new Intl.NumberFormat("vi-VN").format(amount)} VND`
}

export function formatDate(value: string | number | Date): string {
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) {
    return ""
  }

  return format(date, "dd/MM/yyyy")
}
