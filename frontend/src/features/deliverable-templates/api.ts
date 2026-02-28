import type { ApiResponse } from '@/lib/types'
import { apiClient } from '@/lib/api/client'

export type DeliverableTemplateStatus = 'draft' | 'published' | 'archived'

export type DeliverableTemplateRecord = {
  id: string
  code: string
  name: string
  description?: string | null
  status: DeliverableTemplateStatus
  created_at?: string
  updated_at?: string
}

export type DeliverableTemplateVersionRecord = {
  id: string
  semver: string
  storage_path: string
  checksum_sha256: string
  mime: string
  size: number
  placeholders_spec_json?: {
    schema_version?: string
    placeholders?: Array<{ key: string; type?: string; required?: boolean }>
  } | null
  published_at?: string | null
  published_by?: string | null
  created_at?: string
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

export async function listDeliverableTemplates(params?: { page?: number; per_page?: number }) {
  const response = await apiClient.get<DeliverableTemplateRecord[]>(zenaPath('/deliverable-templates'), { params })
  return {
    items: ensureData(response),
    meta: (response.meta || {}) as PaginationMeta,
  }
}

export async function createDeliverableTemplate(payload: {
  code: string
  name: string
  description?: string
}) {
  const response = await apiClient.post<DeliverableTemplateRecord>(zenaPath('/deliverable-templates'), {
    code: payload.code,
    name: payload.name,
    description: payload.description,
    status: 'draft',
  })

  return ensureData(response)
}

export async function getDeliverableTemplate(id: string) {
  const response = await apiClient.get<DeliverableTemplateRecord>(zenaPath(`/deliverable-templates/${id}`))
  return ensureData(response)
}

export async function uploadDeliverableTemplateVersion(
  id: string,
  payload: {
    file: File
  }
) {
  const formData = new FormData()
  formData.append('file', payload.file)

  const response = await apiClient.post<DeliverableTemplateVersionRecord>(
    zenaPath(`/deliverable-templates/${id}/upload-version`),
    formData,
    {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    }
  )

  return ensureData(response)
}

export async function publishDeliverableTemplateVersion(id: string) {
  const response = await apiClient.post<DeliverableTemplateVersionRecord>(zenaPath(`/deliverable-templates/${id}/publish-version`))
  return ensureData(response)
}

export async function listDeliverableTemplateVersions(id: string, params?: { page?: number; per_page?: number }) {
  const response = await apiClient.get<DeliverableTemplateVersionRecord[]>(zenaPath(`/deliverable-templates/${id}/versions`), { params })
  return {
    items: ensureData(response),
    meta: (response.meta || {}) as PaginationMeta,
  }
}
