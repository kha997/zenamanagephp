import React, { useState, useCallback, useMemo } from 'react';
import toast from 'react-hot-toast';
import { useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../shared/ui/card';
import { AccessRestricted } from '../../../components/shared/AccessRestricted';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { Select } from '../../../components/ui/primitives/Select';
import { Modal } from '../../../shared/ui/modal';
import { useAuthStore } from '../../auth/store';
import {
  useTenantMembers,
  useTenantInvitations,
  useUpdateMemberRole,
  useRemoveMember,
  useCreateInvitation,
  useRevokeInvitation,
  useResendInvitation,
  useLeaveCurrentTenant,
  useMakeOwner,
} from '../hooks';
import type { TenantMember, TenantInvitation } from '../types';

type TabType = 'members' | 'invitations';

/**
 * TenantMembersPage - Tenant Members & Invitations Management
 * 
 * Displays members and invitations with permission-based access control.
 * Read-only for tenant.view_members, full management for tenant.manage_members.
 */
export const TenantMembersPage: React.FC = () => {
  const { hasTenantPermission, user, currentTenantRole } = useAuthStore();
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState<TabType>('members');
  
  // Permission checks
  const canViewMembers =
    hasTenantPermission('tenant.view_members') ||
    hasTenantPermission('tenant.manage_members');
  const canManageMembers = hasTenantPermission('tenant.manage_members');
  const isCurrentTenantOwner = currentTenantRole === 'owner';
  const canManageOwnership = canManageMembers && isCurrentTenantOwner;
  const isReadOnly = canViewMembers && !canManageMembers;

  // All hooks must be called before any conditional returns (React hooks rules)
  const { data: membersData, isLoading: membersLoading, error: membersError } = useTenantMembers(
    undefined,
    { enabled: canViewMembers }
  );
  const { data: invitationsData, isLoading: invitationsLoading, error: invitationsError } = useTenantInvitations(
    { enabled: canViewMembers }
  );

  // Mutations
  const updateRoleMutation = useUpdateMemberRole();
  const removeMemberMutation = useRemoveMember();
  const createInvitationMutation = useCreateInvitation();
  const revokeInvitationMutation = useRevokeInvitation();
  const resendInvitationMutation = useResendInvitation();
  const leaveTenantMutation = useLeaveCurrentTenant();
  const makeOwnerMutation = useMakeOwner();

  // Modal states
  const [showCreateInvitationModal, setShowCreateInvitationModal] = useState(false);
  const [showRemoveMemberModal, setShowRemoveMemberModal] = useState<{ member: TenantMember } | null>(null);
  const [invitationForm, setInvitationForm] = useState({ email: '', role: 'member' as const });

  // Early return if user doesn't have view permission
  if (!canViewMembers) {
    return (
      <Container>
        <AccessRestricted
          title="Access Restricted"
          description="You don't have permission to view tenant members."
        />
      </Container>
    );
  }

  const members = membersData?.members || [];
  const invitations = invitationsData?.invitations || [];

  // Handlers
  const handleRoleChange = useCallback(async (memberId: string | number, newRole: string) => {
    try {
      await updateRoleMutation.mutateAsync({
        id: memberId,
        data: { role: newRole as 'owner' | 'admin' | 'member' | 'viewer' },
      });
      toast.success('Member role updated successfully');
    } catch (error: any) {
      if (error.code === 'TENANT_LAST_OWNER_PROTECTED') {
        toast.error('You cannot remove the last owner of this tenant.');
      } else if (error.code === 'TENANT_PERMISSION_DENIED') {
        toast.error('You don\'t have permission to perform this action.');
      } else {
        toast.error(error?.message || 'Failed to update member role');
      }
    }
  }, [updateRoleMutation]);

  const handleRemoveMember = useCallback(async () => {
    if (!showRemoveMemberModal) return;

    try {
      await removeMemberMutation.mutateAsync(showRemoveMemberModal.member.id);
      toast.success('Member removed successfully');
      setShowRemoveMemberModal(null);
    } catch (error: any) {
      if (error.code === 'TENANT_LAST_OWNER_PROTECTED') {
        toast.error('You cannot remove the last owner of this tenant.');
      } else if (error.code === 'TENANT_PERMISSION_DENIED') {
        toast.error('You don\'t have permission to perform this action.');
      } else {
        toast.error(error?.message || 'Failed to remove member');
      }
    }
  }, [showRemoveMemberModal, removeMemberMutation]);

  const handleCreateInvitation = useCallback(async () => {
    if (!invitationForm.email || !invitationForm.role) {
      toast.error('Please fill in all fields');
      return;
    }

    try {
      // Generate idempotency key
      const idempotencyKey = `invite_${invitationForm.email}_${Date.now()}`;
      await createInvitationMutation.mutateAsync({
        email: invitationForm.email,
        role: invitationForm.role,
        idempotency_key: idempotencyKey,
      });
      toast.success('Invitation created successfully');
      setShowCreateInvitationModal(false);
      setInvitationForm({ email: '', role: 'member' });
    } catch (error: any) {
      if (error.code === 'TENANT_INVITE_ALREADY_MEMBER') {
        toast.error('This user is already a member of this tenant.');
      } else if (error.code === 'TENANT_INVITE_ALREADY_PENDING') {
        toast.error('There is already a pending invitation for this email.');
      } else if (error.code === 'TENANT_INVALID_ROLE') {
        toast.error('Invalid role selected.');
      } else if (error.code === 'TENANT_PERMISSION_DENIED') {
        toast.error('You don\'t have permission to perform this action.');
      } else if (error.code === 'VALIDATION_FAILED') {
        toast.error(error?.message || 'Validation failed. Please check your input.');
      } else {
        toast.error(error?.message || 'Failed to create invitation');
      }
    }
  }, [invitationForm, createInvitationMutation]);

  const handleResendInvitation = useCallback(async (invitation: TenantInvitation) => {
    try {
      await resendInvitationMutation.mutateAsync(invitation.id);
      toast.success('Invitation email resent successfully');
    } catch (error: any) {
      // Map error codes similar to landing page Round 21
      switch (error?.code) {
        case 'TENANT_INVITE_EXPIRED':
          toast.error('This invitation has expired and cannot be resent.');
          break;
        case 'TENANT_INVITE_ALREADY_ACCEPTED':
          toast.error('This invitation has already been accepted.');
          break;
        case 'TENANT_INVITE_ALREADY_DECLINED':
          toast.error('This invitation has already been declined.');
          break;
        case 'TENANT_INVITE_ALREADY_REVOKED':
          toast.error('This invitation has been revoked.');
          break;
        case 'TENANT_PERMISSION_DENIED':
          toast.error('You do not have permission to manage invitations.');
          break;
        default:
          toast.error('Failed to resend invitation. Please try again.');
      }
    }
  }, [resendInvitationMutation]);

  const handleRevokeInvitation = useCallback(async (invitationId: string | number) => {
    try {
      await revokeInvitationMutation.mutateAsync(invitationId);
      toast.success('Invitation revoked successfully');
    } catch (error: any) {
      if (error.code === 'TENANT_PERMISSION_DENIED') {
        toast.error('You don\'t have permission to perform this action.');
      } else {
        toast.error(error?.message || 'Failed to revoke invitation');
      }
    }
  }, [revokeInvitationMutation]);

  const handleLeaveTenant = useCallback(async () => {
    try {
      await leaveTenantMutation.mutateAsync();
      toast.success('You have left this workspace.');
      
      // Navigate to dashboard after leaving
      // The auth state will be refreshed automatically via query invalidation
      navigate('/app/dashboard', { replace: true });
    } catch (error: any) {
      if (error?.code === 'TENANT_LAST_OWNER_PROTECTED') {
        toast.error('You are the last owner of this workspace and cannot leave.');
      } else {
        toast.error('Failed to leave this workspace. Please try again.');
      }
    }
  }, [leaveTenantMutation, navigate]);

  const handleMakeOwner = useCallback(async (memberId: string | number) => {
    try {
      await makeOwnerMutation.mutateAsync({ memberId, demoteSelf: false });
      toast.success('Member promoted to owner successfully.');
    } catch (error: any) {
      if (error.code === 'TENANT_PERMISSION_DENIED') {
        toast.error('You do not have permission to manage ownership.');
      } else if (error.code === 'TENANT_MEMBER_ALREADY_OWNER') {
        toast.error('This member is already an owner.');
      } else {
        toast.error('Failed to change ownership. Please refresh and try again.');
      }
    }
  }, [makeOwnerMutation]);

  const handleTransferOwnership = useCallback(async (memberId: string | number) => {
    const confirmed = window.confirm(
      'You will transfer ownership to this member and become an admin in this workspace. Are you sure?'
    );
    if (!confirmed) return;

    try {
      await makeOwnerMutation.mutateAsync({ memberId, demoteSelf: true });
      toast.success('Ownership transferred successfully. You are now an admin.');
    } catch (error: any) {
      if (error.code === 'TENANT_PERMISSION_DENIED') {
        toast.error('You do not have permission to manage ownership.');
      } else if (error.code === 'TENANT_MEMBER_ALREADY_OWNER') {
        toast.error('This member is already an owner.');
      } else {
        toast.error('Failed to change ownership. Please refresh and try again.');
      }
    }
  }, [makeOwnerMutation]);

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  const roleOptions = [
    { value: 'owner', label: 'Owner' },
    { value: 'admin', label: 'Admin' },
    { value: 'member', label: 'Member' },
    { value: 'viewer', label: 'Viewer' },
  ];

  const invitationRoleOptions = [
    { value: 'admin', label: 'Admin' },
    { value: 'member', label: 'Member' },
    { value: 'viewer', label: 'Viewer' },
  ];

  return (
    <Container>
      <div className="space-y-6">
        {/* Page Header */}
        <div className="flex items-center justify-between">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)]">
                Members & Invitations
              </h1>
              {isReadOnly && (
                <span className="px-2 py-1 text-xs font-medium rounded bg-[var(--muted-surface)] text-[var(--muted)]">
                  Read-only mode
                </span>
              )}
            </div>
            <p className="text-[var(--font-body-size)] text-[var(--muted)] mt-1">
              Manage team members and invitations for this workspace
            </p>
          </div>
        </div>

        {/* Tabs */}
        <Card>
          <CardHeader>
            <div className="flex items-center gap-4 border-b border-[var(--border)]">
              <button
                onClick={() => setActiveTab('members')}
                className={`px-4 py-2 text-sm font-medium transition-colors ${
                  activeTab === 'members'
                    ? 'text-[var(--text)] border-b-2 border-[var(--primary)]'
                    : 'text-[var(--muted)] hover:text-[var(--text)]'
                }`}
              >
                Members
              </button>
              <button
                onClick={() => setActiveTab('invitations')}
                className={`px-4 py-2 text-sm font-medium transition-colors ${
                  activeTab === 'invitations'
                    ? 'text-[var(--text)] border-b-2 border-[var(--primary)]'
                    : 'text-[var(--muted)] hover:text-[var(--text)]'
                }`}
              >
                Invitations
              </button>
            </div>
          </CardHeader>
          <CardContent className="pt-6">
            {/* Members Tab */}
            {activeTab === 'members' && (
              <div className="space-y-4">
                {membersLoading ? (
                  <div className="text-center py-8 text-[var(--muted)]">Loading members...</div>
                ) : membersError ? (
                  <div className="text-center py-8 text-red-600">
                    Error loading members: {(membersError as Error).message}
                  </div>
                ) : members.length === 0 ? (
                  <div className="text-center py-8 text-[var(--muted)]">No members found</div>
                ) : (
                  <div className="overflow-x-auto">
                    <table className="w-full border-collapse">
                      <thead>
                        <tr className="border-b border-[var(--border)]">
                          <th className="text-left py-3 px-4 text-sm font-medium text-[var(--text)]">Name</th>
                          <th className="text-left py-3 px-4 text-sm font-medium text-[var(--text)]">Email</th>
                          <th className="text-left py-3 px-4 text-sm font-medium text-[var(--text)]">Role</th>
                          <th className="text-left py-3 px-4 text-sm font-medium text-[var(--text)]">Default</th>
                          <th className="text-left py-3 px-4 text-sm font-medium text-[var(--text)]">Joined At</th>
                          {canManageMembers && (
                            <th className="text-left py-3 px-4 text-sm font-medium text-[var(--text)]">Actions</th>
                          )}
                        </tr>
                      </thead>
                      <tbody>
                        {members.map((member) => (
                          <tr key={member.id} className="border-b border-[var(--border-subtle)] hover:bg-[var(--muted-surface)]">
                            <td className="py-3 px-4">
                              <div className="flex items-center gap-2">
                                <span className="text-[var(--text)]">{member.name}</span>
                                {member.role === 'owner' && (
                                  <span className="px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-800">
                                    Owner
                                  </span>
                                )}
                              </div>
                            </td>
                            <td className="py-3 px-4 text-[var(--text)]">{member.email}</td>
                            <td className="py-3 px-4">
                              {canManageMembers ? (
                                <Select
                                  value={member.role}
                                  options={roleOptions}
                                  onChange={(value) => handleRoleChange(member.id, value)}
                                  style={{ width: 120 }}
                                />
                              ) : (
                                <span className="text-[var(--text)] capitalize">{member.role}</span>
                              )}
                            </td>
                            <td className="py-3 px-4">
                              {member.is_default ? (
                                <span className="text-xs text-[var(--muted)]">Default</span>
                              ) : (
                                <span className="text-xs text-[var(--muted)]">—</span>
                              )}
                            </td>
                            <td className="py-3 px-4 text-[var(--muted)] text-sm">
                              {formatDate(member.joined_at)}
                            </td>
                            {canManageMembers && (
                              <td className="py-3 px-4">
                                <div className="flex gap-2 items-center">
                                  {canManageOwnership && member.role !== 'owner' && member.id !== user?.id && (
                                    <div className="flex gap-1">
                                      <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleMakeOwner(member.id)}
                                        disabled={makeOwnerMutation.isPending}
                                      >
                                        Make owner
                                      </Button>
                                      <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleTransferOwnership(member.id)}
                                        disabled={makeOwnerMutation.isPending}
                                      >
                                        Transfer ownership
                                      </Button>
                                    </div>
                                  )}
                                  <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => setShowRemoveMemberModal({ member })}
                                    style={{ color: 'var(--destructive)' }}
                                  >
                                    Remove
                                  </Button>
                                </div>
                              </td>
                            )}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            )}

            {/* Invitations Tab */}
            {activeTab === 'invitations' && (
              <div className="space-y-4">
                {canManageMembers && (
                  <div className="flex justify-end mb-4">
                    <Button
                      variant="primary"
                      size="sm"
                      onClick={() => setShowCreateInvitationModal(true)}
                    >
                      Create Invitation
                    </Button>
                  </div>
                )}
                {invitationsLoading ? (
                  <div className="text-center py-8 text-[var(--muted)]">Loading invitations...</div>
                ) : invitationsError ? (
                  <div className="text-center py-8 text-red-600">
                    Error loading invitations: {(invitationsError as Error).message}
                  </div>
                ) : invitations.length === 0 ? (
                  <div className="text-center py-8 text-[var(--muted)]">No invitations found</div>
                ) : (
                  <div className="overflow-x-auto">
                    <table className="w-full border-collapse">
                      <thead>
                        <tr className="border-b border-[var(--border)]">
                          <th className="text-left py-3 px-4 text-sm font-medium text-[var(--text)]">Email</th>
                          <th className="text-left py-3 px-4 text-sm font-medium text-[var(--text)]">Role</th>
                          <th className="text-left py-3 px-4 text-sm font-medium text-[var(--text)]">Status</th>
                          <th className="text-left py-3 px-4 text-sm font-medium text-[var(--text)]">Invited By</th>
                          <th className="text-left py-3 px-4 text-sm font-medium text-[var(--text)]">Created At</th>
                          <th className="text-left py-3 px-4 text-sm font-medium text-[var(--text)]">Expires At</th>
                          {canManageMembers && (
                            <th className="text-left py-3 px-4 text-sm font-medium text-[var(--text)]">Actions</th>
                          )}
                        </tr>
                      </thead>
                      <tbody>
                        {invitations.map((invitation) => (
                          <tr key={invitation.id} className="border-b border-[var(--border-subtle)] hover:bg-[var(--muted-surface)]">
                            <td className="py-3 px-4 text-[var(--text)]">{invitation.email}</td>
                            <td className="py-3 px-4 text-[var(--text)] capitalize">{invitation.role}</td>
                            <td className="py-3 px-4">
                              <span
                                className={`px-2 py-0.5 text-xs font-medium rounded ${
                                  invitation.status === 'pending'
                                    ? 'bg-yellow-100 text-yellow-800'
                                    : invitation.status === 'accepted'
                                    ? 'bg-green-100 text-green-800'
                                    : invitation.status === 'revoked'
                                    ? 'bg-red-100 text-red-800'
                                    : 'bg-gray-100 text-gray-800'
                                }`}
                              >
                                {invitation.status}
                              </span>
                            </td>
                            <td className="py-3 px-4 text-[var(--muted)] text-sm">
                              {invitation.invited_by?.name || '—'}
                            </td>
                            <td className="py-3 px-4 text-[var(--muted)] text-sm">
                              {formatDate(invitation.created_at)}
                            </td>
                            <td className="py-3 px-4 text-[var(--muted)] text-sm">
                              {formatDate(invitation.expires_at)}
                            </td>
                            {canManageMembers && (
                              <td className="py-3 px-4">
                                {invitation.status === 'pending' && (
                                  <div className="flex gap-2">
                                    <Button
                                      variant="outline"
                                      size="sm"
                                      onClick={() => handleResendInvitation(invitation)}
                                      disabled={resendInvitationMutation.isPending}
                                    >
                                      Resend
                                    </Button>
                                    <Button
                                      variant="secondary"
                                      size="sm"
                                      onClick={() => handleRevokeInvitation(invitation.id)}
                                      style={{ color: 'var(--destructive)' }}
                                    >
                                      Revoke
                                    </Button>
                                  </div>
                                )}
                              </td>
                            )}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            )}
          </CardContent>
        </Card>

        {/* Create Invitation Modal */}
        <Modal
          open={showCreateInvitationModal}
          onOpenChange={setShowCreateInvitationModal}
          title="Create Invitation"
          description="Invite a new member to this workspace"
          primaryAction={{
            label: 'Create Invitation',
            onClick: handleCreateInvitation,
            loading: createInvitationMutation.isPending,
          }}
          secondaryAction={{
            label: 'Cancel',
            onClick: () => {
              setShowCreateInvitationModal(false);
              setInvitationForm({ email: '', role: 'member' });
            },
          }}
        >
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-2">
                Email
              </label>
              <Input
                type="email"
                value={invitationForm.email}
                onChange={(e) => setInvitationForm({ ...invitationForm, email: e.target.value })}
                placeholder="user@example.com"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-2">
                Role
              </label>
              <Select
                value={invitationForm.role}
                options={invitationRoleOptions}
                onChange={(value) => setInvitationForm({ ...invitationForm, role: value as 'admin' | 'member' | 'viewer' })}
              />
            </div>
          </div>
        </Modal>

        {/* Remove Member Confirmation Modal */}
        <Modal
          open={!!showRemoveMemberModal}
          onOpenChange={(open) => !open && setShowRemoveMemberModal(null)}
          title="Remove Member"
          description={`Are you sure you want to remove ${showRemoveMemberModal?.member.name} from this workspace?`}
          primaryAction={{
            label: 'Remove',
            onClick: handleRemoveMember,
            loading: removeMemberMutation.isPending,
            variant: 'destructive',
          }}
          secondaryAction={{
            label: 'Cancel',
            onClick: () => setShowRemoveMemberModal(null),
          }}
        >
          <p className="text-sm text-[var(--muted)]">
            This action cannot be undone. The member will lose access to this workspace.
          </p>
        </Modal>

        {/* Leave Workspace Section */}
        {canViewMembers && user && (
          <section className="mt-8">
            <Card>
              <CardHeader>
                <CardTitle>Leave this workspace</CardTitle>
                <CardDescription>
                  You can leave this workspace. You will lose access to its projects, tasks, and documents.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <p className="text-sm text-[var(--muted)] mb-4">
                  This action only affects your own membership. It does not delete the workspace or affect other members.
                </p>
                <Button
                  variant="destructive"
                  onClick={handleLeaveTenant}
                  disabled={leaveTenantMutation.isPending}
                  data-testid="leave-tenant-button"
                >
                  {leaveTenantMutation.isPending ? 'Leaving...' : 'Leave this workspace'}
                </Button>
              </CardContent>
            </Card>
          </section>
        )}
      </div>
    </Container>
  );
};

