/**
 * JSend response helper utilities
 */
import type { JSendResponse } from '../types/interactionLog'

/**
 * Kiểm tra xem response có phải là success không
 */
export function isJSendSuccess<T>(response: JSendResponse<T>): response is JSendResponse<T> & { status: 'success'; data: T } {
  return response.status === 'success' && response.data !== undefined
}

/**
 * Kiểm tra xem response có phải là error không
 */
export function isJSendError(response: JSendResponse): response is JSendResponse & { status: 'error'; message: string } {
  return response.status === 'error'
}

/**
 * Kiểm tra xem response có phải là fail không
 */
export function isJSendFail(response: JSendResponse): response is JSendResponse & { status: 'fail' } {
  return response.status === 'fail'
}

/**
 * Extract data từ JSend response hoặc throw error
 */
export function extractJSendData<T>(response: JSendResponse<T>): T {
  if (isJSendSuccess(response)) {
    return response.data
  }
  
  if (isJSendError(response)) {
    throw new Error(response.message || 'API Error')
  }
  
  throw new Error('API request failed')
}

/**
 * Tạo JSend success response (cho testing)
 */
export function createJSendSuccess<T>(data: T, meta?: any): JSendResponse<T> {
  return {
    status: 'success',
    data,
    ...(meta && { meta })
  }
}

/**
 * Tạo JSend error response (cho testing)
 */
export function createJSendError(message: string): JSendResponse {
  return {
    status: 'error',
    message
  }
}