<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AiAssistService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistServiceTest extends TestCase
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.anthropic_api_key' => 'test-key', 'ai.model' => 'claude-haiku-4-5-20251001']);
    }

    public function test_returns_suggestion_on_valid_tool_use_response(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [
                    [
                        'type' => 'tool_use',
                        'name' => 'suggest_lead_conversion',
                        'input' => [
                            'service_category' => 'interior',
                            'scope_summary' => 'Thiết kế nội thất căn hộ 2 phòng ngủ.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = (new AiAssistService())->suggestLeadConversion('Can can ho 2 phong ngu can thiet ke noi that');

        $this->assertSame([
            'service_category' => 'interior',
            'scope_summary' => 'Thiết kế nội thất căn hộ 2 phòng ngủ.',
        ], $result);
    }

    public function test_sends_only_project_description_as_message_content(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_lead_conversion',
                    'input' => ['service_category' => 'architecture', 'scope_summary' => 'Tóm tắt.'],
                ]],
            ], 200),
        ]);

        (new AiAssistService())->suggestLeadConversion('Nha pho 5x20, 3 tang, khu Binh Chanh');

        Http::assertSent(function ($request) {
            $body = $request->data();
            $messages = $body['messages'];

            $this->assertCount(1, $messages);
            $this->assertSame('user', $messages[0]['role']);
            $this->assertSame('Nha pho 5x20, 3 tang, khu Binh Chanh', $messages[0]['content']);

            // No other Lead/Account/tenant field anywhere in the request body.
            $encoded = json_encode($body);
            $this->assertStringNotContainsString('tenant', strtolower((string) $encoded));
            $this->assertStringNotContainsString('contact_hint', (string) $encoded);
            $this->assertStringNotContainsString('account', strtolower((string) $encoded));

            return true;
        });
    }

    public function test_returns_null_when_api_key_missing(): void
    {
        config(['ai.anthropic_api_key' => null]);

        $result = (new AiAssistService())->suggestLeadConversion('Nha pho 5x20');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_returns_null_when_project_description_blank(): void
    {
        $result = (new AiAssistService())->suggestLeadConversion('   ');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_returns_null_on_non_successful_response(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => 'rate limited'], 429)]);

        $this->assertNull((new AiAssistService())->suggestLeadConversion('Nha pho 5x20'));
    }

    public function test_returns_null_when_connection_fails(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $this->assertNull((new AiAssistService())->suggestLeadConversion('Nha pho 5x20'));
    }

    public function test_returns_null_when_no_tool_use_block_present(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [['type' => 'text', 'text' => 'I cannot help with that.']],
            ], 200),
        ]);

        $this->assertNull((new AiAssistService())->suggestLeadConversion('Nha pho 5x20'));
    }

    public function test_returns_null_when_service_category_not_in_enum(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_lead_conversion',
                    'input' => ['service_category' => 'not_a_real_category', 'scope_summary' => 'Tóm tắt.'],
                ]],
            ], 200),
        ]);

        $this->assertNull((new AiAssistService())->suggestLeadConversion('Nha pho 5x20'));
    }

    public function test_returns_null_when_scope_summary_empty(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_lead_conversion',
                    'input' => ['service_category' => 'architecture', 'scope_summary' => ''],
                ]],
            ], 200),
        ]);

        $this->assertNull((new AiAssistService())->suggestLeadConversion('Nha pho 5x20'));
    }

    public function test_uses_configured_model(): void
    {
        config(['ai.model' => 'claude-haiku-4-5-20251001']);

        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_lead_conversion',
                    'input' => ['service_category' => 'architecture', 'scope_summary' => 'Tóm tắt.'],
                ]],
            ], 200),
        ]);

        (new AiAssistService())->suggestLeadConversion('Nha pho 5x20');

        Http::assertSent(function ($request) {
            $this->assertSame('claude-haiku-4-5-20251001', $request->data()['model']);

            return true;
        });
    }

    public function test_suggests_design_item_description_with_service_category(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_design_item_description',
                    'input' => ['description' => 'Bản vẽ phối cảnh mặt tiền theo phong cách hiện đại.'],
                ]],
            ], 200),
        ]);

        $result = (new AiAssistService())->suggestDesignItemDescription('concept', 'architecture');

        $this->assertSame(['description' => 'Bản vẽ phối cảnh mặt tiền theo phong cách hiện đại.'], $result);
    }

    public function test_suggests_design_item_description_without_service_category(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_design_item_description',
                    'input' => ['description' => 'Mô tả kỹ thuật cho hạng mục.'],
                ]],
            ], 200),
        ]);

        $result = (new AiAssistService())->suggestDesignItemDescription('technical', null);

        $this->assertSame(['description' => 'Mô tả kỹ thuật cho hạng mục.'], $result);
    }

    public function test_design_item_suggestion_sends_only_item_type_and_service_category(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_design_item_description',
                    'input' => ['description' => 'Tóm tắt.'],
                ]],
            ], 200),
        ]);

        (new AiAssistService())->suggestDesignItemDescription('mep', 'construction');

        Http::assertSent(function ($request) {
            $content = $request->data()['messages'][0]['content'];
            $this->assertStringContainsString('mep', $content);
            $this->assertStringContainsString('construction', $content);

            $encoded = json_encode($request->data());
            $this->assertStringNotContainsString('project_id', (string) $encoded);
            $this->assertStringNotContainsString('tenant', strtolower((string) $encoded));

            return true;
        });
    }

    public function test_returns_null_when_design_item_api_key_missing(): void
    {
        config(['ai.anthropic_api_key' => null]);

        $result = (new AiAssistService())->suggestDesignItemDescription('concept', 'architecture');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_returns_null_when_item_type_blank(): void
    {
        $result = (new AiAssistService())->suggestDesignItemDescription('   ', 'architecture');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_returns_null_when_design_item_response_not_successful(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => 'rate limited'], 429)]);

        $this->assertNull((new AiAssistService())->suggestDesignItemDescription('concept', 'architecture'));
    }

    public function test_returns_null_when_design_item_description_empty(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_design_item_description',
                    'input' => ['description' => ''],
                ]],
            ], 200),
        ]);

        $this->assertNull((new AiAssistService())->suggestDesignItemDescription('concept', 'architecture'));
    }

    public function test_returns_null_when_design_item_connection_fails(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $this->assertNull((new AiAssistService())->suggestDesignItemDescription('concept', 'architecture'));
    }
}
