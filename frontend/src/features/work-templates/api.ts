import type { ApiResponse } from '@/lib/types'
import { apiClient } from '@/lib/api/client'

export type TemplateStatus = 'draft' | 'published' | 'archived'

export type WorkTemplateFieldInput = {
  key: string
  label: string
  type: 'string' | 'text' | 'number' | 'date' | 'enum' | 'boolean'
  required?: boolean
  default?: string | number | boolean | null
  enum_options?: string[]
}

export type WorkTemplateStepInput = {
  key: string
  name: string
  type: string
  order: number
  sla_hours?: number | null
  assignee_rule?: { role?: string; rule?: string } | null
  fields: WorkTemplateFieldInput[]
}

export type WorkTemplateVersion = {
  id: string
  semver?: string
  published_at?: string | null
  content_json?: {
    steps?: WorkTemplateStepInput[]
    rules?: Record<string, unknown>
  }
}

export type WorkTemplateRecord = {
  id: string
  code: string
  name: string
  description?: string | null
  status: TemplateStatus
  versions?: WorkTemplateVersion[]
  created_at?: string
  updated_at?: string
}

type PaginationMeta = {
  pagination?: {
    page: number
    per_page: number
    total: number
    last_page: number
  }
}

const zenaPath = (path: string) => `/api/zena${path}`

const ensureData = <T>(response: ApiResponse<T>): T => {
  if (response.data === undefined) {
    throw new Error(response.message || 'Unexpected API response')
  }
  return response.data
}

export async function listWorkTemplates(params?: { page?: number; per_page?: number }) {
  const response = await apiClient.get<WorkTemplateRecord[]>(zenaPath('/work-templates'), { params })
  return {
    items: ensureData(response),
    meta: (response.meta || {}) as PaginationMeta,
  }
}

export async function createWorkTemplate(payload: {
  name: string
  code: string
  description?: string
  vertical?: string
}) {
  const response = await apiClient.post<WorkTemplateRecord>(zenaPath('/work-templates'), {
    name: payload.name,
    code: payload.code,
    description: payload.description,
    status: 'draft',
    content_json: {
      steps: [],
      approvals: [],
      rules: {
        vertical: payload.vertical || null,
      },
    },
    steps: [],
  })

  return ensureData(response)
}

export async function getWorkTemplate(id: string) {
  const response = await apiClient.get<WorkTemplateRecord>(zenaPath(`/work-templates/${id}`))
  return ensureData(response)
}

export async function updateWorkTemplate(
  id: string,
  payload: {
    name?: string
    description?: string
    status?: TemplateStatus
    vertical?: string
    steps?: WorkTemplateStepInput[]
  }
) {
  const response = await apiClient.put<WorkTemplateRecord>(zenaPath(`/work-templates/${id}`), {
    name: payload.name,
    description: payload.description,
    status: payload.status,
    content_json: {
      steps: payload.steps || [],
      approvals: [],
      rules: {
        vertical: payload.vertical || null,
      },
    },
    steps: payload.steps || [],
  })

  return ensureData(response)
}

export async function publishWorkTemplate(id: string) {
  const response = await apiClient.post<{ id: string }>(zenaPath(`/work-templates/${id}/publish`))
  return ensureData(response)
}

export type AppliedWorkInstance = {
  id: string
  status: string
  project_id: string
  steps?: Array<{
    id: string
    name: string
    step_key: string
    status: string
    type: string
    step_order: number
    snapshot_fields_json?: Array<{
      field_key: string
      label: string
      type: string
      required?: boolean
      default?: string | number | boolean | null
      enum_options?: string[]
    }>
  }>
}

export async function applyTemplateToProject(projectId: string, workTemplateId: string) {
  const response = await apiClient.post<AppliedWorkInstance>(zenaPath(`/projects/${projectId}/apply-template`), {
    work_template_id: workTemplateId,
  })
  return ensureData(response)
}
