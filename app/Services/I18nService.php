<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class I18nService
{
    protected array $supportedLanguages = [
        'en' => 'English',
        'vi' => 'Tiếng Việt',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'ja' => '日本語',
        'zh' => '中文',
    ];

    protected array $supportedTimezones = [
        'UTC' => 'UTC',
        'America/New_York' => 'Eastern Time (ET)',
        'America/Chicago' => 'Central Time (CT)',
        'America/Denver' => 'Mountain Time (MT)',
        'America/Los_Angeles' => 'Pacific Time (PT)',
        'Europe/London' => 'Greenwich Mean Time (GMT)',
        'Europe/Paris' => 'Central European Time (CET)',
        'Asia/Tokyo' => 'Japan Standard Time (JST)',
        'Asia/Shanghai' => 'China Standard Time (CST)',
        'Asia/Ho_Chi_Minh' => 'Vietnam Time (ICT)',
    ];

    protected array $supportedCurrencies = [
        'USD' => 'US Dollar ($)',
        'EUR' => 'Euro (€)',
        'GBP' => 'British Pound (£)',
        'JPY' => 'Japanese Yen (¥)',
        'CAD' => 'Canadian Dollar (C$)',
        'AUD' => 'Australian Dollar (A$)',
        'VND' => 'Vietnamese Dong (₫)',
    ];

    /**
     * Get supported languages
     */
    public function getSupportedLanguages(): array
    {
        return $this->supportedLanguages;
    }

    /**
     * Get supported timezones
     */
    public function getSupportedTimezones(): array
    {
        return $this->supportedTimezones;
    }

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies(): array
    {
        return $this->supportedCurrencies;
    }

    /**
     * Set application language
     */
    public function setLanguage(string $language): bool
    {
        if (!array_key_exists($language, $this->supportedLanguages)) {
            Log::warning('Unsupported language requested', ['language' => $language]);
            return false;
        }

        App::setLocale($language);
        Session::put('locale', $language);
        Cookie::queue('locale', $language, 60 * 24 * 30); // 30 days

        Log::info('Language changed', ['language' => $language]);
        return true;
    }

    /**
     * Get current language
     */
    public function getCurrentLanguage(): string
    {
        return App::getLocale();
    }

    /**
     * Set application timezone
     */
    public function setTimezone(string $timezone): bool
    {
        if (!array_key_exists($timezone, $this->supportedTimezones)) {
            Log::warning('Unsupported timezone requested', ['timezone' => $timezone]);
            return false;
        }

        // Set Carbon timezone
        Carbon::setTestNow(Carbon::now($timezone));
        
        Session::put('timezone', $timezone);
        Cookie::queue('timezone', $timezone, 60 * 24 * 30); // 30 days

        Log::info('Timezone changed', ['timezone' => $timezone]);
        return true;
    }

    /**
     * Get current timezone
     */
    public function getCurrentTimezone(): string
    {
        return Session::get('timezone', 'UTC');
    }

    /**
     * Format date according to locale
     */
    public function formatDate(\DateTime $date, string $format = null): string
    {
        $locale = $this->getCurrentLanguage();
        $timezone = $this->getCurrentTimezone();
        
        $carbon = Carbon::parse($date)->setTimezone($timezone);
        
        if (!$format) {
            $format = $this->getDateFormat($locale);
        }
        
        return $carbon->locale($locale)->translatedFormat($format);
    }

    /**
     * Format time according to locale
     */
    public function formatTime(\DateTime $time, string $format = null): string
    {
        $locale = $this->getCurrentLanguage();
        $timezone = $this->getCurrentTimezone();
        
        $carbon = Carbon::parse($time)->setTimezone($timezone);
        
        if (!$format) {
            $format = $this->getTimeFormat($locale);
        }
        
        return $carbon->locale($locale)->translatedFormat($format);
    }

    /**
     * Format datetime according to locale
     */
    public function formatDateTime(\DateTime $datetime, string $format = null): string
    {
        $locale = $this->getCurrentLanguage();
        $timezone = $this->getCurrentTimezone();
        
        $carbon = Carbon::parse($datetime)->setTimezone($timezone);
        
        if (!$format) {
            $format = $this->getDateTimeFormat($locale);
        }
        
        return $carbon->locale($locale)->translatedFormat($format);
    }

    /**
     * Format number according to locale
     */
    public function formatNumber(float $number, int $decimals = 2): string
    {
        $locale = $this->getCurrentLanguage();
        
        return number_format($number, $decimals, $this->getDecimalSeparator($locale), $this->getThousandsSeparator($locale));
    }

    /**
     * Format currency according to locale
     */
    public function formatCurrency(float $amount, string $currency = null): string
    {
        $locale = $this->getCurrentLanguage();
        $currency = $currency ?: $this->getCurrentCurrency();
        
        $formatted = $this->formatNumber($amount, 2);
        
        return $this->getCurrencySymbol($currency) . $formatted;
    }

    /**
     * Get date format for locale
     */
    protected function getDateFormat(string $locale): string
    {
        $formats = [
            'en' => 'M j, Y',
            'vi' => 'j/n/Y',
            'es' => 'j/n/Y',
            'fr' => 'j/n/Y',
            'de' => 'j.n.Y',
            'ja' => 'Y年n月j日',
            'zh' => 'Y年n月j日',
        ];
        
        return $formats[$locale] ?? 'Y-m-d';
    }

    /**
     * Get time format for locale
     */
    protected function getTimeFormat(string $locale): string
    {
        $formats = [
            'en' => 'g:i A',
            'vi' => 'H:i',
            'es' => 'H:i',
            'fr' => 'H:i',
            'de' => 'H:i',
            'ja' => 'H:i',
            'zh' => 'H:i',
        ];
        
        return $formats[$locale] ?? 'H:i';
    }

    /**
     * Get datetime format for locale
     */
    protected function getDateTimeFormat(string $locale): string
    {
        return $this->getDateFormat($locale) . ' ' . $this->getTimeFormat($locale);
    }

    /**
     * Get decimal separator for locale
     */
    protected function getDecimalSeparator(string $locale): string
    {
        $separators = [
            'en' => '.',
            'vi' => ',',
            'es' => ',',
            'fr' => ',',
            'de' => ',',
            'ja' => '.',
            'zh' => '.',
        ];
        
        return $separators[$locale] ?? '.';
    }

    /**
     * Get thousands separator for locale
     */
    protected function getThousandsSeparator(string $locale): string
    {
        $separators = [
            'en' => ',',
            'vi' => '.',
            'es' => '.',
            'fr' => ' ',
            'de' => '.',
            'ja' => ',',
            'zh' => ',',
        ];
        
        return $separators[$locale] ?? ',';
    }

    /**
     * Get currency symbol
     */
    protected function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'VND' => '₫',
        ];
        
        return $symbols[$currency] ?? $currency;
    }

    /**
     * Get current currency
     */
    public function getCurrentCurrency(): string
    {
        return Session::get('currency', 'USD');
    }

    /**
     * Initialize i18n from user preferences
     */
    public function initializeFromUserPreferences(array $preferences): void
    {
        if (isset($preferences['language'])) {
            $this->setLanguage($preferences['language']);
        }
        
        if (isset($preferences['timezone'])) {
            $this->setTimezone($preferences['timezone']);
        }
        
        if (isset($preferences['currency'])) {
            Session::put('currency', $preferences['currency']);
        }
    }

    /**
     * Get i18n configuration
     */
    public function getConfiguration(): array
    {
        return [
            'current_language' => $this->getCurrentLanguage(),
            'current_timezone' => $this->getCurrentTimezone(),
            'current_currency' => $this->getCurrentCurrency(),
            'supported_languages' => $this->getSupportedLanguages(),
            'supported_timezones' => $this->getSupportedTimezones(),
            'supported_currencies' => $this->getSupportedCurrencies(),
        ];
    }
}
