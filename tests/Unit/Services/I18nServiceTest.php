<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\I18nService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class I18nServiceTest extends TestCase
{
    use RefreshDatabase;

    protected I18nService $i18nService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->i18nService = new I18nService();
    }

    /** @test */
    public function it_can_get_supported_languages()
    {
        $languages = $this->i18nService->getSupportedLanguages();
        
        $this->assertIsArray($languages);
        $this->assertArrayHasKey('en', $languages);
        $this->assertArrayHasKey('vi', $languages);
        $this->assertEquals('English', $languages['en']);
        $this->assertEquals('Tiếng Việt', $languages['vi']);
    }

    /** @test */
    public function it_can_get_supported_timezones()
    {
        $timezones = $this->i18nService->getSupportedTimezones();
        
        $this->assertIsArray($timezones);
        $this->assertArrayHasKey('UTC', $timezones);
        $this->assertArrayHasKey('Asia/Ho_Chi_Minh', $timezones);
        $this->assertEquals('UTC', $timezones['UTC']);
        $this->assertEquals('Vietnam Time (ICT)', $timezones['Asia/Ho_Chi_Minh']);
    }

    /** @test */
    public function it_can_get_supported_currencies()
    {
        $currencies = $this->i18nService->getSupportedCurrencies();
        
        $this->assertIsArray($currencies);
        $this->assertArrayHasKey('USD', $currencies);
        $this->assertArrayHasKey('VND', $currencies);
        $this->assertEquals('US Dollar ($)', $currencies['USD']);
        $this->assertEquals('Vietnamese Dong (₫)', $currencies['VND']);
    }

    /** @test */
    public function it_can_set_language()
    {
        $result = $this->i18nService->setLanguage('vi');
        
        $this->assertTrue($result);
        $this->assertEquals('vi', $this->i18nService->getCurrentLanguage());
        $this->assertEquals('vi', App::getLocale());
    }

    /** @test */
    public function it_rejects_invalid_language()
    {
        $result = $this->i18nService->setLanguage('invalid');
        
        $this->assertFalse($result);
        $this->assertEquals('en', $this->i18nService->getCurrentLanguage());
    }

    /** @test */
    public function it_can_set_timezone()
    {
        $result = $this->i18nService->setTimezone('Asia/Ho_Chi_Minh');
        
        $this->assertTrue($result);
        $this->assertEquals('Asia/Ho_Chi_Minh', $this->i18nService->getCurrentTimezone());
    }

    /** @test */
    public function it_rejects_invalid_timezone()
    {
        $result = $this->i18nService->setTimezone('Invalid/Timezone');
        
        $this->assertFalse($result);
        $this->assertEquals('UTC', $this->i18nService->getCurrentTimezone());
    }

    /** @test */
    public function it_can_format_date()
    {
        $this->i18nService->setLanguage('en');
        $date = new \DateTime('2025-01-15');
        
        $formatted = $this->i18nService->formatDate($date);
        
        $this->assertStringContainsString('Jan', $formatted);
        $this->assertStringContainsString('2025', $formatted);
    }

    /** @test */
    public function it_can_format_date_in_vietnamese()
    {
        $this->i18nService->setLanguage('vi');
        $date = new \DateTime('2025-01-15');
        
        $formatted = $this->i18nService->formatDate($date);
        
        $this->assertStringContainsString('15', $formatted);
        $this->assertStringContainsString('1', $formatted);
        $this->assertStringContainsString('2025', $formatted);
    }

    /** @test */
    public function it_can_format_time()
    {
        $this->i18nService->setLanguage('en');
        $time = new \DateTime('2025-01-15 14:30:00');
        
        $formatted = $this->i18nService->formatTime($time);
        
        $this->assertStringContainsString('2:30', $formatted);
        $this->assertStringContainsString('PM', $formatted);
    }

    /** @test */
    public function it_can_format_time_in_24_hour_format()
    {
        $this->i18nService->setLanguage('vi');
        $time = new \DateTime('2025-01-15 14:30:00');
        
        $formatted = $this->i18nService->formatTime($time);
        
        $this->assertStringContainsString('14:30', $formatted);
    }

    /** @test */
    public function it_can_format_datetime()
    {
        $this->i18nService->setLanguage('en');
        $datetime = new \DateTime('2025-01-15 14:30:00');
        
        $formatted = $this->i18nService->formatDateTime($datetime);
        
        $this->assertStringContainsString('Jan', $formatted);
        $this->assertStringContainsString('2025', $formatted);
        $this->assertStringContainsString('2:30', $formatted);
    }

    /** @test */
    public function it_can_format_number()
    {
        $this->i18nService->setLanguage('en');
        
        $formatted = $this->i18nService->formatNumber(1234.56);
        
        $this->assertEquals('1,234.56', $formatted);
    }

    /** @test */
    public function it_can_format_number_in_vietnamese_locale()
    {
        $this->i18nService->setLanguage('vi');
        
        $formatted = $this->i18nService->formatNumber(1234.56);
        
        $this->assertEquals('1.234,56', $formatted);
    }

    /** @test */
    public function it_can_format_currency()
    {
        $this->i18nService->setLanguage('en');
        
        $formatted = $this->i18nService->formatCurrency(1234.56, 'USD');
        
        $this->assertEquals('$1,234.56', $formatted);
    }

    /** @test */
    public function it_can_format_currency_in_vietnamese_dong()
    {
        $this->i18nService->setLanguage('vi');
        
        $formatted = $this->i18nService->formatCurrency(1234567, 'VND');
        
        $this->assertEquals('₫1.234.567,00', $formatted);
    }

    /** @test */
    public function it_can_initialize_from_user_preferences()
    {
        $preferences = [
            'language' => 'vi',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND'
        ];
        
        $this->i18nService->initializeFromUserPreferences($preferences);
        
        $this->assertEquals('vi', $this->i18nService->getCurrentLanguage());
        $this->assertEquals('Asia/Ho_Chi_Minh', $this->i18nService->getCurrentTimezone());
    }

    /** @test */
    public function it_can_get_configuration()
    {
        $config = $this->i18nService->getConfiguration();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('current_language', $config);
        $this->assertArrayHasKey('current_timezone', $config);
        $this->assertArrayHasKey('current_currency', $config);
        $this->assertArrayHasKey('supported_languages', $config);
        $this->assertArrayHasKey('supported_timezones', $config);
        $this->assertArrayHasKey('supported_currencies', $config);
    }

    /** @test */
    public function it_handles_timezone_conversion()
    {
        $this->i18nService->setTimezone('Asia/Ho_Chi_Minh');
        $datetime = new \DateTime('2025-01-15 12:00:00', new \DateTimeZone('UTC'));
        
        $formatted = $this->i18nService->formatDateTime($datetime);
        
        // Should show time in Vietnam timezone (UTC+7)
        $this->assertStringContainsString('7:00', $formatted);
    }

    /** @test */
    public function it_persists_language_in_session()
    {
        $this->i18nService->setLanguage('vi');
        
        $this->assertEquals('vi', Session::get('locale'));
    }

    /** @test */
    public function it_persists_timezone_in_session()
    {
        $this->i18nService->setTimezone('Asia/Ho_Chi_Minh');
        
        $this->assertEquals('Asia/Ho_Chi_Minh', Session::get('timezone'));
    }
}
