@extends('layouts.auth')

@section('title', 'Accept Invitation')
@section('page-title', 'Accept Team Invitation')
@section('page-description', 'Join your team using this invitation')

@section('content')
<div x-data="acceptInvitationPage()" class="min-h-screen flex items-center justify-center bg-gray-50 py-10 px-4">
    <div class="w-full max-w-lg space-y-6">
        <div class="bg-white rounded-lg shadow border p-6">
            <h2 class="text-2xl font-semibold text-gray-900">You are invited</h2>
            <p class="mt-2 text-sm text-gray-600">Review invitation details and accept to join the team.</p>

            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex justify-between border-b pb-2">
                    <dt class="text-gray-500">Email</dt>
                    <dd class="text-gray-900">{{ $invitation->email }}</dd>
                </div>
                <div class="flex justify-between border-b pb-2">
                    <dt class="text-gray-500">Team</dt>
                    <dd class="text-gray-900">{{ $teamName ?? 'Team' }}</dd>
                </div>
                <div class="flex justify-between border-b pb-2">
                    <dt class="text-gray-500">Role</dt>
                    <dd class="text-gray-900">{{ $invitation->role }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Expires</dt>
                    <dd class="text-gray-900">{{ optional($invitation->expires_at)->toDateTimeString() }}</dd>
                </div>
            </dl>

            @if(!empty($invitation->message))
                <div class="mt-4 rounded-md bg-blue-50 p-3 text-sm text-blue-900">
                    {{ $invitation->message }}
                </div>
            @endif
        </div>

        <div class="bg-white rounded-lg shadow border p-6">
            <template x-if="state === 'idle'">
                <button
                    type="button"
                    @click="accept()"
                    class="w-full rounded-md bg-blue-600 px-4 py-2.5 text-white hover:bg-blue-700"
                    :disabled="submitting"
                >
                    Accept Invitation
                </button>
            </template>

            <template x-if="state === 'success'">
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-800" x-text="message"></div>
            </template>

            <template x-if="state === 'error'">
                <div class="space-y-3">
                    <div class="rounded-md bg-red-50 p-3 text-sm text-red-800" x-text="message"></div>
                    <button
                        type="button"
                        @click="accept()"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        :disabled="submitting"
                    >
                        Retry
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function acceptInvitationPage() {
    return {
        submitting: false,
        state: 'idle',
        message: '',
        token: @json($token),
        teamId: @json($teamId),
        tenantId: @json((string) auth()->user()?->tenant_id),

        async accept() {
            if (!this.teamId) {
                this.state = 'error';
                this.message = 'Invitation is missing team context.';
                return;
            }

            this.submitting = true;
            this.state = 'idle';
            this.message = '';

            try {
                const token = localStorage.getItem('auth_token') || '';
                const headers = {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Tenant-ID': this.tenantId
                };

                if (token) {
                    headers['Authorization'] = `Bearer ${token}`;
                }

                const response = await fetch(`/api/teams/${encodeURIComponent(this.teamId)}/invitations/${encodeURIComponent(this.token)}/accept`, {
                    method: 'POST',
                    headers
                });

                const result = await response.json().catch(() => ({}));
                if (!response.ok || result.success === false) {
                    this.state = 'error';
                    this.message = result?.error?.message || result?.message || 'Failed to accept invitation.';
                    return;
                }

                this.state = 'success';
                this.message = 'Invitation accepted successfully.';
                setTimeout(() => {
                    window.location.href = '/app/team';
                }, 1200);
            } catch (error) {
                this.state = 'error';
                this.message = 'Unable to accept invitation right now.';
            } finally {
                this.submitting = false;
            }
        }
    };
}
</script>
@endsection
