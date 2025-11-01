<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Password Policy Service
 * 
 * Enforces password policies and validates passwords
 * according to security requirements.
 */
class PasswordPolicyService
{
    /**
     * Validate password against policy
     */
    public function validatePassword(string $password): array
    {
        $errors = [];

        // Minimum length
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }

        // Maximum length
        if (strlen($password) > 128) {
            $errors[] = 'Password must not exceed 128 characters';
        }

        // Require uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        // Require lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        // Require number
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        // Require special character
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        // Check against common passwords
        if ($this->isCommonPassword($password)) {
            $errors[] = 'Password is too common. Please choose a more unique password';
        }

        // Check for sequential characters
        if ($this->hasSequentialCharacters($password)) {
            $errors[] = 'Password contains sequential characters. Please avoid patterns like "123" or "abc"';
        }

        // Check for repeated characters
        if ($this->hasRepeatedCharacters($password)) {
            $errors[] = 'Password contains too many repeated characters';
        }

        if (!empty($errors)) {
            return [
                'valid' => false,
                'message' => implode('. ', $errors),
                'errors' => $errors
            ];
        }

        return [
            'valid' => true,
            'message' => 'Password meets security requirements'
        ];
    }

    /**
     * Check if password is common
     */
    private function isCommonPassword(string $password): bool
    {
        $commonPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123',
            'password123', 'admin', 'letmein', 'welcome', 'monkey',
            '1234567890', 'password1', 'qwerty123', 'dragon', 'master',
            'hello', 'freedom', 'whatever', 'qazwsx', 'trustno1'
        ];

        return in_array(strtolower($password), $commonPasswords);
    }

    /**
     * Check for sequential characters
     */
    private function hasSequentialCharacters(string $password): bool
    {
        $sequences = [
            '0123456789',
            'abcdefghijklmnopqrstuvwxyz',
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'qwertyuiop',
            'asdfghjkl',
            'zxcvbnm'
        ];

        foreach ($sequences as $sequence) {
            for ($i = 0; $i <= strlen($sequence) - 3; $i++) {
                $substring = substr($sequence, $i, 3);
                if (stripos($password, $substring) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check for repeated characters
     */
    private function hasRepeatedCharacters(string $password): bool
    {
        // Check for 3 or more consecutive identical characters
        return preg_match('/(.)\1{2,}/', $password);
    }

    /**
     * Hash password with Argon2id
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
     * Check if password needs rehashing
     */
    public function needsRehash(string $hash): bool
    {
        return Hash::needsRehash($hash);
    }

    /**
     * Generate secure random password
     */
    public function generateSecurePassword(int $length = 12): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        $all = $uppercase . $lowercase . $numbers . $symbols;
        $password = '';

        // Ensure at least one character from each category
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];

        // Fill the rest randomly
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        // Shuffle the password
        return str_shuffle($password);
    }

    /**
     * Calculate password strength score
     */
    public function calculateStrength(string $password): array
    {
        $score = 0;
        $maxScore = 100;

        // Length score (0-30 points)
        $length = strlen($password);
        if ($length >= 8) $score += 10;
        if ($length >= 12) $score += 10;
        if ($length >= 16) $score += 10;

        // Character variety score (0-40 points)
        if (preg_match('/[a-z]/', $password)) $score += 10;
        if (preg_match('/[A-Z]/', $password)) $score += 10;
        if (preg_match('/[0-9]/', $password)) $score += 10;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 10;

        // Complexity score (0-30 points)
        if (!$this->isCommonPassword($password)) $score += 15;
        if (!$this->hasSequentialCharacters($password)) $score += 10;
        if (!$this->hasRepeatedCharacters($password)) $score += 5;

        $percentage = ($score / $maxScore) * 100;

        $strength = match (true) {
            $percentage >= 80 => 'strong',
            $percentage >= 60 => 'good',
            $percentage >= 40 => 'fair',
            default => 'weak'
        };

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'strength' => $strength
        ];
    }
}