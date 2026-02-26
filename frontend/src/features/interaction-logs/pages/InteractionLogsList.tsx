import { useMemo } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { InteractionLogsApi } from '../api/interactionLogsApi'
import type { InteractionLog } from '../types/interactionLog'

export const InteractionLogsList: React.FC = () => {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()

  const page = Number(searchParams.get('page') ?? '1')

  const { data, isLoading, isError } = useQuery({
    queryKey: ['interaction-logs', page],
    queryFn: () => InteractionLogsApi.list({ page, per_page: 20 }),
  })

  const logs = useMemo<InteractionLog[]>(() => data?.data ?? [], [data])

  return (
    <div className="space-y-4 p-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">Interaction Logs</h1>
          <p className="text-sm text-gray-500">Danh sach interaction logs</p>
        </div>
        <button
          onClick={() => navigate('/interaction-logs/create')}
          className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          Create
        </button>
      </div>

      {isLoading && <p className="text-sm text-gray-500">Loading...</p>}
      {isError && <p className="text-sm text-red-600">Failed to load interaction logs.</p>}

      {!isLoading && !isError && logs.length === 0 && (
        <p className="rounded border border-dashed border-gray-300 p-4 text-sm text-gray-600">
          No interaction logs found.
        </p>
      )}

      {!isLoading && !isError && logs.length > 0 && (
        <ul className="divide-y divide-gray-200 rounded border border-gray-200">
          {logs.map((log) => (
            <li key={log.id} className="flex items-center justify-between p-4">
              <div>
                <p className="font-medium text-gray-900">{log.type_label}</p>
                <p className="text-sm text-gray-600">{log.description}</p>
              </div>
              <Link
                to={`/interaction-logs/${log.id}`}
                className="text-sm font-medium text-blue-600 hover:text-blue-700"
              >
                View detail
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
