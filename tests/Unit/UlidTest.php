<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Src\Foundation\Foundation;
use Illuminate\Support\Str;

/**
 * Test cases cho ULID implementation
 */
class UlidTest extends TestCase
{
    /**
     * Test tạo ULID hợp lệ
     */
    public function test_generate_ulid_returns_valid_format(): void
    {
        $ulid = Foundation::generateUlid();
        
        // ULID phải có độ dài 26 ký tự
        $this->assertEquals(26, strlen($ulid));
        
        // ULID chỉ chứa ký tự hợp lệ (0-9, A-Z, a-z)
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{26}$/', $ulid);
    }
    
    /**
     * Test ULID được tạo là duy nhất
     */
    public function test_generate_ulid_creates_unique_values(): void
    {
        $ulids = [];
        
        // Tạo 100 ULID và kiểm tra tính duy nhất
        for ($i = 0; $i < 100; $i++) {
            $ulids[] = Foundation::generateUlid();
        }
        
        $uniqueUlids = array_unique($ulids);
        $this->assertEquals(count($ulids), count($uniqueUlids));
    }
    
    /**
     * Test ULID có thể sắp xếp theo thời gian
     */
    public function test_ulid_is_lexicographically_sortable(): void
    {
        $ulid1 = Foundation::generateUlid();
        
        // Đợi 1ms để đảm bảo timestamp khác nhau
        usleep(1000);
        
        $ulid2 = Foundation::generateUlid();
        
        // ULID sau phải lớn hơn ULID trước (theo thứ tự từ điển)
        $this->assertGreaterThan($ulid1, $ulid2);
    }
    
    /**
     * Test Laravel Str::ulid() hoạt động đúng
     */
    public function test_laravel_str_ulid_works(): void
    {
        $ulid = (string) Str::ulid();
        
        $this->assertEquals(26, strlen($ulid));
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{26}$/', $ulid);
    }
}