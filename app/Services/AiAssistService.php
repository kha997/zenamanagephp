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
     * @param array<int, mixed> $contentBlocks
     * @return array{service_category: string, scope_summary: string}|null
     */
    private function extractSuggestion(array $contentBlocks): ?array
    {
        $toolUse = null;
        foreach ($contentBlocks as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'tool_use') {
                $toolUse = $block;
                break;
            }
        }

        if ($toolUse === null || !isset($toolUse['input']) || !is_array($toolUse['input'])) {
            return null;
        }

        $category = (string) ($toolUse['input']['service_category'] ?? '');
        $summary = trim((string) ($toolUse['input']['scope_summary'] ?? ''));

        if (!in_array($category, Opportunity::VALID_SERVICE_CATEGORIES, true) || $summary === '') {
            Log::warning('ai_assist.lead_suggestion_invalid_output', ['service_category' => $category]);

            return null;
        }

        return ['service_category' => $category, 'scope_summary' => $summary];
    }
}
