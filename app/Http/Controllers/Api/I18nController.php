<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\I18nService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class I18nController extends Controller
{
    protected I18nService $i18nService;

    public function __construct(I18nService $i18nService)
    {
        $this->i18nService = $i18nService;
    }

    /**
     * Get i18n configuration
     */
    public function getConfiguration(): JsonResponse
    {
        try {
            $config = $this->i18nService->getConfiguration();
            
            return response()->json([
                'success' => true,
                'data' => $config
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid time format'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to get i18n configuration', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to get i18n configuration'
            ], 500);
        }
    }

    /**
     * Set language
     */
    public function setLanguage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'language' => 'required|string|in:' . implode(',', array_keys($this->i18nService->getSupportedLanguages()))
            ]);

            $success = $this->i18nService->setLanguage($request->input('language'));
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Language updated successfully',
                    'data' => [
                        'language' => $this->i18nService->getCurrentLanguage()
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid language'
                ], 400);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid language'
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid time format'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to set language', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to set language'
            ], 500);
        }
    }

    /**
     * Set timezone
     */
    public function setTimezone(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'timezone' => 'required|string|in:' . implode(',', array_keys($this->i18nService->getSupportedTimezones()))
            ]);

            $success = $this->i18nService->setTimezone($request->input('timezone'));
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Timezone updated successfully',
                    'data' => [
                        'timezone' => $this->i18nService->getCurrentTimezone()
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid timezone'
                ], 400);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid timezone'
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid time format'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to set timezone', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to set timezone'
            ], 500);
        }
    }

    /**
     * Format date
     */
    public function formatDate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'format' => 'nullable|string'
            ]);

            $date = new \DateTime($request->input('date'));
            $format = $request->input('format');
            
            $formatted = $this->i18nService->formatDate($date, $format);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'original' => $request->input('date'),
                    'formatted' => $formatted
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid date format'
            ], 422);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid time format'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to format date', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to format date'
            ], 500);
        }
    }

    /**
     * Format time
     */
    public function formatTime(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'time' => 'required|date',
                'format' => 'nullable|string'
            ]);

            $time = new \DateTime($request->input('time'));
            $format = $request->input('format');
            
            $formatted = $this->i18nService->formatTime($time, $format);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'original' => $request->input('time'),
                    'formatted' => $formatted
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid time format'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to format time', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to format time'
            ], 500);
        }
    }

    /**
     * Format datetime
     */
    public function formatDateTime(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'datetime' => 'required|date',
                'format' => 'nullable|string'
            ]);

            $datetime = new \DateTime($request->input('datetime'));
            $format = $request->input('format');
            
            $formatted = $this->i18nService->formatDateTime($datetime, $format);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'original' => $request->input('datetime'),
                    'formatted' => $formatted
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid datetime format'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to format datetime', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to format datetime'
            ], 500);
        }
    }

    /**
     * Format number
     */
    public function formatNumber(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'number' => 'required|numeric',
                'decimals' => 'nullable|integer|min:0|max:10'
            ]);

            $number = (float) $request->input('number');
            $decimals = $request->input('decimals', 2);
            
            $formatted = $this->i18nService->formatNumber($number, $decimals);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'original' => $number,
                    'formatted' => $formatted
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid number format'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to format number', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to format number'
            ], 500);
        }
    }

    /**
     * Format currency
     */
    public function formatCurrency(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'amount' => 'required|numeric',
                'currency' => 'nullable|string|in:' . implode(',', array_keys($this->i18nService->getSupportedCurrencies()))
            ]);

            $amount = (float) $request->input('amount');
            $currency = $request->input('currency');
            
            $formatted = $this->i18nService->formatCurrency($amount, $currency);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'original' => $amount,
                    'formatted' => $formatted,
                    'currency' => $currency ?: 'USD'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid currency format'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to format currency', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to format currency'
            ], 500);
        }
    }

    /**
     * Get current locale info
     */
    public function getCurrentLocale(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'language' => $this->i18nService->getCurrentLanguage(),
                    'timezone' => $this->i18nService->getCurrentTimezone(),
                    'currency' => $this->i18nService->getCurrentCurrency()
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid time format'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to get current locale', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to get current locale'
            ], 500);
        }
    }
}
