<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PasswordSecurityService
{
    /**
     * Check if password has been breached using HaveIBeenPwned API
     */
    public function isPasswordBreached(string $password): bool
    {
        try {
            $sha1Hash = strtoupper(sha1($password));
            $prefix = substr($sha1Hash, 0, 5);
            $suffix = substr($sha1Hash, 5);
            
            // Cache the result for 1 hour to avoid repeated API calls
            $cacheKey = "password_breach_check_{$prefix}";
            
            $response = Cache::remember($cacheKey, 3600, function () use ($prefix) {
                return Http::timeout(5)->get("https://api.pwnedpasswords.com/range/{$prefix}");
            });
            
            if ($response->successful()) {
                $hashes = $response->body();
                return str_contains($hashes, $suffix);
            }
            
            // If API is down, log but don't block user
            Log::warning('HaveIBeenPwned API unavailable', ['prefix' => $prefix]);
            return false;
            
        } catch (\Exception $e) {
            Log::error('Password breach check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Validate password strength
     */
    public function validatePasswordStrength(string $password): array
    {
        $errors = [];
        $score = 0;
        
        // Length check
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        } else {
            $score += 1;
        }
        
        if (strlen($password) >= 12) {
            $score += 1;
        }
        
        // Character variety checks
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        } else {
            $score += 1;
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        } else {
            $score += 1;
        }
        
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Password must contain at least one number';
        } else {
            $score += 1;
        }
        
        if (!preg_match('/[^a-zA-Z\d]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        } else {
            $score += 1;
        }
        
        // Common password patterns
        $commonPatterns = [
            '/^password/i',
            '/^123456/',
            '/^qwerty/i',
            '/^admin/i',
            '/^letmein/i',
            '/^welcome/i',
        ];
        
        foreach ($commonPatterns as $pattern) {
            if (preg_match($pattern, $password)) {
                $errors[] = 'Password contains common patterns and is easily guessable';
                break;
            }
        }
        
        // Sequential characters
        if (preg_match('/(?:012|123|234|345|456|567|678|789|890|abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i', $password)) {
            $errors[] = 'Password contains sequential characters';
        }
        
        // Repeated characters
        if (preg_match('/(.)\1{2,}/', $password)) {
            $errors[] = 'Password contains too many repeated characters';
        }
        
        // Breach check
        if ($this->isPasswordBreached($password)) {
            $errors[] = 'This password has been found in data breaches and should not be used';
            $score = 0; // Breached password gets 0 score
        }
        
        // Calculate strength
        $strength = 'weak';
        if ($score >= 4 && empty($errors)) {
            $strength = 'strong';
        } elseif ($score >= 2 && count($errors) <= 2) {
            $strength = 'medium';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'score' => $score,
            'strength' => $strength,
            'suggestions' => $this->getPasswordSuggestions($errors)
        ];
    }

    /**
     * Generate password suggestions based on validation errors
     */
    private function getPasswordSuggestions(array $errors): array
    {
        $suggestions = [];
        
        if (!empty($errors)) {
            $suggestions[] = 'Use a mix of uppercase and lowercase letters';
            $suggestions[] = 'Include numbers and special characters (!@#$%^&*)';
            $suggestions[] = 'Make it at least 12 characters long';
            $suggestions[] = 'Avoid common words and patterns';
            $suggestions[] = 'Consider using a passphrase with multiple words';
            $suggestions[] = 'Use a password manager to generate and store secure passwords';
        }
        
        return array_unique($suggestions);
    }

    /**
     * Hash password securely
     */
    public function hashPassword(string $password): string
    {
        return Hash::make($password);
    }

    /**
     * Verify password against hash
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return Hash::check($password, $hash);
    }

    /**
     * Check if password needs rehashing (for algorithm updates)
     */
    public function needsRehash(string $hash): bool
    {
        return Hash::needsRehash($hash);
    }

    /**
     * Generate a secure random password
     */
    public function generateSecurePassword(int $length = 16): string
    {
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        $password = '';
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        
        $allChars = $lowercase . $uppercase . $numbers . $symbols;
        
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        return str_shuffle($password);
    }

    /**
     * Generate a memorable passphrase
     */
    public function generatePassphrase(int $wordCount = 4): string
    {
        $words = [
            'apple', 'banana', 'cherry', 'dragon', 'elephant', 'forest', 'guitar', 'harmony',
            'island', 'jungle', 'kangaroo', 'lemon', 'mountain', 'ocean', 'penguin', 'quartz',
            'rainbow', 'sunset', 'thunder', 'umbrella', 'violet', 'whisper', 'xylophone', 'yellow', 'zebra',
            'adventure', 'butterfly', 'cascade', 'diamond', 'emerald', 'firefly', 'galaxy', 'horizon',
            'inspire', 'journey', 'kindness', 'liberty', 'melody', 'nature', 'optimize', 'paradise',
            'quantum', 'radiant', 'serenity', 'tranquil', 'universe', 'victory', 'wisdom', 'xenial', 'youthful', 'zenith'
        ];
        
        $selectedWords = [];
        for ($i = 0; $i < $wordCount; $i++) {
            $selectedWords[] = ucfirst($words[array_rand($words)]);
        }
        
        return implode('-', $selectedWords) . random_int(10, 99);
    }

    /**
     * Get password policy configuration
     */
    public function getPasswordPolicy(): array
    {
        return [
            'min_length' => 8,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_numbers' => true,
            'require_symbols' => true,
            'check_breaches' => true,
            'prevent_common_patterns' => true,
            'max_age_days' => 90,
            'history_count' => 5, // Remember last 5 passwords
        ];
    }

    /**
     * Estimate time to crack password
     */
    public function estimateCrackTime(string $password): array
    {
        $charset = 0;
        
        if (preg_match('/[a-z]/', $password)) $charset += 26;
        if (preg_match('/[A-Z]/', $password)) $charset += 26;
        if (preg_match('/\d/', $password)) $charset += 10;
        if (preg_match('/[^a-zA-Z\d]/', $password)) $charset += 32;
        
        $length = strlen($password);
        $combinations = pow($charset, $length);
        
        // Assume 1 billion attempts per second (modern hardware)
        $secondsToCrack = $combinations / 2 / 1000000000;
        
        $times = [
            'seconds' => $secondsToCrack,
            'minutes' => $secondsToCrack / 60,
            'hours' => $secondsToCrack / 3600,
            'days' => $secondsToCrack / 86400,
            'years' => $secondsToCrack / 31536000,
        ];
        
        // Find the most appropriate unit
        if ($times['years'] >= 1) {
            $display = number_format($times['years'], 1) . ' years';
        } elseif ($times['days'] >= 1) {
            $display = number_format($times['days'], 1) . ' days';
        } elseif ($times['hours'] >= 1) {
            $display = number_format($times['hours'], 1) . ' hours';
        } elseif ($times['minutes'] >= 1) {
            $display = number_format($times['minutes'], 1) . ' minutes';
        } else {
            $display = number_format($times['seconds'], 1) . ' seconds';
        }
        
        return [
            'display' => $display,
            'seconds' => $secondsToCrack,
            'charset_size' => $charset,
            'combinations' => $combinations
        ];
    }
}
