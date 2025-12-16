export interface Contract {
  id: string | number;
  tenant_id: string;
  client_id?: string | number;
  project_id?: string | number;
  code: string;
  name: string;
  status: 'draft' | 'active' | 'completed' | 'cancelled';
  signed_at?: string;
  effective_from?: string;
  effective_to?: string;
  currency: string;
  total_value: number;
  notes?: string;
  created_by_id?: string | number;
  updated_by_id?: string | number;
  created_at: string;
  updated_at: string;
  // Relations
  client?: {
    id: string | number;
    name: string;
    code?: string;
  };
  project?: {
    id: string | number;
    name: string;
    code?: string;
  };
}

export interface ContractPayment {
  id: string | number;
  tenant_id: string;
  contract_id: string | number;
  code?: string;
  name: string;
  type?: 'deposit' | 'milestone' | 'progress' | 'retention' | 'final';
  due_date: string;
  amount: number;
  currency: string;
  status: 'planned' | 'due' | 'paid' | 'overdue' | 'cancelled';
  paid_at?: string;
  notes?: string;
  sort_order: number;
  created_by_id?: string | number;
  updated_by_id?: string | number;
  created_at: string;
  updated_at: string;
}

export interface ContractFilters {
  search?: string;
  status?: string;
  client_id?: string | number;
  project_id?: string | number;
  signed_from?: string;
  signed_to?: string;
  sort_by?: string;
  sort_direction?: 'asc' | 'desc';
}

export interface ContractsResponse {
  data: Contract[];
  meta?: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

export interface ContractPaymentsResponse {
  data: ContractPayment[];
  meta?: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

export interface CreatePaymentData {
  code?: string;
  name: string;
  type?: 'deposit' | 'milestone' | 'progress' | 'retention' | 'final';
  due_date: string;
  amount: number;
  currency?: string;
  status?: 'planned' | 'due' | 'paid' | 'overdue' | 'cancelled';
  notes?: string;
  sort_order?: number;
}

export interface UpdatePaymentData extends Partial<CreatePaymentData> {}

