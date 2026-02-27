import { ChangeEvent, useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { LoadingSpinner } from '@/components/ui/loading-spinner'
import {
  getDeliverableTemplate,
  listDeliverableTemplateVersions,
  publishDeliverableTemplateVersion,
  uploadDeliverableTemplateVersion,
  type DeliverableTemplateRecord,
  type DeliverableTemplateVersionRecord,
} from '@/features/deliverable-templates/api'

export function DeliverableTemplateDetailPage() {
  const { id } = useParams<{ id: string }>()

  const [template, setTemplate] = useState<DeliverableTemplateRecord | null>(null)
  const [versions, setVersions] = useState<DeliverableTemplateVersionRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [uploading, setUploading] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [rawHtml, setRawHtml] = useState('')
  const [selectedFile, setSelectedFile] = useState<File | null>(null)

  const loadData = async () => {
    if (!id) {
      return
    }

    const [record, versionsResult] = await Promise.all([
      getDeliverableTemplate(id),
      listDeliverableTemplateVersions(id, { per_page: 50 }),
    ])

    setTemplate(record)
    setVersions(versionsResult.items)
  }

  useEffect(() => {
    if (!id) {
      setError('Template id is missing')
      setLoading(false)
      return
    }

    let mounted = true
    setLoading(true)

    loadData()
      .then(() => {
        if (!mounted) {
          return
        }

        setError(null)
      })
      .catch((err: unknown) => {
        if (!mounted) {
          return
        }

        const message = err instanceof Error ? err.message : 'Failed to load deliverable template'
        setError(message)
      })
      .finally(() => {
        if (mounted) {
          setLoading(false)
        }
      })

    return () => {
      mounted = false
    }
  }, [id])

  const latestDraft = useMemo(
    () => versions.find((version) => version.semver === 'draft' && !version.published_at),
    [versions]
  )

  const onFileChange = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0] || null
    setSelectedFile(file)
  }

  const onUpload = async () => {
    if (!id) {
      return
    }

    if (!selectedFile && !rawHtml.trim()) {
      setError('Upload an HTML file or provide raw HTML text.')
      return
    }

    try {
      setUploading(true)
      await uploadDeliverableTemplateVersion(id, {
        file: selectedFile || undefined,
        html: selectedFile ? undefined : rawHtml,
      })
      setRawHtml('')
      setSelectedFile(null)
      await loadData()
      setError(null)
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to upload draft HTML version'
      setError(message)
    } finally {
      setUploading(false)
    }
  }

  const onPublish = async () => {
    if (!id) {
      return
    }

    if (!window.confirm('Publish current draft as an immutable semver version?')) {
      return
    }

    try {
      setPublishing(true)
      await publishDeliverableTemplateVersion(id)
      await loadData()
      setError(null)
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to publish deliverable template version'
      setError(message)
    } finally {
      setPublishing(false)
    }
  }

  if (loading) {
    return (
      <div className="flex h-40 items-center justify-center">
        <LoadingSpinner />
      </div>
    )
  }

  if (!template) {
    return (
      <Card className="p-6">
        <p className="text-sm text-gray-700">Deliverable template not found.</p>
        <Link className="mt-3 inline-block text-sm text-blue-600 hover:underline" to="/deliverable-templates">
          Back to list
        </Link>
      </Card>
    )
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Deliverable Template</h1>
          <p className="text-sm text-gray-600">
            {template.name} ({template.code}) | Status: {template.status}
          </p>
        </div>
        <Button onClick={onPublish} disabled={publishing || uploading || !latestDraft}>
          {publishing ? 'Publishing...' : 'Publish Version'}
        </Button>
      </div>

      {error ? <p className="text-sm text-red-600">{error}</p> : null}

      <Card className="space-y-4 p-4">
        <h2 className="text-lg font-semibold text-gray-900">Upload Draft HTML</h2>
        <div className="space-y-3">
          <label className="block text-sm text-gray-700">
            HTML File
            <input className="mt-1 block w-full text-sm" type="file" accept=".html,.htm,text/html" onChange={onFileChange} />
          </label>
          <label className="block text-sm text-gray-700">
            Or Raw HTML
            <textarea
              className="mt-1 h-40 w-full rounded border border-gray-300 px-3 py-2 font-mono text-xs"
              value={rawHtml}
              onChange={(event) => setRawHtml(event.target.value)}
              placeholder="<html><body>{{project.name}}</body></html>"
            />
          </label>
          <Button onClick={onUpload} disabled={uploading}>
            {uploading ? 'Uploading...' : 'Upload Draft Version'}
          </Button>
        </div>
      </Card>

      <Card className="p-4">
        <h2 className="mb-3 text-lg font-semibold text-gray-900">Versions</h2>
        {versions.length === 0 ? <p className="text-sm text-gray-500">No versions yet.</p> : null}

        <div className="space-y-2">
          {versions.map((version) => (
            <div key={version.id} className="rounded border border-gray-200 p-3">
              <p className="text-sm font-medium text-gray-900">
                {version.semver} {version.published_at ? '(published)' : '(draft)'}
              </p>
              <p className="text-xs text-gray-500">Checksum: {version.checksum_sha256}</p>
              <p className="text-xs text-gray-500">Mime: {version.mime} | Size: {version.size} bytes</p>
            </div>
          ))}
        </div>
      </Card>
    </div>
  )
}
