/**
 * Script test API client cho frontend
 * Chạy trong browser console để test kết nối API
 */

import { AuthService } from './lib/api/auth.service'
import { apiClient } from './lib/api/client'

/**
 * Test API connection và authentication flow
 */
export class ApiTester {
  private static testResults: Array<{test: string, success: boolean, message: string}> = []
  
  /**
   * Chạy tất cả các test
   */
  static async runAllTests(): Promise<void> {
    console.log('🚀 Bắt đầu test API connection...')
    
    await this.testApiHealth()
    await this.testAuthLogin()
    await this.testProtectedEndpoint()
    
    this.printResults()
  }
  
  /**
   * Test 1: Kiểm tra API health
   */
  private static async testApiHealth(): Promise<void> {
    try {
      console.log('\n📡 Test 1: API Health Check')
      
      const response = await apiClient.get('/test')
      
      if (response.status === 'success') {
        this.addResult('API Health Check', true, 'API server đang hoạt động')
        console.log('✅ API Health Check thành công')
      } else {
        this.addResult('API Health Check', false, 'API response không đúng format')
        console.log('❌ API Health Check thất bại')
      }
    } catch (error: any) {
      this.addResult('API Health Check', false, error.message)
      console.log('❌ API Health Check lỗi:', error.message)
    }
  }
  
  /**
   * Test 2: Test authentication login
   */
  private static async testAuthLogin(): Promise<void> {
    try {
      console.log('\n🔐 Test 2: Authentication Login')
      
      const authResponse = await AuthService.login({
        email: 'admin@example.com',
        password: 'password'
      })
      
      if (authResponse.access_token && authResponse.user) {
        this.addResult('Authentication Login', true, 'Đăng nhập thành công và nhận JWT token')
        console.log('✅ Login thành công')
        console.log('User:', authResponse.user.name)
        console.log('Token:', authResponse.access_token.substring(0, 50) + '...')
        
        // Lưu token để test tiếp
        localStorage.setItem('auth_token', authResponse.access_token)
        localStorage.setItem('auth_user', JSON.stringify(authResponse.user))
      } else {
        this.addResult('Authentication Login', false, 'Response không chứa token hoặc user')
        console.log('❌ Login response không đúng format')
      }
    } catch (error: any) {
      this.addResult('Authentication Login', false, error.message)
      console.log('❌ Login lỗi:', error.message)
    }
  }
  
  /**
   * Test 3: Test protected endpoint
   */
  private static async testProtectedEndpoint(): Promise<void> {
    try {
      console.log('\n🛡️ Test 3: Protected Endpoint')
      
      const userProfile = await AuthService.getProfile()
      
      if (userProfile && userProfile.id) {
        this.addResult('Protected Endpoint', true, 'Truy cập protected endpoint thành công')
        console.log('✅ Protected endpoint hoạt động')
        console.log('User Profile:', userProfile)
      } else {
        this.addResult('Protected Endpoint', false, 'Không nhận được user profile')
        console.log('❌ Protected endpoint không trả về data')
      }
    } catch (error: any) {
      this.addResult('Protected Endpoint', false, error.message)
      console.log('❌ Protected endpoint lỗi:', error.message)
    }
  }
  
  /**
   * Test 4: Test JWT refresh
   */
  static async testJwtRefresh(): Promise<void> {
    try {
      console.log('\n🔄 Test 4: JWT Refresh')
      
      const refreshResponse = await AuthService.refreshToken()
      
      if (refreshResponse.access_token) {
        this.addResult('JWT Refresh', true, 'Refresh token thành công')
        console.log('✅ JWT Refresh thành công')
        console.log('New Token:', refreshResponse.access_token.substring(0, 50) + '...')
      } else {
        this.addResult('JWT Refresh', false, 'Không nhận được token mới')
        console.log('❌ JWT Refresh thất bại')
      }
    } catch (error: any) {
      this.addResult('JWT Refresh', false, error.message)
      console.log('❌ JWT Refresh lỗi:', error.message)
    }
  }
  
  /**
   * Thêm kết quả test
   */
  private static addResult(test: string, success: boolean, message: string): void {
    this.testResults.push({ test, success, message })
  }
  
  /**
   * In kết quả tổng hợp
   */
  private static printResults(): void {
    console.log('\n📊 KẾT QUẢ TEST API:')
    console.log('=' .repeat(50))
    
    let passCount = 0
    
    this.testResults.forEach(result => {
      const icon = result.success ? '✅' : '❌'
      console.log(`${icon} ${result.test}: ${result.message}`)
      if (result.success) passCount++
    })
    
    console.log('=' .repeat(50))
    console.log(`📈 Tổng kết: ${passCount}/${this.testResults.length} test passed`)
    
    if (passCount === this.testResults.length) {
      console.log('🎉 Tất cả test đều PASS! API connection hoạt động tốt.')
    } else {
      console.log('⚠️ Có test FAIL. Cần kiểm tra lại cấu hình API.')
    }
  }
}

// Export để sử dụng trong browser console
;(window as any).ApiTester = ApiTester

console.log('API Tester đã sẵn sàng. Chạy ApiTester.runAllTests() để bắt đầu test.')