<div x-data="teamInvitationsPage()" x-init="init()" class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Team</h1>
            <p class="text-sm text-gray-600">Members and pending invitations.</p>
        </div>
        <div class="flex items-end gap-3">
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Team</label>
                <select x-model="selectedTeamId" @change="loadTeamData()" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Select team</option>
                    <template x-for="team in teams" :key="team.id">
                        <option :value="team.id" x-text="team.name"></option>
                    </template>
                </select>
            </div>
            <button
                type="button"
                class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 disabled:opacity-60"
                :disabled="!selectedTeamId"
                @click="openInviteModal = true"
            >
                Invite
            </button>
        </div>
    </div>

    <template x-if="errorMessage">
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="errorMessage"></div>
    </template>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-lg border bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-base font-semibold text-gray-900">Members</h2>
            <template x-if="loadingMembers">
                <p class="text-sm text-gray-500">Loading members...</p>
            </template>
            <template x-if="!loadingMembers && members.length === 0">
                <p class="text-sm text-gray-500">No active members found.</p>
            </template>
            <ul class="space-y-2" x-show="!loadingMembers && members.length > 0">
                <template x-for="member in members" :key="member.id">
                    <li class="flex items-center justify-between rounded border px-3 py-2 text-sm">
                        <div>
                            <p class="font-medium text-gray-900" x-text="member.name || member.email"></p>
                            <p class="text-xs text-gray-500" x-text="member.email"></p>
                        </div>
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700" x-text="member.role || 'member'"></span>
                    </li>
                </template>
            </ul>
        </section>

        <section class="rounded-lg border bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-base font-semibold text-gray-900">Pending Invitations</h2>
            <template x-if="loadingInvitations">
                <p class="text-sm text-gray-500">Loading invitations...</p>
            </template>
            <template x-if="!loadingInvitations && pendingInvitations.length === 0">
                <p class="text-sm text-gray-500">No pending invitations.</p>
            </template>
            <ul class="space-y-2" x-show="!loadingInvitations && pendingInvitations.length > 0">
                <template x-for="invitation in pendingInvitations" :key="invitation.id">
                    <li class="flex items-center justify-between rounded border px-3 py-2 text-sm">
                        <div>
                            <p class="font-medium text-gray-900" x-text="invitation.email"></p>
                            <p class="text-xs text-gray-500" x-text="`Role: ${invitation.role} · Expires: ${formatDate(invitation.expires_at)}`"></p>
                        </div>
                        <button
                            type="button"
                            class="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                            @click="revokeInvitation(invitation.id)"
                        >
                            Revoke
                        </button>
                    </li>
                </template>
            </ul>
        </section>
    </div>

    <div x-show="openInviteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
        <div class="w-full max-w-md rounded-lg bg-white p-5 shadow-xl">
            <h3 class="text-lg font-semibold text-gray-900">Invite Member</h3>
            <p class="mt-1 text-sm text-gray-600">Send an invitation to join this team.</p>
            <form class="mt-4 space-y-4" @submit.prevent="createInvitation()">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                    <input x-model="inviteForm.email" type="email" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Role</label>
                    <select x-model="inviteForm.role" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <option value="member">member</option>
                        <option value="lead">lead</option>
                        <option value="admin">admin</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700" @click="closeInviteModal()">Cancel</button>
                    <button type="submit" class="rounded-md bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700" :disabled="submittingInvite">
                        Send Invite
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function teamInvitationsPage() {
    return {
        teams: [],
        selectedTeamId: '',
        members: [],
        pendingInvitations: [],
        loadingTeams: false,
        loadingMembers: false,
        loadingInvitations: false,
        submittingInvite: false,
        openInviteModal: false,
        errorMessage: '',
        inviteForm: {
            email: '',
            role: 'member'
        },

        async init() {
            await this.loadTeams();
        },

        tenantId() {
            try {
                const raw = localStorage.getItem('user_data');
                if (!raw) return '';
                const user = JSON.parse(raw);
                return user?.tenant_id || '';
            } catch (_) {
                return '';
            }
        },

        authToken() {
            return localStorage.getItem('auth_token') || '';
        },

        apiHeaders() {
            const headers = {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            };
            const token = this.authToken();
            const tenantId = this.tenantId();
            if (token) headers['Authorization'] = `Bearer ${token}`;
            if (tenantId) headers['X-Tenant-ID'] = tenantId;
            return headers;
        },

        async request(url, options = {}) {
            const response = await fetch(url, {
                ...options,
                headers: {
                    ...this.apiHeaders(),
                    ...(options.headers || {})
                }
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.success === false) {
                throw new Error(payload?.error?.message || payload?.message || `Request failed (${response.status})`);
            }

            return payload;
        },

        async loadTeams() {
            this.loadingTeams = true;
            this.errorMessage = '';
            try {
                const payload = await this.request('/api/teams');
                const teamsData = payload?.data?.data || payload?.data || [];
                this.teams = Array.isArray(teamsData) ? teamsData : [];
                if (this.teams.length > 0) {
                    this.selectedTeamId = this.teams[0].id;
                    await this.loadTeamData();
                }
            } catch (error) {
                this.errorMessage = error.message;
            } finally {
                this.loadingTeams = false;
            }
        },

        async loadTeamData() {
            if (!this.selectedTeamId) {
                this.members = [];
                this.pendingInvitations = [];
                return;
            }

            this.errorMessage = '';
            this.loadingMembers = true;
            this.loadingInvitations = true;

            try {
                const [membersPayload, invitationsPayload] = await Promise.all([
                    this.request(`/api/teams/${encodeURIComponent(this.selectedTeamId)}/members`),
                    this.request(`/api/teams/${encodeURIComponent(this.selectedTeamId)}/invitations`)
                ]);

                this.members = Array.isArray(membersPayload.data) ? membersPayload.data : [];
                const allInvitations = Array.isArray(invitationsPayload.data) ? invitationsPayload.data : [];
                this.pendingInvitations = allInvitations.filter(invitation => invitation.status === 'pending');
            } catch (error) {
                this.errorMessage = error.message;
            } finally {
                this.loadingMembers = false;
                this.loadingInvitations = false;
            }
        },

        async createInvitation() {
            if (!this.selectedTeamId) return;
            this.submittingInvite = true;
            this.errorMessage = '';
            try {
                await this.request(`/api/teams/${encodeURIComponent(this.selectedTeamId)}/invitations`, {
                    method: 'POST',
                    body: JSON.stringify({
                        email: this.inviteForm.email,
                        role: this.inviteForm.role
                    })
                });
                this.closeInviteModal();
                await this.loadTeamData();
            } catch (error) {
                this.errorMessage = error.message;
            } finally {
                this.submittingInvite = false;
            }
        },

        async revokeInvitation(invitationId) {
            this.errorMessage = '';
            try {
                await this.request(`/api/teams/${encodeURIComponent(this.selectedTeamId)}/invitations/${encodeURIComponent(invitationId)}`, {
                    method: 'DELETE'
                });
                await this.loadTeamData();
            } catch (error) {
                this.errorMessage = error.message;
            }
        },

        closeInviteModal() {
            this.openInviteModal = false;
            this.inviteForm.email = '';
            this.inviteForm.role = 'member';
        },

        formatDate(value) {
            if (!value) return 'N/A';
            try {
                return new Date(value).toLocaleString();
            } catch (_) {
                return value;
            }
        }
    };
}
</script>
