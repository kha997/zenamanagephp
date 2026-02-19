<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\ErrorEnvelopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $user = $request->user();

        if (!$user) {
            return ErrorEnvelopeService::authenticationError(
                'User not authenticated',
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        if (!$tenantId) {
            return ErrorEnvelopeService::error(
                'TENANT_REQUIRED',
                'Tenant context missing',
                [],
                400,
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        $this->dropTenantIdFromPayload($request);

        $payload = $request->validate([
            'tenant_id' => 'prohibited',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|string',
            'priority' => 'required|string',
        ]);

        $ticket = SupportTicket::create([
            'tenant_id' => $tenantId,
            'ticket_number' => SupportTicket::generateTicketNumber($tenantId),
            'user_id' => $user->id,
            'subject' => $payload['subject'],
            'description' => $payload['description'],
            'category' => $payload['category'],
            'priority' => $payload['priority'],
            'status' => 'open',
        ]);

        return response()->json($ticket, 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        if ($ticket->tenant_id !== $this->resolveTenantId($request)) {
            return ErrorEnvelopeService::notFoundError('Support ticket', ErrorEnvelopeService::getCurrentRequestId());
        }

        return response()->json($ticket->load('messages'));
    }

    public function addMessage(Request $request, SupportTicket $ticket): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        if ($ticket->tenant_id !== $tenantId) {
            return ErrorEnvelopeService::notFoundError('Support ticket', ErrorEnvelopeService::getCurrentRequestId());
        }

        $user = $request->user();

        if (!$user) {
            return ErrorEnvelopeService::authenticationError(
                'User not authenticated',
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        $payload = $request->validate([
            'message' => 'required|string',
            'is_internal' => 'sometimes|boolean',
        ]);

        $message = SupportTicketMessage::create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $payload['message'],
            'is_internal' => $payload['is_internal'] ?? false,
        ]);

        return response()->json($message, 201);
    }

    public function update(Request $request, SupportTicket $ticket): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        if ($ticket->tenant_id !== $tenantId) {
            return ErrorEnvelopeService::notFoundError('Support ticket', ErrorEnvelopeService::getCurrentRequestId());
        }

        $payload = $request->validate([
            'status' => 'required|string',
            'assigned_to' => 'nullable|string',
        ]);

        if ($payload['assigned_to']) {
            $assignedTo = User::where('id', $payload['assigned_to'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$assignedTo) {
                return ErrorEnvelopeService::validationError([
                    'assigned_to' => ['Assigned user not found in tenant']
                ], ErrorEnvelopeService::getCurrentRequestId());
            }
        }

        $ticket->update([
            'status' => $payload['status'],
            'assigned_to' => $payload['assigned_to'] ?? null,
        ]);

        return response()->json($ticket);
    }

    private function dropTenantIdFromPayload(Request $request): void
    {
        $request->request->remove('tenant_id');
        $request->json()->remove('tenant_id');
    }

    private function resolveTenantId(Request $request): ?string
    {
        $tenantId = $request->attributes->get('tenant_id');

        if ($tenantId) {
            return $tenantId;
        }

        if (app()->bound('current_tenant_id')) {
            return app('current_tenant_id');
        }

        return null;
    }
}
