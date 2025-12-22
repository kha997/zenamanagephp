/**
 * Invitation Types
 * 
 * Types for public invitation flow (Round 21)
 */

export interface PublicInvitation {
  tenant_name: string;
  email: string;
  role: string;
  status: 'pending' | 'accepted' | 'declined' | 'revoked' | 'expired';
  is_expired: boolean;
}

export interface PublicInvitationResponse {
  success: boolean;
  data: PublicInvitation;
  message?: string;
}

export interface AcceptInvitationResponse {
  success: boolean;
  data?: {
    invitation_status: string;
  };
  message?: string;
}

export interface DeclineInvitationResponse {
  success: boolean;
  data?: {
    invitation_status: string;
  };
  message?: string;
}

