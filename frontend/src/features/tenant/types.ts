// Tenant Members & Invitations Types

export interface TenantMember {
  id: string | number;
  name: string;
  email: string;
  role: 'owner' | 'admin' | 'member' | 'viewer';
  is_default: boolean;
  joined_at: string;
}

export interface TenantMembersResponse {
  members: TenantMember[];
  pagination?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number;
    to?: number;
  };
}

export interface UpdateMemberRoleRequest {
  role: 'owner' | 'admin' | 'member' | 'viewer';
}

export interface TenantInvitation {
  id: string | number;
  email: string;
  role: 'owner' | 'admin' | 'member' | 'viewer';
  status: 'pending' | 'accepted' | 'revoked' | 'expired';
  invited_by?: {
    id: string | number;
    name: string;
    email?: string;
  };
  created_at: string;
  expires_at: string;
}

export interface TenantInvitationsResponse {
  invitations: TenantInvitation[];
}

export interface CreateInvitationRequest {
  email: string;
  role: 'owner' | 'admin' | 'member' | 'viewer';
  idempotency_key?: string;
}

export interface TenantMemberChangeResponse {
  member: TenantMember;
  acting_member: TenantMember;
}

