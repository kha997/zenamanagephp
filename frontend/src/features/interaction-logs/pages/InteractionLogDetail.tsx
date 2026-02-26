import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { InteractionLogsApi } from '../api/interactionLogsApi'

export const InteractionLogDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data, isLoading, isError } = useQuery({
    queryKey: ['interaction-log', id],
    queryFn: () => InteractionLogsApi.detail(id as string),
    enabled: Boolean(id),
  })

  const approveMutation = useMutation({
    mutationFn: () => InteractionLogsApi.approveForClient(id as string),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['interaction-log', id] })
    },
  })

  const deleteMutation = useMutation({
    mutationFn: () => InteractionLogsApi.delete(id as string),
    onSuccess: () => {
      navigate('/interaction-logs')
    },
  })

  if (!id) {
    return <p className="p-4 text-sm text-red-600">Invalid interaction log ID.</p>
  }

  if (isLoading) {
    return <p className="p-4 text-sm text-gray-500">Loading...</p>
  }

  if (isError || !data?.data) {
    return <p className="p-4 text-sm text-red-600">Failed to load interaction log.</p>
  }

  const log = data.data

  return (
    <div className="space-y-4 p-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900">Interaction Log #{log.id}</h1>
        <button
          onClick={() => navigate('/interaction-logs')}
          className="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        >
          Back
        </button>
      </div>

      <div className="space-y-2 rounded border border-gray-200 p-4 text-sm">
        <p>
          <span className="font-medium text-gray-700">Type:</span> {log.type}
        </p>
        <p>
          <span className="font-medium text-gray-700">Visibility:</span> {log.visibility}
        </p>
        <p>
          <span className="font-medium text-gray-700">Tag path:</span> {log.tag_path}
        </p>
        <p>
          <span className="font-medium text-gray-700">Description:</span> {log.description}
        </p>
      </div>

      <div className="flex items-center gap-2">
        <button
          onClick={() => approveMutation.mutate()}
          disabled={approveMutation.isPending}
          className="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-70"
        >
          {approveMutation.isPending ? 'Approving...' : 'Approve for client'}
        </button>

        <button
          onClick={() => deleteMutation.mutate()}
          disabled={deleteMutation.isPending}
          className="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-70"
        >
          {deleteMutation.isPending ? 'Deleting...' : 'Delete'}
        </button>
      </div>
    </div>
  )
}
