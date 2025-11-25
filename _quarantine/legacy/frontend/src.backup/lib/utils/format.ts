import { format, parseISO, formatDistanceToNow } from 'date-fns'
import { vi, enUS } from 'date-fns/locale'
import { DATE_FORMATS } from '../constants'

/**
 * Utilities cho formatting dữ liệu
 */

/**
 * Format số tiền theo định dạng VND
 */
export const formatCurrency = (amount: number, currency = 'VND'): string => {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)
}

/**
 * Format số với dấu phẩy phân cách
 */
export const formatNumber = (num: number): string => {
  return new Intl.NumberFormat('vi-VN').format(num)
}

/**
 * Format phần trăm
 */
export const formatPercentage = (value: number, decimals = 1): string => {
  return `${value.toFixed(decimals)}%`
}

/**
 * Format ngày tháng
 */
export const formatDate = (
  date: string | Date,
  formatStr = DATE_FORMATS.DISPLAY,
  locale = 'vi'
): string => {
  const dateObj = typeof date === 'string' ? parseISO(date) : date
  const localeObj = locale === 'vi' ? vi : enUS
  
  return format(dateObj, formatStr, { locale: localeObj })
}

/**
 * Format thời gian tương đối (vd: "2 giờ trước")
 */
export const formatRelativeTime = (
  date: string | Date,
  locale = 'vi'
): string => {
  const dateObj = typeof date === 'string' ? parseISO(date) : date
  const localeObj = locale === 'vi' ? vi : enUS
  
  return formatDistanceToNow(dateObj, {
    addSuffix: true,
    locale: localeObj,
  })
}

/**
 * Truncate text với ellipsis
 */
export const truncateText = (text: string, maxLength: number): string => {
  if (text.length <= maxLength) return text
  return text.slice(0, maxLength) + '...'
}

/**
 * Format tên file
 */
export const formatFileName = (fileName: string, maxLength = 30): string => {
  if (fileName.length <= maxLength) return fileName
  
  const extension = fileName.split('.').pop()
  const nameWithoutExt = fileName.slice(0, fileName.lastIndexOf('.'))
  const truncatedName = nameWithoutExt.slice(0, maxLength - extension!.length - 4)
  
  return `${truncatedName}...${extension}`
}

/**
 * Format file size
 */
export const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes'
  
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

/**
 * Capitalize first letter
 */
export const capitalize = (str: string): string => {
  return str.charAt(0).toUpperCase() + str.slice(1)
}

/**
 * Convert camelCase to Title Case
 */
export const camelToTitle = (str: string): string => {
  return str
    .replace(/([A-Z])/g, ' $1')
    .replace(/^./, (str) => str.toUpperCase())
    .trim()
}

/**
 * Generate initials from name
 */
export const getInitials = (name: string): string => {
  return name
    .split(' ')
    .map(word => word.charAt(0).toUpperCase())
    .slice(0, 2)
    .join('')
}