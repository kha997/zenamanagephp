@extends('layouts.dashboard')

@section('title', 'Invitations Management')
@section('page-title', 'Invitations Management')
@section('page-description', 'Manage user invitations and onboarding')
@section('user-initials', 'PM')
@section('user-name', 'Project Manager')
@section('current-route', 'invitations')

@php
$breadcrumb = [
    [
        'label' => 'Dashboard',
        'url' => '/dashboard',
        'icon' => 'fas fa-home'
    ],
    [
        'label' => 'Invitations Management',
        'url' => '/invitations'
    ]
];
$currentRoute = 'invitations';
@endphp

@section('content')
<div x-data="invitationManagement()">
    <!-- Header Actions -->
    <div class="dashboard-card p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-envelope text-blue-600 mr-2"></i>
                    User Invitations
                </h3>
                <p class="text-sm text-gray-600 mt-1">Manage invitations and track user onboarding</p>
            </div>
            <div class="flex space-x-3">
                <button 
                    @click="showBulkInvite = true"
                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center"
                >
                    <i class="fas fa-upload mr-2"></i>
                    Bulk Invite
                </button>
                <a 
                    href="/invitations/create"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                >
                    <i class="fas fa-plus mr-2"></i>
                    Send Invitation
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="dashboard-card p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-blue-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Pending</p>
                    <p class="text-2xl font-semibold text-gray-900" x-text="stats.pending"></p>
                </div>
            </div>
        </div>

        <div class="dashboard-card p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check text-green-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Accepted</p>
                    <p class="text-2xl font-semibold text-gray-900" x-text="stats.accepted"></p>
                </div>
            </div>
        </div>

        <div class="dashboard-card p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-times text-red-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Expired</p>
                    <p class="text-2xl font-semibold text-gray-900" x-text="stats.expired"></p>
                </div>
            </div>
        </div>

        <div class="dashboard-card p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-ban text-yellow-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Cancelled</p>
                    <p class="text-2xl font-semibold text-gray-900" x-text="stats.cancelled"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Invitations List -->
    <div class="dashboard-card p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Recent Invitations</h3>
            <div class="flex space-x-2">
                <select x-model="statusFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="accepted">Accepted</option>
                    <option value="expired">Expired</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <input 
                    type="text" 
                    x-model="searchQuery"
                    placeholder="Search by email or name..."
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm w-64"
                >
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Invitee
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Role
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Project
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Expires
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($invitations as $invitation)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                        <span class="text-sm font-medium text-gray-700">
                                            {{ strtoupper(substr($invitation->email, 0, 2)) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $invitation->full_name ?: $invitation->email }}
                                    </div>
                                    <div class="text-sm text-gray-500">{{ $invitation->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                {{ $invitation->getRoleDisplayName() }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $invitation->getProjectName() }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($invitation->status === 'pending')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                    Pending
                                </span>
                            @elseif($invitation->status === 'accepted')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Accepted
                                </span>
                            @elseif($invitation->status === 'expired')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    Expired
                                </span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                    Cancelled
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $invitation->expires_at->format('M d, Y') }}
                            @if($invitation->status === 'pending')
                                <div class="text-xs text-gray-500">
                                    {{ $invitation->days_until_expiry }} days left
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                @if($invitation->status === 'pending')
                                    <button 
                                        @click="resendInvitation({{ $invitation->id }})"
                                        class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                                        title="Resend Invitation"
                                    >
                                        <i class="fas fa-redo"></i>
                                    </button>
                                    <button 
                                        @click="cancelInvitation({{ $invitation->id }})"
                                        class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                                        title="Cancel Invitation"
                                    >
                                        <i class="fas fa-ban"></i>
                                    </button>
                                @endif
                                <button 
                                    @click="viewInvitation({{ $invitation->id }})"
                                    class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                                    title="View Details"
                                >
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $invitations->links() }}
        </div>
    </div>
</div>

<script>
function invitationManagement() {
    return {
        searchQuery: '',
        statusFilter: '',
        showBulkInvite: false,
        stats: {
            pending: {{ $invitations->where('status', 'pending')->count() }},
            accepted: {{ $invitations->where('status', 'accepted')->count() }},
            expired: {{ $invitations->where('status', 'expired')->count() }},
            cancelled: {{ $invitations->where('status', 'cancelled')->count() }}
        },

        resendInvitation(invitationId) {
            if (confirm('Resend this invitation?')) {
                // Implement resend logic
                this.showNotification('Invitation resent successfully!', 'success');
            }
        },

        cancelInvitation(invitationId) {
            if (confirm('Cancel this invitation? This action cannot be undone.')) {
                // Implement cancel logic
                this.showNotification('Invitation cancelled successfully!', 'success');
            }
        },

        viewInvitation(invitationId) {
            // Implement view logic
            window.open(`/invitations/${invitationId}`, '_blank');
        },

        showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg text-white shadow-lg transition-all duration-300 ${
                type === 'success' ? 'bg-green-600' : 
                type === 'error' ? 'bg-red-600' : 
                type === 'warning' ? 'bg-yellow-600' :
                'bg-blue-600'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    }
}
</script>
@endsection
