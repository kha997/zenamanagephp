import { useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, ExternalLink } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { LoadingSpinner } from '@/components/ui/loading-spinner'
import { apiClient } from '@/lib/api/client'
import { applyTemplateToProject, listWorkTemplates } from '@/features/work-templates/api'
import { cacheWorkInstance, listProjectWorkInstances, type WorkInstanceRecord } from '@/features/work-instances/api'

type ProjectRecord = {
  id: string
  name: string
  description?: string | null
  status?: string
  updated_at?: string
}

const formatDateTime = (value?: string) => {
  if (!value) {
    return '-'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  return date.toLocaleString('vi-VN')
}

export function ProjectDetailRoutePage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [selectedTemplateId, setSelectedTemplateId] = useState('')
  const [applyError, setApplyError] = useState<string | null>(null)

  const projectQuery = useQuery({
    queryKey: ['project-detail', id],
    enabled: Boolean(id),
    queryFn: async () => {
      const response = await apiClient.get<{ project?: ProjectRecord } | ProjectRecord>(`/api/zena/projects/${id}`)
      const payload = response.data

      if (!payload) {
        throw new Error('Project not found')
      }

      if ('project' in payload && payload.project) {
        return payload.project
      }

      return payload as unknown as ProjectRecord
    },
  })

  const workInstancesQuery = useQuery({
    queryKey: ['project-work-instances', id],
    enabled: Boolean(id),
    queryFn: async () => {
      return await listProjectWorkInstances(String(id), { per_page: 50 })
    },
  })

  const templatesQuery = useQuery({
    queryKey: ['work-templates-for-project-apply'],
    queryFn: async () => {
      const result = await listWorkTemplates({ per_page: 100 })
      return result.items.filter((template) => template.status === 'published')
    },
  })

  const applyMutation = useMutation({
    mutationFn: async () => {
      if (!id || !selectedTemplateId) {
        throw new Error('Work template is required')
      }

      return await applyTemplateToProject(id, selectedTemplateId)
    },
    onSuccess: async (instance) => {
      const cacheRecord: WorkInstanceRecord = {
        id: instance.id,
        status: instance.status,
        project_id: instance.project_id,
        steps: (instance.steps || []).map((step) => ({
          id: step.id,
          step_key: step.step_key,
          name: step.name,
          type: step.type,
          status: step.status,
          snapshot_fields_json: step.snapshot_fields_json,
        })),
      }

      cacheWorkInstance(cacheRecord)
      setApplyError(null)
      await queryClient.invalidateQueries({ queryKey: ['project-work-instances', id] })
      navigate(`/work-instances/${instance.id}`)
    },
    onError: (error: unknown) => {
      const message = error instanceof Error ? error.message : 'Failed to apply template'
      setApplyError(message)
    },
  })

  const isLoading = projectQuery.isLoading || workInstancesQuery.isLoading

  const project = projectQuery.data
  const workflowItems = useMemo(() => workInstancesQuery.data?.items || [], [workInstancesQuery.data?.items])
  const templates = useMemo(() => templatesQuery.data || [], [templatesQuery.data])

  if (isLoading) {
    return (
      <div className="flex h-40 items-center justify-center">
        <LoadingSpinner />
      </div>
    )
  }

  if (!project) {
    return (
      <Card className="p-6">
        <p className="text-sm text-gray-700">Project not found.</p>
        <button className="mt-3 text-sm text-blue-600 hover:underline" onClick={() => navigate('/projects')}>
          Back to projects
        </button>
      </Card>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-3">
        <div>
          <button
            className="mb-2 inline-flex items-center text-sm text-blue-600 hover:underline"
            onClick={() => navigate('/projects')}
          >
            <ArrowLeft className="mr-1 h-4 w-4" />
            Back to projects
          </button>
          <h1 className="text-2xl font-bold text-gray-900">{project.name}</h1>
          <p className="mt-1 text-sm text-gray-600">{project.description || 'No description provided.'}</p>
        </div>
        <Link className="text-sm text-blue-600 hover:underline" to="/work-templates">
          Manage templates
        </Link>
      </div>

      <Card className="p-5">
        <h2 className="text-lg font-semibold text-gray-900">Workflows</h2>
        <p className="mt-1 text-sm text-gray-600">Apply a published workflow template directly to this project.</p>

        <div className="mt-4 flex flex-col gap-3 md:flex-row md:items-center">
          <select
            className="w-full rounded border border-gray-300 px-3 py-2 text-sm md:max-w-md"
            value={selectedTemplateId}
            onChange={(event) => setSelectedTemplateId(event.target.value)}
          >
            <option value="">Select published template</option>
            {templates.map((template) => (
              <option key={template.id} value={template.id}>
                {template.name} ({template.code})
              </option>
            ))}
          </select>

          <Button
            onClick={() => applyMutation.mutate()}
            disabled={!selectedTemplateId || applyMutation.isPending}
          >
            {applyMutation.isPending ? 'Applying...' : 'Apply Template'}
          </Button>
        </div>

        {applyError ? <p className="mt-3 text-sm text-red-600">{applyError}</p> : null}
      </Card>

      <Card className="p-5">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-gray-900">Project Work Instances</h3>
          {workInstancesQuery.isFetching ? <span className="text-xs text-gray-500">Refreshing...</span> : null}
        </div>

        {workflowItems.length === 0 ? (
          <p className="mt-3 text-sm text-gray-600">No work instances yet for this project.</p>
        ) : (
          <div className="mt-4 space-y-3">
            {workflowItems.map((instance) => (
              <div key={instance.id} className="rounded border border-gray-200 p-3">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-medium text-gray-900">{instance.template?.name || 'Template'} {instance.template?.semver ? `(${instance.template.semver})` : ''}</p>
                    <p className="text-xs text-gray-500">ID: {instance.id}</p>
                    <p className="text-xs text-gray-500">Steps: {instance.steps_count} | Created: {formatDateTime(instance.created_at)}</p>
                  </div>
                  <div className="flex items-center gap-3">
                    <span className="rounded bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">{instance.status}</span>
                    <Link className="inline-flex items-center text-sm text-blue-600 hover:underline" to={`/work-instances/${instance.id}`}>
                      Open
                      <ExternalLink className="ml-1 h-3.5 w-3.5" />
                    </Link>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  )
}
