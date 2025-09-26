<?php

namespace Tests\Unit\Rules;

use Tests\TestCase;
use App\Rules\UniqueRule;
use App\Rules\ExistsRule;
use App\Rules\DateRule;
use App\Rules\FileRule;
use App\Rules\PasswordRule;
use App\Rules\EmailRule;
use App\Rules\PhoneRule;
use App\Rules\UrlRule;
use App\Rules\JsonRule;
use App\Rules\CustomRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ValidationRulesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unique_rule_passes_when_value_is_unique()
    {
        $rule = new UniqueRule('users', 'email');
        
        $this->assertTrue($rule->passes('email', 'unique@example.com'));
    }

    /** @test */
    public function unique_rule_fails_when_value_exists()
    {
        User::factory()->create(['email' => 'existing@example.com']);
        
        $rule = new UniqueRule('users', 'email');
        
        $this->assertFalse($rule->passes('email', 'existing@example.com'));
    }

    /** @test */
    public function unique_rule_ignores_specified_id()
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        
        $rule = new UniqueRule('users', 'email', $user->id);
        
        $this->assertTrue($rule->passes('email', 'test@example.com'));
    }

    /** @test */
    public function exists_rule_passes_when_value_exists()
    {
        $user = User::factory()->create();
        
        $rule = new ExistsRule('users', 'id');
        
        $this->assertTrue($rule->passes('user_id', $user->id));
    }

    /** @test */
    public function exists_rule_fails_when_value_does_not_exist()
    {
        $rule = new ExistsRule('users', 'id');
        
        $this->assertFalse($rule->passes('user_id', 99999));
    }

    /** @test */
    public function date_rule_passes_with_valid_date()
    {
        $rule = new DateRule('Y-m-d');
        
        $this->assertTrue($rule->passes('date', '2023-12-31'));
    }

    /** @test */
    public function date_rule_fails_with_invalid_date()
    {
        $rule = new DateRule('Y-m-d');
        
        $this->assertFalse($rule->passes('date', 'invalid-date'));
    }

    /** @test */
    public function date_rule_respects_min_date()
    {
        $rule = new DateRule('Y-m-d', now()->subDays(10), now()->addDays(10));
        
        $this->assertTrue($rule->passes('date', now()->format('Y-m-d')));
        $this->assertFalse($rule->passes('date', now()->subDays(20)->format('Y-m-d')));
    }

    /** @test */
    public function file_rule_passes_with_valid_file()
    {
        Storage::fake('local');
        
        $file = UploadedFile::fake()->create('test.pdf', 1000, 'application/pdf');
        
        $rule = new FileRule(5000, ['application/pdf'], ['pdf']);
        
        $this->assertTrue($rule->passes('file', $file));
    }

    /** @test */
    public function file_rule_fails_with_oversized_file()
    {
        Storage::fake('local');
        
        $file = UploadedFile::fake()->create('test.pdf', 10000, 'application/pdf');
        
        $rule = new FileRule(1000); // 1MB limit
        
        $this->assertFalse($rule->passes('file', $file));
    }

    /** @test */
    public function file_rule_fails_with_invalid_mime_type()
    {
        Storage::fake('local');
        
        $file = UploadedFile::fake()->create('test.txt', 1000, 'text/plain');
        
        $rule = new FileRule(5000, ['application/pdf'], ['pdf']);
        
        $this->assertFalse($rule->passes('file', $file));
    }

    /** @test */
    public function password_rule_passes_with_strong_password()
    {
        $rule = new PasswordRule(8, true, true, true, false);
        
        $this->assertTrue($rule->passes('password', 'Password123'));
    }

    /** @test */
    public function password_rule_fails_with_weak_password()
    {
        $rule = new PasswordRule(8, true, true, true, false);
        
        $this->assertFalse($rule->passes('password', 'weak'));
    }

    /** @test */
    public function password_rule_fails_without_uppercase()
    {
        $rule = new PasswordRule(8, true, true, true, false);
        
        $this->assertFalse($rule->passes('password', 'password123'));
    }

    /** @test */
    public function password_rule_fails_without_lowercase()
    {
        $rule = new PasswordRule(8, true, true, true, false);
        
        $this->assertFalse($rule->passes('password', 'PASSWORD123'));
    }

    /** @test */
    public function password_rule_fails_without_numbers()
    {
        $rule = new PasswordRule(8, true, true, true, false);
        
        $this->assertFalse($rule->passes('password', 'Password'));
    }

    /** @test */
    public function email_rule_passes_with_valid_email()
    {
        $rule = new EmailRule();
        
        $this->assertTrue($rule->passes('email', 'test@example.com'));
    }

    /** @test */
    public function email_rule_fails_with_invalid_email()
    {
        $rule = new EmailRule();
        
        $this->assertFalse($rule->passes('email', 'invalid-email'));
    }

    /** @test */
    public function email_rule_passes_with_multiple_emails()
    {
        $rule = new EmailRule(true, 5);
        
        $this->assertTrue($rule->passes('emails', 'test1@example.com,test2@example.com'));
    }

    /** @test */
    public function email_rule_fails_with_too_many_emails()
    {
        $rule = new EmailRule(true, 2);
        
        $this->assertFalse($rule->passes('emails', 'test1@example.com,test2@example.com,test3@example.com'));
    }

    /** @test */
    public function phone_rule_passes_with_international_format()
    {
        $rule = new PhoneRule('international');
        
        $this->assertTrue($rule->passes('phone', '+1234567890'));
    }

    /** @test */
    public function phone_rule_passes_with_national_format()
    {
        $rule = new PhoneRule('national');
        
        $this->assertTrue($rule->passes('phone', '1234567890'));
    }

    /** @test */
    public function phone_rule_fails_with_invalid_format()
    {
        $rule = new PhoneRule('international');
        
        $this->assertFalse($rule->passes('phone', '1234567890')); // Missing +
    }

    /** @test */
    public function phone_rule_respects_country_code()
    {
        $rule = new PhoneRule('international', '1');
        
        $this->assertTrue($rule->passes('phone', '+1234567890'));
        $this->assertFalse($rule->passes('phone', '+2234567890'));
    }

    /** @test */
    public function url_rule_passes_with_valid_url()
    {
        $rule = new UrlRule(['http', 'https']);
        
        $this->assertTrue($rule->passes('url', 'https://example.com'));
    }

    /** @test */
    public function url_rule_fails_with_invalid_url()
    {
        $rule = new UrlRule(['http', 'https']);
        
        $this->assertFalse($rule->passes('url', 'not-a-url'));
    }

    /** @test */
    public function url_rule_respects_protocol_restrictions()
    {
        $rule = new UrlRule(['https']);
        
        $this->assertTrue($rule->passes('url', 'https://example.com'));
        $this->assertFalse($rule->passes('url', 'http://example.com'));
    }

    /** @test */
    public function json_rule_passes_with_valid_json()
    {
        $rule = new JsonRule();
        
        $this->assertTrue($rule->passes('data', '{"key": "value"}'));
    }

    /** @test */
    public function json_rule_fails_with_invalid_json()
    {
        $rule = new JsonRule();
        
        $this->assertFalse($rule->passes('data', 'invalid-json'));
    }

    /** @test */
    public function json_rule_validates_required_fields()
    {
        $rule = new JsonRule([], ['name', 'email']);
        
        $this->assertTrue($rule->passes('data', '{"name": "John", "email": "john@example.com"}'));
        $this->assertFalse($rule->passes('data', '{"name": "John"}'));
    }

    /** @test */
    public function json_rule_validates_allowed_fields()
    {
        $rule = new JsonRule([], [], ['name', 'email']);
        
        $this->assertTrue($rule->passes('data', '{"name": "John", "email": "john@example.com"}'));
        $this->assertFalse($rule->passes('data', '{"name": "John", "invalid": "field"}'));
    }

    /** @test */
    public function json_rule_validates_field_schema()
    {
        $rule = new JsonRule(['name' => 'string|required', 'age' => 'integer|min:0']);
        
        $this->assertTrue($rule->passes('data', '{"name": "John", "age": 25}'));
        $this->assertFalse($rule->passes('data', '{"name": "John", "age": -5}'));
    }

    /** @test */
    public function custom_rule_executes_callback()
    {
        $rule = new CustomRule(
            function ($attribute, $value) {
                return $value === 'valid';
            },
            'The :attribute must be valid.'
        );
        
        $this->assertTrue($rule->passes('field', 'valid'));
        $this->assertFalse($rule->passes('field', 'invalid'));
    }

    /** @test */
    public function custom_rule_uses_custom_message()
    {
        $rule = new CustomRule(
            function ($attribute, $value) {
                return false;
            },
            'Custom error message.'
        );
        
        $this->assertEquals('Custom error message.', $rule->message());
    }

    /** @test */
    public function all_rules_handle_empty_values()
    {
        $rules = [
            new UniqueRule('users', 'email'),
            new ExistsRule('users', 'id'),
            new DateRule('Y-m-d'),
            new PasswordRule(),
            new EmailRule(),
            new PhoneRule(),
            new UrlRule(),
            new JsonRule(),
            new CustomRule(function () { return true; })
        ];
        
        foreach ($rules as $rule) {
            $this->assertTrue($rule->passes('field', ''), get_class($rule) . ' should pass with empty value');
        }
    }

    /** @test */
    public function all_rules_have_proper_error_messages()
    {
        $rules = [
            new UniqueRule('users', 'email'),
            new ExistsRule('users', 'id'),
            new DateRule('Y-m-d'),
            new PasswordRule(),
            new EmailRule(),
            new PhoneRule(),
            new UrlRule(),
            new JsonRule(),
            new CustomRule(function () { return false; })
        ];
        
        foreach ($rules as $rule) {
            $message = $rule->message();
            $this->assertIsString($message, get_class($rule) . ' should have string message');
            $this->assertNotEmpty($message, get_class($rule) . ' should have non-empty message');
        }
    }
}
