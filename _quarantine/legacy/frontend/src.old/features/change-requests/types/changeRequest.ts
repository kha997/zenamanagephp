export type ChangeRequestStatus = 'draft' | 'awaiting_approval' | 'approved' | 'rejected';

export interface ChangeRequest {
  id: string;
  project_id: string;
  code: string;
  title: string;
  description: string;
  status: ChangeRequestStatus;
  impact_days: number;
  impact_cost: number;
  impact_kpi: Record<string, string>;
  created_by: string;
  decided_by?: string;
  decided_at?: string;
  decision_note?: string;
  created_at: string;
  updated_at: string;
}

export interface ChangeRequestFilters {
  search?: string;
  status?: ChangeRequestStatus | 'all';
  project_id?: string;
  created_by?: string;
  date_from?: string;
  date_to?: string;
  sort_by?: 'created_at' | 'updated_at' | 'impact_cost' | 'impact_days';
  sort_order?: 'asc' | 'desc';
  page?: number;
  limit?: number;
}

export interface CreateChangeRequestData {
  project_id: string;
  title: string;
  description: string;
  impact_days: number;
  impact_cost: number;
  impact_kpi: Record<string, string>;
}

export interface UpdateChangeRequestData {
  title?: string;
  description?: string;
  impact_days?: number;
  impact_cost?: number;
  impact_kpi?: Record<string, string>;
}

export interface ChangeRequestDecision {
  decision: 'approve' | 'reject';
  decision_note?: string;
}

export interface ChangeRequestsResponse {
  data: ChangeRequest[];
  total: number;
  page: number;
  limit: number;
  total_pages: number;
}

export interface ChangeRequestResponse {
  data: ChangeRequest;
}

export interface ChangeRequestStats {
  total: number;
  draft: number;
  awaiting_approval: number;
  approved: number;
  rejected: number;
  total_impact_cost: number;
  total_impact_days: number;
}

export interface ChangeRequestError {
  message: string;
  field?: string;
  code?: string;
}