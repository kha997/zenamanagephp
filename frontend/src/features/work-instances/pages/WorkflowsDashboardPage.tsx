import { FormEvent, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import {
  getWorkInstanceMetrics,
  listWorkInstances,
  type WorkInstanceListParams,
  type WorkInstanceMetrics,
  type WorkInstanceRecord,
} from '@/features/work-instances/api'

type DashboardFilters = {
  project_id: string
  status: string
}

type PaginationMeta = {
  page: number
  per_page: number
  total: number
  last_page: number
}

const DEFAULT_FILTERS: DashboardFilters = {
  project_id: '',
  status: '',
}

const PAGE_SIZE = 15

function formatDate(value?: string | null): string {
  if (!value) {
    return '-'
  }

  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleString()
}

function toRequestParams(filters: DashboardFilters, page: number): WorkInstanceListParams {
  const params: WorkInstanceListParams = {
    page,
    per_page: PAGE_SIZE,
  }

  if (filters.project_id.trim() !== '') {
    params.project_id = filters.project_id.trim()
  }

  if (filters.status.trim() !== '') {
    params.status = filters.status.trim()
  }

  return params
}

export function WorkflowsDashboardPage() {
  const [filters, setFilters] = useState<DashboardFilters>(DEFAULT_FILTERS)
  const [appliedFilters, setAppliedFilters] = useState<DashboardFilters>(DEFAULT_FILTERS)
  const [items, setItems] = useState<WorkInstanceRecord[]>([])
  const [metrics, setMetrics] = useState<WorkInstanceMetrics | null>(null)
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false

    const load = async () => {
      setLoading(true)
      setError(null)

      try {
        const params = toRequestParams(appliedFilters, page)
        const [nextMetrics, nextList] = await Promise.all([
          getWorkInstanceMetrics({
            project_id: params.project_id,
            status: params.status,
          }),
          listWorkInstances(params),
        ])

        if (cancelled) {
          return
        }

        setMetrics(nextMetrics)
        setItems(nextList.items)
        setPagination(nextList.meta.pagination ?? null)
      } catch (err) {
        if (cancelled) {
          return
        }

        const message = err instanceof Error ? err.message : 'Cannot load workflow dashboard.'
        setError(message)
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    void load()

    return () => {
      cancelled = true
    }
  }, [appliedFilters, page])

  const statusOptions = useMemo(() => {
    const statuses = new Set<string>()

    metrics?.instances_by_status.forEach((item) => {
      if (item.status) {
        statuses.add(item.status)
      }
    })

    items.forEach((item) => {
      if (item.status) {
        statuses.add(item.status)
      }
    })

    return Array.from(statuses).sort((left, right) => left.localeCompare(right))
  }, [items, metrics])

  const onApplyFilters = (event: FormEvent) => {
    event.preventDefault()
    setPage(1)
    setAppliedFilters({
      project_id: filters.project_id.trim(),
      status: filters.status.trim(),
    })
  }

  const onResetFilters = () => {
    setFilters(DEFAULT_FILTERS)
    setAppliedFilters(DEFAULT_FILTERS)
    setPage(1)
  }

  const currentPage = pagination?.page ?? page
  const lastPage = pagination?.last_page ?? 1

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-2">
        <h1 className="text-2xl font-bold text-gray-900">Workflows</h1>
        <p className="text-sm text-gray-600">
          Global work instance reporting across the current tenant with metrics, overdue steps, and drilldown into detail.
        </p>
      </div>

      <Card className="p-4">
        <form className="grid gap-4 md:grid-cols-[minmax(0,1fr)_220px_auto_auto]" onSubmit={onApplyFilters}>
          <label className="space-y-2 text-sm text-gray-700">
            <span className="font-medium">Project ID</span>
            <input
              className="w-full rounded border border-gray-300 px-3 py-2"
              value={filters.project_id}
              onChange={(event) => setFilters((current) => ({ ...current, project_id: event.target.value }))}
              placeholder="Filter by project ULID"
            />
          </label>

          <label className="space-y-2 text-sm text-gray-700">
            <span className="font-medium">Status</span>
            <input
              className="w-full rounded border border-gray-300 px-3 py-2"
              list="workflow-status-options"
              value={filters.status}
              onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}
              placeholder="Any status"
            />
            <datalist id="workflow-status-options">
              {statusOptions.map((status) => (
                <option key={status} value={status} />
              ))}
            </datalist>
          </label>

          <div className="flex items-end">
            <Button className="w-full md:w-auto" type="submit">
              Apply
            </Button>
          </div>

          <div className="flex items-end">
            <Button className="w-full md:w-auto" type="button" variant="outline" onClick={onResetFilters}>
              Reset
            </Button>
          </div>
        </form>
      </Card>

      {error ? (
        <Card className="border-red-200 bg-red-50 p-4 text-sm text-red-700">
          {error}
        </Card>
      ) : null}

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card className="p-4">
          <p className="text-sm font-medium text-gray-500">Total Instances</p>
          <p className="mt-2 text-3xl font-semibold text-gray-900">{metrics?.total_instances ?? 0}</p>
        </Card>

        <Card className="p-4">
          <p className="text-sm font-medium text-gray-500">Overdue Steps</p>
          <p className="mt-2 text-3xl font-semibold text-red-600">{metrics?.overdue_steps ?? 0}</p>
        </Card>

        <Card className="p-4">
          <p className="text-sm font-medium text-gray-500">Total Steps</p>
          <p className="mt-2 text-3xl font-semibold text-gray-900">{metrics?.total_steps ?? 0}</p>
        </Card>

        <Card className="p-4">
          <p className="text-sm font-medium text-gray-500">Status Buckets</p>
          <p className="mt-2 text-3xl font-semibold text-gray-900">{metrics?.instances_by_status.length ?? 0}</p>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="p-4">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-lg font-semibold text-gray-900">Instances by Status</h2>
            {loading ? <span className="text-xs text-gray-500">Refreshing...</span> : null}
          </div>

          <div className="space-y-3">
            {(metrics?.instances_by_status ?? []).map((item) => (
              <div key={item.status || 'unknown'} className="rounded border border-gray-200 p-3">
                <div className="flex items-center justify-between gap-4">
                  <span className="text-sm font-medium text-gray-700">{item.status || 'unknown'}</span>
                  <span className="text-lg font-semibold text-gray-900">{item.count}</span>
                </div>
              </div>
            ))}
            {!loading && (metrics?.instances_by_status.length ?? 0) === 0 ? (
              <p className="text-sm text-gray-500">No instance metrics for the current filters.</p>
            ) : null}
          </div>
        </Card>

        <Card className="p-4">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-lg font-semibold text-gray-900">Steps by Status</h2>
            {loading ? <span className="text-xs text-gray-500">Refreshing...</span> : null}
          </div>

          <div className="space-y-3">
            {(metrics?.steps_by_status ?? []).map((item) => (
              <div key={item.status || 'unknown'} className="rounded border border-gray-200 p-3">
                <div className="flex items-center justify-between gap-4">
                  <span className="text-sm font-medium text-gray-700">{item.status || 'unknown'}</span>
                  <span className="text-lg font-semibold text-gray-900">{item.count}</span>
                </div>
              </div>
            ))}
            {!loading && (metrics?.steps_by_status.length ?? 0) === 0 ? (
              <p className="text-sm text-gray-500">No step metrics for the current filters.</p>
            ) : null}
          </div>
        </Card>
      </div>

      <Card className="overflow-hidden">
        <div className="border-b border-gray-200 px-4 py-4">
          <h2 className="text-lg font-semibold text-gray-900">Work Instance List</h2>
          <p className="text-sm text-gray-500">Click an instance to open the existing workflow execution detail page.</p>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Instance</th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Project</th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Template</th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Steps</th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Created</th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Updated</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 bg-white">
              {items.map((item) => (
                <tr key={item.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 align-top">
                    <Link className="text-sm font-medium text-blue-600 hover:underline" to={`/work-instances/${item.id}`}>
                      {item.id}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-700">{item.project_id || '-'}</td>
                  <td className="px-4 py-3 text-sm text-gray-700">
                    <div className="font-medium text-gray-900">{item.template?.name || '-'}</div>
                    <div className="text-xs text-gray-500">{item.template?.semver || '-'}</div>
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-700">{item.status}</td>
                  <td className="px-4 py-3 text-sm text-gray-700">{item.steps_count ?? 0}</td>
                  <td className="px-4 py-3 text-sm text-gray-700">{formatDate(item.created_at)}</td>
                  <td className="px-4 py-3 text-sm text-gray-700">{formatDate(item.updated_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {!loading && items.length === 0 ? (
          <div className="px-4 py-6 text-sm text-gray-500">No work instances found for the current filters.</div>
        ) : null}

        <div className="flex items-center justify-between border-t border-gray-200 px-4 py-4">
          <p className="text-sm text-gray-500">
            Showing page {currentPage} of {lastPage}
            {pagination ? `, ${pagination.total} total instances` : ''}
          </p>
          <div className="flex gap-2">
            <Button
              type="button"
              variant="outline"
              disabled={loading || currentPage <= 1}
              onClick={() => setPage((current) => Math.max(current - 1, 1))}
            >
              Previous
            </Button>
            <Button
              type="button"
              variant="outline"
              disabled={loading || currentPage >= lastPage}
              onClick={() => setPage((current) => current + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      </Card>
    </div>
  )
}
