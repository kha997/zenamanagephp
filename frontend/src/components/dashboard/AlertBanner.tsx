import React from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { X, AlertCircle, AlertTriangle, Info, CheckCircle } from 'lucide-react'
import { Button } from '../ui/Button'
import { Badge } from '../ui/Badge'
import { cn } from '../../lib/utils'

export interface Alert {
  id: string
  message: string
  severity: 'info' | 'warning' | 'error' | 'success'
  type?: string
  timestamp?: string
}

interface AlertBannerProps {
  alerts: Alert[]
  loading?: boolean
  onDismiss?: (alertId: string) => void
  onDismissAll?: () => void
  dataTestId?: string
}

const severityConfig = {
  info: {
    icon: Info,
    color: 'bg-blue-50 border-blue-200 text-blue-800',
    badgeColor: 'bg-blue-100 text-blue-800',
    iconColor: 'text-blue-600'
  },
  warning: {
    icon: AlertTriangle,
    color: 'bg-yellow-50 border-yellow-200 text-yellow-800',
    badgeColor: 'bg-yellow-100 text-yellow-800',
    iconColor: 'text-yellow-600'
  },
  error: {
    icon: AlertCircle,
    color: 'bg-red-50 border-red-200 text-red-800',
    badgeColor: 'bg-red-100 text-red-800',
    iconColor: 'text-red-600'
  },
  success: {
    icon: CheckCircle,
    color: 'bg-green-50 border-green-200 text-green-800',
    badgeColor: 'bg-green-100 text-green-800',
    iconColor: 'text-green-600'
  }
}

export const AlertBanner: React.FC<AlertBannerProps> = ({
  alerts,
  loading = false,
  onDismiss,
  onDismissAll,
  dataTestId = 'alert-banner'
}) => {
  if (loading && alerts.length === 0) {
    return null // Don't show skeleton for alerts banner
  }

  if (alerts.length === 0) {
    return null
  }

  const severityCounts = alerts.reduce((acc, alert) => {
    acc[alert.severity] = (acc[alert.severity] || 0) + 1
    return acc
  }, {} as Record<string, number>)

  const mostSevere = alerts.length > 0
    ? alerts.reduce((prev, current) => {
        const severityOrder = { error: 3, warning: 2, info: 1, success: 0 }
        return severityOrder[current.severity] > severityOrder[prev.severity] ? current : prev
      }, alerts[0])
    : null

  if (!mostSevere) return null

  const config = severityConfig[mostSevere.severity]
  const Icon = config.icon

  return (
    <motion.div
      initial={{ opacity: 0, y: -20 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -20 }}
      data-testid={dataTestId}
      className={cn(
        'border-b',
        config.color
      )}
    >
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Icon className={cn('h-5 w-5', config.iconColor)} />
            <div className="flex items-center gap-2">
              <span className="font-medium">
                {alerts.length} {alerts.length === 1 ? 'alert' : 'alerts'} require attention
              </span>
              <div className="flex gap-1">
                {Object.entries(severityCounts).map(([severity, count]) => (
                  <Badge
                    key={severity}
                    variant="secondary"
                    className={severityConfig[severity as keyof typeof severityConfig].badgeColor}
                  >
                    {count} {severity}
                  </Badge>
                ))}
              </div>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={onDismissAll}
              data-testid={`${dataTestId}-dismiss-all`}
            >
              Dismiss All
            </Button>
          </div>
        </div>
      </div>
    </motion.div>
  )
}

