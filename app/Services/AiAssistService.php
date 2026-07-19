<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Opportunity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AI-assisted lead-conversion suggestion (spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md, Phase 7, Use Case 1).
 * Data minimization: the only data ever sent to Anthropic is the raw Lead.project_description string —
 * no contact info, account data, or tenant identifiers. Never trust the response blindly: service_category
 * is re-validated against Opportunity::VALID_SERVICE_CATEGORIES before being returned.
 */
class AiAssistService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const TOOL_NAME = 'suggest_lead_conversion';
    private const DESIGN_ITEM_TOOL_NAME = 'suggest_design_item_description';
    private const SUMMARY_TOOL_NAME = 'summarize_opportunity';

    /**
     * @return array{service_category: string, scope_summary: string}|null
     */
    public function suggestLeadConversion(string $projectDescription): ?array
    {
        $apiKey = (string) config('ai.anthropic_api_key');

        if ($apiKey === '' || trim($projectDescription) === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post(self::ENDPOINT, [
                    'model' => (string) config('ai.model', 'claude-haiku-4-5-20251001'),
                    'max_tokens' => 512,
                    'tools' => [[
                        'name' => self::TOOL_NAME,
                        'description' => 'Suggest a service category and a short scope summary for a CRM opportunity converted from a lead.',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'service_category' => [
                                    'type' => 'string',
                                    'enum' => Opportunity::VALID_SERVICE_CATEGORIES,
                                ],
                                'scope_summary' => [
                                    'type' => 'string',
                                    'description' => 'A short (1-2 sentence) Vietnamese scope summary suitable for a CRM opportunity record.',
                                ],
                            ],
                            'required' => ['service_category', 'scope_summary'],
                        ],
                    ]],
                    'tool_choice' => ['type' => 'tool', 'name' => self::TOOL_NAME],
                    'messages' => [[
                        'role' => 'user',
                        'content' => $projectDescription,
                    ]],
                ]);

            if (!$response->successful()) {
                Log::warning('ai_assist.lead_suggestion_failed', ['status' => $response->status()]);

                return null;
            }

            return $this->extractSuggestion($response->json('content', []));
        } catch (Throwable $e) {
            Log::error('ai_assist.lead_suggestion_exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{description: string}|null
     */
    public function suggestDesignItemDescription(string $itemType, ?string $serviceCategory): ?array
    {
        $apiKey = (string) config('ai.anthropic_api_key');

        if ($apiKey === '' || trim($itemType) === '') {
            return null;
        }

        $context = ($serviceCategory !== null && trim($serviceCategory) !== '')
            ? "Design item type: {$itemType}. Project service category: {$serviceCategory}."
            : "Design item type: {$itemType}.";

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post(self::ENDPOINT, [
                    'model' => (string) config('ai.model', 'claude-haiku-4-5-20251001'),
                    'max_tokens' => 512,
                    'tools' => [[
                        'name' => self::DESIGN_ITEM_TOOL_NAME,
                        'description' => 'Suggest a short Vietnamese description for a design work item, given its type and, when known, the project service category.',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'description' => [
                                    'type' => 'string',
                                    'description' => 'A short (1-2 sentence) Vietnamese description of the design work item, suitable to save on the record.',
                                ],
                            ],
                            'required' => ['description'],
                        ],
                    ]],
                    'tool_choice' => ['type' => 'tool', 'name' => self::DESIGN_ITEM_TOOL_NAME],
                    'messages' => [[
                        'role' => 'user',
                        'content' => $context,
                    ]],
                ]);

            if (!$response->successful()) {
                Log::warning('ai_assist.design_item_suggestion_failed', ['status' => $response->status()]);

                return null;
            }

            $input = $this->extractToolUseInput($response->json('content', []), self::DESIGN_ITEM_TOOL_NAME);

            if ($input === null) {
                return null;
            }

            $description = trim((string) ($input['description'] ?? ''));

            if ($description === '') {
                Log::warning('ai_assist.design_item_suggestion_invalid_output');

                return null;
            }

            return ['description' => $description];
        } catch (Throwable $e) {
            Log::error('ai_assist.design_item_suggestion_exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Summarize an anonymized CRM opportunity context for pre-meeting preparation.
     * The caller (CrmPageController::buildOpportunitySummaryContext) is responsible for
     * anonymization — this method sends $context verbatim as JSON and must only ever
     * receive already-whitelisted data.
     *
     * @param array<string, mixed> $context
     * @return array{summary: string}|null
     */
    public function summarizeOpportunity(array $context): ?array
    {
        $apiKey = (string) config('ai.anthropic_api_key');

        if ($apiKey === '' || $context === []) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post(self::ENDPOINT, [
                    'model' => (string) config('ai.model', 'claude-haiku-4-5-20251001'),
                    'max_tokens' => 1024,
                    'system' => 'Bạn tóm tắt một cơ hội bán hàng (CRM) cho người sale chuẩn bị gặp khách. '
                        . 'Viết tiếng Việt, 5-7 gạch đầu dòng, theo khung: tình trạng hiện tại → lịch sử tương tác → tình trạng báo giá → điểm cần lưu ý trước cuộc gặp. '
                        . 'CHỈ dùng dữ kiện được cung cấp trong JSON. KHÔNG suy diễn hay bịa số liệu. '
                        . 'Dữ kiện nào thiếu thì ghi "chưa có thông tin".',
                    'tools' => [[
                        'name' => self::SUMMARY_TOOL_NAME,
                        'description' => 'Return the Vietnamese pre-meeting summary of the CRM opportunity.',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'summary' => [
                                    'type' => 'string',
                                    'description' => '5-7 Vietnamese bullet lines summarizing the opportunity.',
                                ],
                            ],
                            'required' => ['summary'],
                        ],
                    ]],
                    'tool_choice' => ['type' => 'tool', 'name' => self::SUMMARY_TOOL_NAME],
                    'messages' => [[
                        'role' => 'user',
                        'content' => (string) json_encode($context, JSON_UNESCAPED_UNICODE),
                    ]],
                ]);

            if (!$response->successful()) {
                Log::warning('ai_assist.opportunity_summary_failed', ['status' => $response->status()]);

                return null;
            }

            $input = $this->extractToolUseInput($response->json('content', []), self::SUMMARY_TOOL_NAME);

            if ($input === null) {
                return null;
            }

            $summary = trim((string) ($input['summary'] ?? ''));

            if ($summary === '') {
                Log::warning('ai_assist.opportunity_summary_invalid_output');

                return null;
            }

            return ['summary' => $summary];
        } catch (Throwable $e) {
            Log::error('ai_assist.opportunity_summary_exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param array<int, mixed> $contentBlocks
     * @return array{service_category: string, scope_summary: string}|null
     */
    private function extractSuggestion(array $contentBlocks): ?array
    {
        $input = $this->extractToolUseInput($contentBlocks, self::TOOL_NAME);

        if ($input === null) {
            return null;
        }

        $category = (string) ($input['service_category'] ?? '');
        $summary = trim((string) ($input['scope_summary'] ?? ''));

        if (!in_array($category, Opportunity::VALID_SERVICE_CATEGORIES, true) || $summary === '') {
            Log::warning('ai_assist.lead_suggestion_invalid_output', ['service_category' => $category]);

            return null;
        }

        return ['service_category' => $category, 'scope_summary' => $summary];
    }

    /**
     * @param array<int, mixed> $contentBlocks
     * @return array<string, mixed>|null
     */
    private function extractToolUseInput(array $contentBlocks, string $toolName): ?array
    {
        foreach ($contentBlocks as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === $toolName) {
                $input = $block['input'] ?? null;

                return is_array($input) ? $input : null;
            }
        }

        return null;
    }
}
