/**
 * Date utility functions cho Interaction Logs
 */
import { format, formatDistanceToNow, parseISO, isValid } from 'date-fns'
import { vi } from 'date-fns/locale'

/**
 * Format date theo định dạng Việt Nam
 */
export function formatDate(date: string | Date, formatStr: string = 'dd/MM/yyyy'): string {
  try {
    const dateObj = typeof date === 'string' ? parseISO(date) : date
    if (!isValid(dateObj)) return 'Ngày không hợp lệ'
    return format(dateObj, formatStr, { locale: vi })
  } catch {
    return 'Ngày không hợp lệ'
  }
}

/**
 * Format datetime với giờ phút
 */
export function formatDateTime(date: string | Date): string {
  return formatDate(date, 'dd/MM/yyyy HH:mm')
}

/**
 * Format relative time (vd: "2 giờ trước")
 */
export function formatRelativeTime(date: string | Date): string {
  try {
    const dateObj = typeof date === 'string' ? parseISO(date) : date
    if (!isValid(dateObj)) return 'Không xác định'
    return formatDistanceToNow(dateObj, { addSuffix: true, locale: vi })
  } catch {
    return 'Không xác định'
  }
}

/**
 * Tạo date range cho filter
 */
export function createDateRange(days: number): { start: string; end: string } {
  const end = new Date()
  const start = new Date()
  start.setDate(start.getDate() - days)
  
  return {
    start: start.toISOString().split('T')[0],
    end: end.toISOString().split('T')[0]
  }
}

/**
 * Predefined date ranges
 */
export const DATE_RANGES = {
  TODAY: () => createDateRange(0),
  YESTERDAY: () => createDateRange(1),
  LAST_7_DAYS: () => createDateRange(7),
  LAST_30_DAYS: () => createDateRange(30),
  LAST_90_DAYS: () => createDateRange(90)
}