// Documents API types and interfaces
export interface Document {
  id: number;
  name: string;
  filename: string;
  original_filename: string;
  mime_type: string;
  size: number;
  path: string;
  url: string;
  project_id?: number;
  project_name?: string;
  uploaded_by: number;
  uploaded_by_name: string;
  uploaded_at: string;
  updated_at: string;
  tags: string[];
  description?: string;
  version: number;
  is_public: boolean;
  download_count: number;
  last_accessed_at?: string;
}

export interface DocumentsResponse {
  data: Document[];
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

export interface DocumentsFilters {
  search?: string;
  project_id?: number;
  mime_type?: string;
  tags?: string[];
  uploaded_by?: number;
  is_public?: boolean;
  page?: number;
  per_page?: number;
  sort_by?: 'name' | 'size' | 'uploaded_at' | 'download_count';
  sort_order?: 'asc' | 'desc';
}

export interface UploadDocumentRequest {
  file: File;
  project_id?: number;
  description?: string;
  tags?: string[];
  is_public?: boolean;
}

export interface UpdateDocumentRequest {
  name?: string;
  description?: string;
  tags?: string[];
  is_public?: boolean;
}

export interface DocumentStats {
  total_documents: number;
  total_size_bytes: number;
  total_downloads: number;
  documents_by_type: Record<string, number>;
  recent_uploads: Document[];
  most_downloaded: Document[];
}

export interface DocumentVersion {
  id: number;
  version: number;
  filename: string;
  size: number;
  uploaded_at: string;
  uploaded_by: number;
  uploaded_by_name: string;
  change_description?: string;
}
