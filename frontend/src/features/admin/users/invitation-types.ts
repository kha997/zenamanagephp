// Invitation types for admin users feature
export interface Invitation {
  id: number;
  email: string;
  first_name?: string;
  last_name?: string;
  role: string;
  tenant_id: string;
  tenant_name: string;
  token: string;
  link: string;
  status: 'pending' | 'accepted' | 'expired' | 'cancelled';
  expires_at: string;
  used_at?: string;
  accepted_at?: string;
  invited_by: number;
  inviter_name: string;
  message?: string;
  note?: string;
  created_at: string;
}

export interface CreateInvitationRequest {
  email: string;
  first_name?: string;
  last_name?: string;
  role: 'super_admin' | 'admin' | 'project_manager' | 'member' | 'client';
  tenant_id: string;
  project_id?: string;
  message?: string;
  note?: string;
  send_email?: boolean;
  expires_in_days?: number;
}

export interface BulkInvitationRequest {
  emails: string[];
  role: 'super_admin' | 'admin' | 'project_manager' | 'member' | 'client';
  tenant_id: string;
  project_id?: string;
  message?: string;
  note?: string;
  send_email?: boolean;
  expires_in_days?: number;
}

export interface InvitationsResponse {
  data: Invitation[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  links: {
    first: string;
    last: string;
    prev?: string;
    next?: string;
  };
}

export interface InvitationsFilters {
  tenant_id?: number;
  status?: 'pending' | 'accepted' | 'expired' | 'cancelled';
  search?: string;
  page?: number;
  per_page?: number;
}

export interface BulkInvitationResult {
  created: Invitation[];
  already_member: Array<{
    email: string;
    user: {
      id: number;
      name: string;
      email: string;
    };
  }>;
  pending: Invitation[];
  errors: Array<{
    row: number;
    email: string;
    message: string;
  }>;
  summary: {
    total: number;
    created: number;
    already_member: number;
    pending: number;
    errors: number;
  };
  email_sent: boolean;
}

export interface InvitationValidationResponse {
  valid: boolean;
  email: string;
  first_name?: string;
  last_name?: string;
  role: string;
  tenant_id: string;
  tenant_name: string;
  expires_at: string;
  message?: string;
}

