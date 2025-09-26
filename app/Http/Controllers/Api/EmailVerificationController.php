<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\nService;
use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Email Verification Controller
 * 
 * Handles email verification and email change functionality
 */
class EmailVerificationController extends Controller
{
    private EmailVerificationService $emailVerificationService;

    public function __construct(EmailVerificationService $emailVerificationService)
    {
        $this->emailVerificationService = $emailVerificationService;
    }

    /**
     * Send verification email
     * POST /api/v1/email/send-verification
     */
    public function sendVerification(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required'
            ], 401);
        }

        if ($user->email_verified) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email is already verified'
            ], 400);
        }

        $success = $this->emailVerificationService->sendVerificationEmail($user);

        if ($success) {
            return response()->json([
                'status' => 'success',
                'message' => 'Verification email sent successfully'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to send verification email'
        ], 500);
    }

    /**
     * Verify email with token
     * POST /api/v1/email/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->emailVerificationService->verifyEmail($request->input('token'));

        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => [
                    'user' => $result['user']
                ]
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message']
        ], 400);
    }

    /**
     * Resend verification email
     * POST /api/v1/email/resend-verification
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required'
            ], 401);
        }

        $success = $this->emailVerificationService->resendVerificationEmail($user);

        if ($success) {
            return response()->json([
                'status' => 'success',
                'message' => 'Verification email resent successfully'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to resend verification email. You may have reached the rate limit.'
        ], 429);
    }

    /**
     * Initiate email change
     * POST /api/v1/email/change
     */
    public function initiateChange(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'new_email' => 'required|email|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->emailVerificationService->initiateEmailChange(
            $user, 
            $request->input('new_email')
        );

        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'message' => $result['message']
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message']
        ], 400);
    }

    /**
     * Confirm email change
     * POST /api/v1/email/confirm-change
     */
    public function confirmChange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->emailVerificationService->confirmEmailChange($request->input('token'));

        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => [
                    'user' => $result['user']
                ]
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message']
        ], 400);
    }

    /**
     * Check verification status
     * GET /api/v1/email/status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required'
            ], 401);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'email_verified' => $user->email_verified,
                'email_verified_at' => $user->email_verified_at,
                'pending_email' => $user->pending_email,
                'can_login' => $this->emailVerificationService->canUserLogin($user)
            ]
        ]);
    }
}