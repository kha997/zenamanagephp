import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation } from '@tanstack/react-query'
import { InteractionLogsApi } from '../api/interactionLogsApi'
import type {
  CreateInteractionLogForm,
  InteractionLogType,
  InteractionLogVisibility,
} from '../types/interactionLog'

const interactionTypes: InteractionLogType[] = ['call', 'email', 'meeting', 'note', 'feedback']
const visibilityTypes: InteractionLogVisibility[] = ['internal', 'client']

export const CreateInteractionLog: React.FC = () => {
  const navigate = useNavigate()
  const [formData, setFormData] = useState<CreateInteractionLogForm>({
    project_id: '',
    linked_task_id: '',
    type: 'note',
    description: '',
    tag_path: '',
    visibility: 'internal',
    client_approved: false,
  })

  const createMutation = useMutation({
    mutationFn: (payload: CreateInteractionLogForm) => InteractionLogsApi.create(payload),
    onSuccess: (response) => {
      navigate(`/interaction-logs/${response.data.id}`)
    },
  })

  const submit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    createMutation.mutate({
      ...formData,
      linked_task_id: formData.linked_task_id || undefined,
    })
  }

  return (
    <div className="p-4">
      <h1 className="mb-4 text-2xl font-semibold text-gray-900">Create Interaction Log</h1>

      <form onSubmit={submit} className="space-y-4 rounded border border-gray-200 p-4">
        <label className="block text-sm">
          <span className="mb-1 block font-medium text-gray-700">Project ID</span>
          <input
            required
            value={formData.project_id}
            onChange={(e) => setFormData((prev) => ({ ...prev, project_id: e.target.value }))}
            className="w-full rounded border border-gray-300 px-3 py-2"
          />
        </label>

        <label className="block text-sm">
          <span className="mb-1 block font-medium text-gray-700">Type</span>
          <select
            value={formData.type}
            onChange={(e) => setFormData((prev) => ({ ...prev, type: e.target.value as InteractionLogType }))}
            className="w-full rounded border border-gray-300 px-3 py-2"
          >
            {interactionTypes.map((type) => (
              <option key={type} value={type}>
                {type}
              </option>
            ))}
          </select>
        </label>

        <label className="block text-sm">
          <span className="mb-1 block font-medium text-gray-700">Visibility</span>
          <select
            value={formData.visibility}
            onChange={(e) =>
              setFormData((prev) => ({ ...prev, visibility: e.target.value as InteractionLogVisibility }))
            }
            className="w-full rounded border border-gray-300 px-3 py-2"
          >
            {visibilityTypes.map((visibility) => (
              <option key={visibility} value={visibility}>
                {visibility}
              </option>
            ))}
          </select>
        </label>

        <label className="block text-sm">
          <span className="mb-1 block font-medium text-gray-700">Description</span>
          <textarea
            required
            value={formData.description}
            onChange={(e) => setFormData((prev) => ({ ...prev, description: e.target.value }))}
            className="min-h-24 w-full rounded border border-gray-300 px-3 py-2"
          />
        </label>

        <label className="block text-sm">
          <span className="mb-1 block font-medium text-gray-700">Tag path</span>
          <input
            required
            value={formData.tag_path}
            onChange={(e) => setFormData((prev) => ({ ...prev, tag_path: e.target.value }))}
            className="w-full rounded border border-gray-300 px-3 py-2"
          />
        </label>

        <label className="block text-sm">
          <span className="mb-1 block font-medium text-gray-700">Linked task ID (optional)</span>
          <input
            value={formData.linked_task_id ?? ''}
            onChange={(e) => setFormData((prev) => ({ ...prev, linked_task_id: e.target.value }))}
            className="w-full rounded border border-gray-300 px-3 py-2"
          />
        </label>

        <div className="flex items-center gap-2">
          <button
            type="submit"
            disabled={createMutation.isPending}
            className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70"
          >
            {createMutation.isPending ? 'Saving...' : 'Save'}
          </button>
          <button
            type="button"
            onClick={() => navigate('/interaction-logs')}
            className="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  )
}
