<?php

namespace App\Http\Controllers;

use App\Services\AccessibilityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AccessibilityController extends Controller
{
    protected AccessibilityService $accessibilityService;
    
    public function __construct(AccessibilityService $accessibilityService)
    {
        $this->accessibilityService = $accessibilityService;
    }
    
    /**
     * Get user accessibility preferences.
     *
     * @return JsonResponse
     */
    public function preferences(): JsonResponse
    {
        try {
            $preferences = $this->accessibilityService->getUserPreferences();
            
            return response()->json([
                'success' => true,
                'data' => $preferences,
                'message' => 'Accessibility preferences retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve accessibility preferences',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Save user accessibility preferences.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function savePreferences(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'high_contrast' => 'boolean',
                'reduced_motion' => 'boolean',
                'large_text' => 'boolean',
                'keyboard_navigation' => 'boolean',
                'screen_reader_optimized' => 'boolean',
                'focus_indicators' => 'boolean',
                'skip_links' => 'boolean',
                'aria_labels' => 'boolean',
            ]);
            
            $saved = $this->accessibilityService->saveUserPreferences($validated);
            
            if ($saved) {
                return response()->json([
                    'success' => true,
                    'data' => $validated,
                    'message' => 'Accessibility preferences saved successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to save accessibility preferences'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to save accessibility preferences',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get accessibility compliance report.
     *
     * @return JsonResponse
     */
    public function complianceReport(): JsonResponse
    {
        try {
            $report = $this->accessibilityService->getComplianceReport();
            
            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Compliance report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate compliance report',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Audit a specific page for accessibility issues.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function auditPage(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page_url' => 'required|url'
            ]);
            
            $audit = $this->accessibilityService->auditPage($validated['page_url']);
            
            return response()->json([
                'success' => true,
                'data' => $audit,
                'message' => 'Page audit completed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to audit page',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get accessibility statistics.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->accessibilityService->getAccessibilityStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Accessibility statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve accessibility statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Check color contrast compliance.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkColorContrast(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'foreground_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'background_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'level' => 'string|in:AA,AAA'
            ]);
            
            $level = $validated['level'] ?? 'AA';
            $result = $this->accessibilityService->checkColorContrast(
                $validated['foreground_color'],
                $validated['background_color'],
                $level
            );
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Color contrast check completed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to check color contrast',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generate accessibility report for export.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateReport(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'format' => 'string|in:json,csv,pdf'
            ]);
            
            $format = $validated['format'] ?? 'json';
            $report = $this->accessibilityService->generateAccessibilityReport($format);
            
            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Accessibility report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate accessibility report',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Reset user accessibility preferences to defaults.
     *
     * @return JsonResponse
     */
    public function resetPreferences(): JsonResponse
    {
        try {
            $defaultPreferences = [
                'high_contrast' => false,
                'reduced_motion' => false,
                'large_text' => false,
                'keyboard_navigation' => true,
                'screen_reader_optimized' => false,
                'focus_indicators' => true,
                'skip_links' => true,
                'aria_labels' => true,
            ];
            
            $saved = $this->accessibilityService->saveUserPreferences($defaultPreferences);
            
            if ($saved) {
                return response()->json([
                    'success' => true,
                    'data' => $defaultPreferences,
                    'message' => 'Accessibility preferences reset to defaults successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to reset accessibility preferences'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to reset accessibility preferences',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get accessibility help and documentation.
     *
     * @return JsonResponse
     */
    public function help(): JsonResponse
    {
        try {
            $help = [
                'keyboard_shortcuts' => [
                    'Tab' => 'Navigate forward through interactive elements',
                    'Shift + Tab' => 'Navigate backward through interactive elements',
                    'Enter' => 'Activate buttons and links',
                    'Space' => 'Activate buttons and checkboxes',
                    'Escape' => 'Close modals and menus',
                    'Alt + S' => 'Focus search input',
                    'Alt + N' => 'Focus navigation menu',
                    'Alt + M' => 'Open modal dialog',
                    'Alt + H' => 'Show help information'
                ],
                'screen_reader_commands' => [
                    'H' => 'Navigate to next heading',
                    'Shift + H' => 'Navigate to previous heading',
                    'L' => 'Navigate to next list',
                    'Shift + L' => 'Navigate to previous list',
                    'F' => 'Navigate to next form field',
                    'Shift + F' => 'Navigate to previous form field',
                    'B' => 'Navigate to next button',
                    'Shift + B' => 'Navigate to previous button'
                ],
                'accessibility_features' => [
                    'Skip Links' => 'Use Tab to access skip links for quick navigation',
                    'Focus Indicators' => 'Visible focus indicators show current element',
                    'ARIA Labels' => 'Screen readers announce element purposes',
                    'Live Regions' => 'Dynamic content updates are announced',
                    'High Contrast' => 'Enhanced contrast mode for better visibility',
                    'Reduced Motion' => 'Minimizes animations for motion sensitivity'
                ],
                'contact_support' => [
                    'email' => 'accessibility@zenamanage.com',
                    'phone' => '+1-800-ACCESS-HELP',
                    'hours' => 'Monday-Friday, 9AM-5PM EST'
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $help,
                'message' => 'Accessibility help retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve accessibility help',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
