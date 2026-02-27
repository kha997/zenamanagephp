import { FormEvent, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { getCachedWorkInstance } from '@/features/work-instances/api'

export function WorkInstancesListPage() {
  const navigate = useNavigate()
  const [instanceId, setInstanceId] = useState('')

  const cachedItems = useMemo(() => {
    const raw = localStorage.getItem('work_instance_cache_v1')
    if (!raw) {
      return [] as Array<{ id: string; status: string; project_id?: string }>
    }

    const map: Record<string, { id: string; status: string; project_id?: string }> = JSON.parse(raw)
    return Object.values(map)
  }, [])

  const onOpen = (event: FormEvent) => {
    event.preventDefault()
    if (!instanceId.trim()) {
      return
    }
    navigate(`/work-instances/${instanceId.trim()}`)
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Work Instances</h1>
        <p className="text-sm text-gray-600">Open an instance by ID, or continue from recent applied instances.</p>
      </div>

      <Card className="p-4">
        <form className="flex gap-2" onSubmit={onOpen}>
          <input
            className="w-full rounded border border-gray-300 px-3 py-2"
            value={instanceId}
            onChange={(event) => setInstanceId(event.target.value)}
            placeholder="Paste work instance id"
          />
          <Button type="submit">Open</Button>
        </form>
      </Card>

      <Card className="p-4">
        <h2 className="mb-3 text-lg font-semibold text-gray-900">Recent Applied</h2>
        {cachedItems.length === 0 ? <p className="text-sm text-gray-500">No cached instances yet.</p> : null}
        <div className="space-y-2">
          {cachedItems.map((item) => (
            <div key={item.id} className="flex items-center justify-between rounded border border-gray-200 p-3">
              <div>
                <p className="text-sm font-medium text-gray-900">{item.id}</p>
                <p className="text-xs text-gray-500">
                  status: {item.status} | project: {item.project_id || '-'}
                </p>
              </div>
              <Link className="text-sm text-blue-600 hover:underline" to={`/work-instances/${item.id}`}>
                Open
              </Link>
            </div>
          ))}
        </div>
      </Card>

      {instanceId && !getCachedWorkInstance(instanceId) ? (
        <p className="text-xs text-gray-500">If direct load fails, open from Apply flow to include snapshot fields.</p>
      ) : null}
    </div>
  )
}
