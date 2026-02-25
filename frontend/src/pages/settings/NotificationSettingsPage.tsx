import React, { useCallback, useEffect, useState } from 'react'
import { apiClient } from '@/lib/api/client'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'

type SettingsObject = Record<string, any>

const SETTINGS_ENDPOINT = '/settings/notifications'

const isPlainObject = (value: unknown): value is SettingsObject =>
  value !== null && typeof value === 'object' && !Array.isArray(value)

const toLabel = (key: string): string =>
  key
    .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
    .replace(/[_-]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/^\w/, (char) => char.toUpperCase())

const unwrapPayload = (response: any): unknown => {
  const first = response?.data !== undefined ? response.data : response
  return first?.data !== undefined ? first.data : first
}

const extractValidationMessage = (errorData: any): string | null => {
  const errors = errorData?.errors ?? errorData?.data?.errors
  if (isPlainObject(errors)) {
    for (const value of Object.values(errors)) {
      if (Array.isArray(value) && typeof value[0] === 'string') {
        return value[0]
      }
      if (typeof value === 'string') {
        return value
      }
    }
  }
  return null
}

const resolveErrorMessage = (error: any): string => {
  const status = error?.status ?? error?.response?.status

  if (status === 403 || status === 404) {
    return 'Not found or no access.'
  }

  if (status === 422) {
    return (
      extractValidationMessage(error?.data ?? error?.response?.data) ??
      error?.message ??
      'Validation failed.'
    )
  }

  return error?.message ?? 'Unable to process notification settings.'
}

const setByPath = (object: SettingsObject, path: string[], nextValue: unknown): SettingsObject => {
  if (path.length === 0) {
    return object
  }

  const [head, ...tail] = path
  if (tail.length === 0) {
    return { ...object, [head]: nextValue }
  }

  const current = isPlainObject(object[head]) ? object[head] : {}
  return { ...object, [head]: setByPath(current, tail, nextValue) }
}

export const NotificationSettingsPage: React.FC = () => {
  const [settings, setSettings] = useState<SettingsObject>({})
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [saveError, setSaveError] = useState<string | null>(null)
  const [saveSuccess, setSaveSuccess] = useState<string | null>(null)

  const loadSettings = useCallback(async () => {
    setIsLoading(true)
    setLoadError(null)
    setSaveSuccess(null)

    try {
      const response = await apiClient.get<any>(SETTINGS_ENDPOINT)
      const payload = unwrapPayload(response)
      setSettings(isPlainObject(payload) ? payload : {})
    } catch (error: any) {
      setLoadError(resolveErrorMessage(error))
      setSettings({})
    } finally {
      setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadSettings()
  }, [loadSettings])

  const handleBooleanChange = (path: string[], checked: boolean): void => {
    setSettings((prev) => setByPath(prev, path, checked))
  }

  const handleSave = async (): Promise<void> => {
    setIsSaving(true)
    setSaveError(null)
    setSaveSuccess(null)

    try {
      const response = await apiClient.patch<any>(SETTINGS_ENDPOINT, settings)
      const payload = unwrapPayload(response)
      if (isPlainObject(payload)) {
        setSettings(payload)
      }
      setSaveSuccess('Notification settings saved.')
    } catch (error: any) {
      setSaveError(resolveErrorMessage(error))
    } finally {
      setIsSaving(false)
    }
  }

  const renderValue = (value: unknown, path: string[]): React.ReactNode => {
    if (typeof value === 'boolean') {
      return (
        <label className="inline-flex cursor-pointer items-center">
          <input
            type="checkbox"
            className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            checked={value}
            onChange={(event) => handleBooleanChange(path, event.target.checked)}
          />
        </label>
      )
    }

    if (isPlainObject(value)) {
      const entries = Object.entries(value)
      return (
        <div className="mt-3 space-y-3 rounded-md border border-gray-200 p-3">
          {entries.length === 0 && <p className="text-sm text-gray-500">No editable fields.</p>}
          {entries.map(([childKey, childValue]) => (
            <div key={childKey} className="flex items-center justify-between gap-3">
              <div>
                <p className="text-sm font-medium text-gray-900">{toLabel(childKey)}</p>
              </div>
              {renderValue(childValue, [...path, childKey])}
            </div>
          ))}
        </div>
      )
    }

    return <p className="text-sm text-gray-500">{String(value)}</p>
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Notification Settings</h1>
        <p className="text-gray-600">Load and save notification preferences from the v1 settings API.</p>
      </div>

      {loadError && (
        <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{loadError}</div>
      )}

      {saveError && (
        <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{saveError}</div>
      )}

      {saveSuccess && (
        <div className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-700">{saveSuccess}</div>
      )}

      <Card className="space-y-4 p-6">
        {isLoading && <p className="text-sm text-gray-600">Loading settings...</p>}

        {!isLoading && Object.keys(settings).length === 0 && (
          <p className="text-sm text-gray-600">No notification settings were returned by the API.</p>
        )}

        {!isLoading &&
          Object.entries(settings).map(([key, value]) => (
            <div key={key} className="rounded-md border border-gray-200 p-4">
              <div className="flex items-center justify-between gap-3">
                <p className="font-medium text-gray-900">{toLabel(key)}</p>
                {!isPlainObject(value) && renderValue(value, [key])}
              </div>
              {isPlainObject(value) && renderValue(value, [key])}
            </div>
          ))}
      </Card>

      <div className="flex justify-end gap-3">
        <Button variant="outline" onClick={() => void loadSettings()} disabled={isLoading || isSaving}>
          Reload
        </Button>
        <Button onClick={() => void handleSave()} disabled={isLoading || isSaving || !Object.keys(settings).length}>
          {isSaving ? 'Saving...' : 'Save settings'}
        </Button>
      </div>
    </div>
  )
}
