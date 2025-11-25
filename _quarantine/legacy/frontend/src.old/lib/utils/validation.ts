/**
 * Validation utilities cho forms và data
 */

/**
 * Kiểm tra email hợp lệ
 */
export const isValidEmail = (email: string): boolean => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

/**
 * Kiểm tra password mạnh
 */
export const isStrongPassword = (password: string): boolean => {
  // Ít nhất 8 ký tự, có chữ hoa, chữ thường, số và ký tự đặc biệt
  const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/
  return passwordRegex.test(password)
}

/**
 * Kiểm tra số điện thoại Việt Nam
 */
export const isValidPhoneNumber = (phone: string): boolean => {
  const phoneRegex = /^(\+84|84|0)(3|5|7|8|9)[0-9]{8}$/
  return phoneRegex.test(phone.replace(/\s/g, ''))
}

/**
 * Kiểm tra URL hợp lệ
 */
export const isValidUrl = (url: string): boolean => {
  try {
    new URL(url)
    return true
  } catch {
    return false
  }
}

/**
 * Kiểm tra ngày hợp lệ
 */
export const isValidDate = (date: string): boolean => {
  const dateObj = new Date(date)
  return !isNaN(dateObj.getTime())
}

/**
 * Kiểm tra ngày trong tương lai
 */
export const isFutureDate = (date: string): boolean => {
  const dateObj = new Date(date)
  const now = new Date()
  return dateObj > now
}

/**
 * Kiểm tra range ngày hợp lệ
 */
export const isValidDateRange = (startDate: string, endDate: string): boolean => {
  const start = new Date(startDate)
  const end = new Date(endDate)
  return start <= end
}

/**
 * Kiểm tra số dương
 */
export const isPositiveNumber = (value: number): boolean => {
  return value > 0
}

/**
 * Kiểm tra phần trăm hợp lệ (0-100)
 */
export const isValidPercentage = (value: number): boolean => {
  return value >= 0 && value <= 100
}

/**
 * Validation rules cho forms
 */
export const validationRules = {
  required: (value: any) => {
    if (typeof value === 'string') {
      return value.trim().length > 0 || 'Trường này là bắt buộc'
    }
    return value !== null && value !== undefined || 'Trường này là bắt buộc'
  },
  
  email: (value: string) => {
    return isValidEmail(value) || 'Email không hợp lệ'
  },
  
  minLength: (min: number) => (value: string) => {
    return value.length >= min || `Tối thiểu ${min} ký tự`
  },
  
  maxLength: (max: number) => (value: string) => {
    return value.length <= max || `Tối đa ${max} ký tự`
  },
  
  strongPassword: (value: string) => {
    return isStrongPassword(value) || 'Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt'
  },
  
  phoneNumber: (value: string) => {
    return isValidPhoneNumber(value) || 'Số điện thoại không hợp lệ'
  },
  
  url: (value: string) => {
    return isValidUrl(value) || 'URL không hợp lệ'
  },
  
  positiveNumber: (value: number) => {
    return isPositiveNumber(value) || 'Giá trị phải lớn hơn 0'
  },
  
  percentage: (value: number) => {
    return isValidPercentage(value) || 'Phần trăm phải từ 0 đến 100'
  },
}