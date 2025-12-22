import React, { useState, useEffect } from 'react';
import { Modal } from '../../../../shared/ui/modal';
import { Button } from '../../../../shared/ui/button';
import { Input } from '../../../../components/ui/primitives/Input';
import { Select } from '../../../../components/ui/primitives/Select';
import { useCreateInvitation, useBulkCreateInvitations } from '../hooks';
import type { CreateInvitationRequest, BulkInvitationRequest } from '../invitation-types';
import toast from 'react-hot-toast';
import { useAuthStore } from '../../../../features/auth/store';
import { BulkInviteForm } from './BulkInviteForm';

interface InviteUserModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess?: () => void;
}

type InviteMode = 'single' | 'bulk';

const ROLE_OPTIONS = [
  { value: 'member', label: 'Member' },
  { value: 'project_manager', label: 'Project Manager' },
  { value: 'admin', label: 'Admin' },
  { value: 'client', label: 'Client' },
];

export const InviteUserModal: React.FC<InviteUserModalProps> = ({
  open,
  onOpenChange,
  onSuccess,
}) => {
  const user = useAuthStore((state) => state.user);
  const [mode, setMode] = useState<InviteMode>('single');
  const [email, setEmail] = useState('');
  const [emails, setEmails] = useState<string[]>([]);
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [role, setRole] = useState<CreateInvitationRequest['role']>('member');
  const [tenantId, setTenantId] = useState<string>('');
  const [message, setMessage] = useState('');
  const [note, setNote] = useState('');
  const [sendEmail, setSendEmail] = useState(true);
  const [expiresInDays, setExpiresInDays] = useState(7);

  const createInvitation = useCreateInvitation();
  const bulkCreateInvitations = useBulkCreateInvitations();

  // Check if user is Super Admin
  const isSuperAdmin = user?.permissions?.includes('admin.access') || user?.is_super_admin;

  // Auto-set tenant_id for non-super-admin users
  useEffect(() => {
    if (!isSuperAdmin && user?.tenant_id && !tenantId) {
      setTenantId(String(user.tenant_id));
    }
  }, [isSuperAdmin, user?.tenant_id, tenantId]);

  // Reset form when modal closes
  useEffect(() => {
    if (!open) {
      setMode('single');
      setEmail('');
      setEmails([]);
      setFirstName('');
      setLastName('');
      setRole('member');
      setMessage('');
      setNote('');
      setSendEmail(true);
      setExpiresInDays(7);
      if (!isSuperAdmin && user?.tenant_id) {
        setTenantId(String(user.tenant_id));
      } else {
        setTenantId('');
      }
    }
  }, [open, isSuperAdmin, user?.tenant_id]);

  const handleSingleInvite = async () => {
    if (!email.trim()) {
      toast.error('Please enter an email address');
      return;
    }

    if (!tenantId) {
      toast.error('Please select a tenant');
      return;
    }

    try {
      const data: CreateInvitationRequest = {
        email: email.trim(),
        first_name: firstName.trim() || undefined,
        last_name: lastName.trim() || undefined,
        role,
        tenant_id: tenantId,
        message: message.trim() || undefined,
        note: note.trim() || undefined,
        send_email: sendEmail,
        expires_in_days: expiresInDays,
      };

      const result = await createInvitation.mutateAsync(data);
      
      if (result.invitation) {
        toast.success(`Invitation sent to ${email}`);
        if (result.email_sent) {
          toast.success('Email notification sent');
        } else {
          toast('Email not configured. You can copy the invitation link manually.', {
            duration: 5000,
            icon: 'ℹ️',
          });
        }
        onSuccess?.();
        onOpenChange(false);
      }
    } catch (error: any) {
      const errorMessage = error?.response?.data?.error?.message || error?.message || 'Failed to send invitation';
      toast.error(errorMessage);
    }
  };

  const handleBulkInvite = async () => {
    if (emails.length === 0) {
      toast.error('Please enter at least one email address');
      return;
    }

    if (!tenantId) {
      toast.error('Please select a tenant');
      return;
    }

    try {
      const data: BulkInvitationRequest = {
        emails,
        role,
        tenant_id: tenantId,
        message: message.trim() || undefined,
        note: note.trim() || undefined,
        send_email: sendEmail,
        expires_in_days: expiresInDays,
      };

      const result = await bulkCreateInvitations.mutateAsync(data);
      
      const { summary } = result;
      toast.success(
        `Invitations processed: ${summary.created} created, ${summary.already_member} already members, ${summary.pending} pending, ${summary.errors} errors`
      );
      
      if (summary.errors > 0) {
        toast.error(`Some invitations failed. Check the details.`, { duration: 5000 });
      }
      
      if (result.email_sent) {
        toast.success('Email notifications sent');
      } else {
        toast('Email not configured. You can copy invitation links manually.', {
          duration: 5000,
          icon: 'ℹ️',
        });
      }
      
      onSuccess?.();
      onOpenChange(false);
    } catch (error: any) {
      const errorMessage = error?.response?.data?.error?.message || error?.message || 'Failed to send invitations';
      toast.error(errorMessage);
    }
  };

  const handleSubmit = () => {
    if (mode === 'single') {
      handleSingleInvite();
    } else {
      handleBulkInvite();
    }
  };

  const isLoading = createInvitation.isPending || bulkCreateInvitations.isPending;

  return (
    <Modal
      open={open}
      onOpenChange={onOpenChange}
      title="Invite User"
      description="Send an invitation to join your organization"
      primaryAction={{
        label: mode === 'single' ? 'Send Invitation' : 'Send Invitations',
        onClick: handleSubmit,
        loading: isLoading,
      }}
      secondaryAction={{
        label: 'Cancel',
        onClick: () => onOpenChange(false),
      }}
    >
      <div className="space-y-4">
        {/* Mode Toggle */}
        <div className="flex gap-2 border-b border-[var(--color-border-subtle)] pb-4">
          <Button
            variant={mode === 'single' ? 'primary' : 'outline'}
            size="sm"
            onClick={() => setMode('single')}
          >
            Single
          </Button>
          <Button
            variant={mode === 'bulk' ? 'primary' : 'outline'}
            size="sm"
            onClick={() => setMode('bulk')}
          >
            Bulk
          </Button>
        </div>

        {/* Tenant Selector (Super Admin only) */}
        {isSuperAdmin && (
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
              Tenant *
            </label>
            <Input
              placeholder="Enter tenant ID"
              value={tenantId}
              onChange={(e) => setTenantId(e.target.value)}
              required
            />
            <p className="text-xs text-[var(--color-text-muted)] mt-1">
              Enter the tenant ID to invite users to
            </p>
          </div>
        )}

        {/* Role Selector */}
        <div>
          <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
            Role *
          </label>
          <Select
            options={ROLE_OPTIONS}
            value={role}
            onChange={(value) => setRole(value as CreateInvitationRequest['role'])}
            placeholder="Select role"
          />
        </div>

        {/* Single Mode Form */}
        {mode === 'single' && (
          <>
            <div>
              <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                Email Address *
              </label>
              <Input
                type="email"
                placeholder="user@example.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                  First Name
                </label>
                <Input
                  placeholder="John"
                  value={firstName}
                  onChange={(e) => setFirstName(e.target.value)}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
                  Last Name
                </label>
                <Input
                  placeholder="Doe"
                  value={lastName}
                  onChange={(e) => setLastName(e.target.value)}
                />
              </div>
            </div>
          </>
        )}

        {/* Bulk Mode Form */}
        {mode === 'bulk' && (
          <BulkInviteForm
            emails={emails}
            onEmailsChange={setEmails}
          />
        )}

        {/* Message */}
        <div>
          <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
            Message (Optional)
          </label>
          <textarea
            className="w-full min-h-[80px] px-3 py-2 border border-[var(--color-border-default)] rounded-[var(--radius-md)] bg-transparent text-[var(--color-text-primary)] resize-none focus:outline-none focus:ring-2 focus:ring-[var(--color-border-focus)]"
            placeholder="Add a personal message to the invitation..."
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            maxLength={1000}
          />
          <p className="text-xs text-[var(--color-text-muted)] mt-1">
            {message.length}/1000 characters
          </p>
        </div>

        {/* Note (Internal) */}
        <div>
          <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
            Internal Note (Optional)
          </label>
          <textarea
            className="w-full min-h-[60px] px-3 py-2 border border-[var(--color-border-default)] rounded-[var(--radius-md)] bg-transparent text-[var(--color-text-primary)] resize-none focus:outline-none focus:ring-2 focus:ring-[var(--color-border-focus)]"
            placeholder="Internal note (not visible to invitee)..."
            value={note}
            onChange={(e) => setNote(e.target.value)}
            maxLength={500}
          />
          <p className="text-xs text-[var(--color-text-muted)] mt-1">
            {note.length}/500 characters
          </p>
        </div>

        {/* Options */}
        <div className="space-y-2 border-t border-[var(--color-border-subtle)] pt-4">
          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              checked={sendEmail}
              onChange={(e) => setSendEmail(e.target.checked)}
              className="w-4 h-4 rounded border-[var(--color-border-default)]"
            />
            <span className="text-sm text-[var(--color-text-secondary)]">
              Send email notification
            </span>
          </label>

          <div>
            <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
              Expires In (Days)
            </label>
            <Input
              type="number"
              min="1"
              max="30"
              value={expiresInDays}
              onChange={(e) => setExpiresInDays(Number(e.target.value) || 7)}
            />
          </div>
        </div>
      </div>
    </Modal>
  );
};

