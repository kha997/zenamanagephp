import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { apiClient } from '@/lib/api/client'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { useAuthStore } from '@/store/auth.store'
import { Link } from 'react-router-dom'
import toast from 'react-hot-toast'

type SecuritySettings = {
  passwordMinLength?: number
  passwordRequireUppercase?: boolean
  passwordRequireNumbers?: boolean
  passwordRequireSymbols?: boolean
  maxLoginAttempts?: number
  lockoutDuration?: number
  twoFactorEnabled?: boolean
  ipWhitelist?: string[]
  sessionSecure?: boolean
}

const SETTINGS_ENDPOINT = '/settings/security'
const FIELD_ORDER: Array<keyof SecuritySettings> = [
  'passwordMinLength',
  'passwordRequireUppercase',
  'passwordRequireNumbers',
  'passwordRequireSymbols',
  'maxLoginAttempts',
  'lockoutDuration',
  'twoFactorEnabled',
  'ipWhitelist',
  'sessionSecure',
]

const LABELS: Record<keyof SecuritySettings, string> = {
  passwordMinLength: 'Password minimum length',
  passwordRequireUppercase: 'Require uppercase',
  passwordRequireNumbers: 'Require numbers',
  passwordRequireSymbols: 'Require symbols',
  maxLoginAttempts: 'Max login attempts',
  lockoutDuration: 'Lockout duration (minutes)',
  twoFactorEnabled: 'Two-factor authentication',
  ipWhitelist: 'IP whitelist',
  sessionSecure: 'Secure session only',
}

const unwrapPayload = (response: any): unknown => {
  const first = response?.data !== undefined ? response.data : response
  return first?.data !== undefined ? first.data : first
}

const isObject = (value: unknown): value is Record<string, unknown> =>
  value !== null && typeof value === 'object' && !Array.isArray(value)

const resolveErrorMessage = (error: any): string => {
  const status = error?.status ?? error?.response?.status
  if (status === 403 || status === 404) {
    return 'Not found or no access.'
  }
  if (status === 422) {
    const errors = error?.data?.errors ?? error?.response?.data?.errors
    if (isObject(errors)) {
      const first = Object.values(errors)[0]
      if (Array.isArray(first) && typeof first[0] === 'string') {
        return first[0]
      }
      if (typeof first === 'string') {
        return first
      }
    }
  }
  return error?.message ?? 'Unable to process security settings.'
}

const toSecuritySettings = (payload: unknown): SecuritySettings => {
  if (!isObject(payload)) {
    return {}
  }

  const settings: SecuritySettings = {}
  for (const key of FIELD_ORDER) {
    if (payload[key] !== undefined) {
      settings[key] = payload[key] as never
    }
  }
  return settings
}

export const SecuritySettingsPage: React.FC = () => {
  const user = useAuthStore((state) => state.user)
  const canReadSettings = user?.permissions?.includes('settings.security.read') ?? false
  const canManageSettings = user?.permissions?.includes('settings.security.update') ?? false

  const [settings, setSettings] = useState<SecuritySettings>({})
  const [initialSettings, setInitialSettings] = useState<SecuritySettings>({})
  const [allowedKeys, setAllowedKeys] = useState<Array<keyof SecuritySettings>>([])
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [saveError, setSaveError] = useState<string | null>(null)
  const [saveSuccess, setSaveSuccess] = useState<string | null>(null)
  const [hasNoAccess, setHasNoAccess] = useState(false)

  const hasChanges = useMemo(() => {
    return allowedKeys.some((key) => JSON.stringify(settings[key]) !== JSON.stringify(initialSettings[key]))
  }, [allowedKeys, settings, initialSettings])

  const loadSettings = useCallback(async () => {
    if (!canReadSettings) {
      setIsLoading(false)
      return
    }

    setIsLoading(true)
    setLoadError(null)
    setSaveSuccess(null)
    setHasNoAccess(false)

    try {
      const response = await apiClient.get<any>(SETTINGS_ENDPOINT)
      const payload = toSecuritySettings(unwrapPayload(response))
      const nextAllowedKeys = FIELD_ORDER.filter((key) => payload[key] !== undefined)
      setAllowedKeys(nextAllowedKeys)
      setSettings(payload)
      setInitialSettings(payload)
    } catch (error: any) {
      if ((error?.status ?? error?.response?.status) === 403) {
        setHasNoAccess(true)
      } else {
        setLoadError(resolveErrorMessage(error))
      }
      setAllowedKeys([])
      setSettings({})
      setInitialSettings({})
    } finally {
      setIsLoading(false)
    }
  }, [canReadSettings])

  useEffect(() => {
    void loadSettings()
  }, [loadSettings])

  const updateField = (key: keyof SecuritySettings, value: string | boolean | number | string[]): void => {
    setSettings((prev) => ({ ...prev, [key]: value }))
  }

  const handleSave = async (): Promise<void> => {
    if (!canManageSettings) {
      setSaveError('No access to manage security settings.')
      return
    }

    const patchPayload: Record<string, unknown> = {}
    for (const key of allowedKeys) {
      if (JSON.stringify(settings[key]) !== JSON.stringify(initialSettings[key])) {
        patchPayload[key] = settings[key]
      }
    }

    if (Object.keys(patchPayload).length === 0) {
      setSaveSuccess('No changes to save.')
      return
    }

    setIsSaving(true)
    setSaveError(null)
    setSaveSuccess(null)

    try {
      const response = await apiClient.patch<any>(SETTINGS_ENDPOINT, patchPayload)
      const payload = toSecuritySettings(unwrapPayload(response))
      const nextAllowedKeys = FIELD_ORDER.filter((key) => payload[key] !== undefined)
      setAllowedKeys(nextAllowedKeys)
      setSettings(payload)
      setInitialSettings(payload)
      setSaveSuccess('Security settings saved.')
      toast.success('Security settings saved.')
    } catch (error: any) {
      const message = resolveErrorMessage(error)
      setSaveError(message)
      toast.error(message)
    } finally {
      setIsSaving(false)
    }
  }

  const renderField = (key: keyof SecuritySettings): React.ReactNode => {
    const value = settings[key]
    const disabled = isSaving || !canManageSettings

    if (typeof value === 'boolean') {
      return (
        <input
          type="checkbox"
          className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          checked={value}
          disabled={disabled}
          onChange={(event) => updateField(key, event.target.checked)}
        />
      )
    }

    if (Array.isArray(value)) {
      return (
        <textarea
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          rows={3}
          disabled={disabled}
          value={value.join('\n')}
          onChange={(event) => updateField(
            key,
            event.target.value
              .split('\n')
              .map((line) => line.trim())
              .filter(Boolean)
          )}
        />
      )
    }

    if (typeof value === 'number') {
      return (
        <input
          type="number"
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          disabled={disabled}
          value={Number.isFinite(value) ? value : 0}
          onChange={(event) => updateField(key, Number(event.target.value))}
        />
      )
    }

    return (
      <p className="text-sm text-gray-600">
        {value === undefined || value === null ? 'No value' : String(value)}
      </p>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2 text-sm">
        <Link to="/settings/general" className="text-gray-600 hover:text-blue-600">General</Link>
        <span className="text-gray-400">|</span>
        <Link to="/settings/security" className="font-semibold text-blue-600">Security</Link>
        <span className="text-gray-400">|</span>
        <Link to="/settings/notifications" className="text-gray-600 hover:text-blue-600">Notifications</Link>
      </div>

      <div>
        <h1 className="text-2xl font-bold text-gray-900">Security Settings</h1>
        <p className="text-gray-600">Load and save security preferences from the v1 settings API.</p>
      </div>

      {(!canReadSettings || hasNoAccess) && (
        <Card className="p-6">
          <p className="text-sm font-medium text-red-700">No access.</p>
          <p className="mt-1 text-sm text-gray-600">You do not have permission `settings.security.read` for this page.</p>
        </Card>
      )}

      {loadError && (
        <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{loadError}</div>
      )}

      {saveError && (
        <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{saveError}</div>
      )}

      {saveSuccess && (
        <div className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-700">{saveSuccess}</div>
      )}

      {canReadSettings && !hasNoAccess && (
        <Card className="space-y-4 p-6">
          {isLoading && (
            <div className="space-y-4">
              <Skeleton height={20} width="30%" />
              {Array.from({ length: 4 }).map((_, index) => (
                <div key={index} className="rounded-md border border-gray-200 p-4">
                  <div className="flex items-center justify-between gap-3">
                    <Skeleton height={16} width="40%" />
                    <Skeleton height={20} width="35%" />
                  </div>
                </div>
              ))}
            </div>
          )}

          {!isLoading && allowedKeys.length === 0 && (
            <p className="text-sm text-gray-600">No security settings were returned by the API.</p>
          )}

          {!isLoading && allowedKeys.map((key) => (
            <div key={key} className="rounded-md border border-gray-200 p-4">
              <div className="mb-2 text-sm font-medium text-gray-900">{LABELS[key]}</div>
              {renderField(key)}
            </div>
          ))}
        </Card>
      )}

      {canReadSettings && !hasNoAccess && (
        <div className="flex justify-end gap-3">
          <Button variant="outline" onClick={() => void loadSettings()} disabled={isLoading || isSaving}>
            Reload
          </Button>
          <Button
            onClick={() => void handleSave()}
            disabled={isLoading || isSaving || !canManageSettings || allowedKeys.length === 0 || !hasChanges}
          >
            {isSaving ? 'Saving...' : 'Save settings'}
          </Button>
        </div>
      )}
    </div>
  )
}
