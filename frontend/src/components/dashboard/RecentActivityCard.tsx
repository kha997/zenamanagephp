import React from 'react'
import { motion } from 'framer-motion'
import { Card, CardHeader, CardTitle, CardContent } from '../ui/Card'
import { Skeleton } from '../ui/Skeleton'
import { Activity, ArrowRight } from 'lucide-react'
import { Button } from '../ui/Button'
import { cn } from '../../lib/utils'

export interface Activity {
  id: string
  type: string
  action: string
  description: string
  timestamp: string
  user?: {
    id: string
    name: string
    avatar?: string
  }
}

interface RecentActivityCardProps {
  activities?: Activity[]
  loading?: boolean
  error?: Error | null
  dataTestId?: string
  onViewAll?: () => void
}

const timeAgo = (date: string) => {
  const now = new Date()
  const activityDate = new Date(date)
  const diffInMinutes = Math.floor((now.getTime() - activityDate.getTime()) / (1000 * 60))

  if (diffInMinutes < 1) return 'Just now'
  if (diffInMinutes < 60) return `${diffInMinutes}m ago`
  
  const diffInHours = Math.floor(diffInMinutes / 60)
  if (diffInHours < 24) return `${diffInHours}h ago`
  
  const diffInDays = Math.floor(diffInHours / 24)
  return `${diffInDays}d ago`
}

const getActivityIcon = (type: string) => {
  switch (type) {
    case 'project':
      return '📁'
    case 'task':
      return '✓'
    case 'user':
      return '👤'
    case 'comment':
      return '💬'
    default:
      return '📌'
  }
}

export const RecentActivityCard: React.FC<RecentActivityCardProps> = ({
  activities = [],
  loading = false,
  error = null,
  dataTestId = 'activity-feed-widget',
  onViewAll
}) => {
  return (
    <Card data-testid={dataTestId}>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Activity className="h-5 w-5 text-primary-600" />
            <CardTitle>Recent Activity</CardTitle>
          </div>
          {onViewAll && (
            <Button
              variant="ghost"
              size="sm"
              onClick={onViewAll}
              className="text-xs"
              data-testid={`${dataTestId}-view-all`}
            >
              View all
              <ArrowRight className="h-3 w-3 ml-1" />
            </Button>
          )}
        </div>
        <p className="text-sm text-muted-foreground">
          Latest updates and changes across your workspace
        </p>
      </CardHeader>
      <CardContent>
        {loading && (
          <div className="space-y-4">
            {Array.from({ length: 5 }).map((_, i) => (
              <div key={i} className="flex items-start gap-4">
                <Skeleton variant="circular" width={32} height={32} />
                <div className="flex-1">
                  <Skeleton variant="text" className="mb-2" />
                  <Skeleton variant="text" width="40%" />
                </div>
                <Skeleton width={80} height={20} />
              </div>
            ))}
          </div>
        )}

        {error && (
          <div className="text-center py-8">
            <p className="text-sm text-muted-foreground">Failed to load activity</p>
          </div>
        )}

        {!loading && !error && activities.length > 0 && (
          <div className="space-y-4">
            {activities.slice(0, 10).map((activity, index) => (
              <motion.div
                key={activity.id}
                initial={{ opacity: 0, x: -20 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: index * 0.05 }}
                className="flex items-start gap-3"
              >
                <div className="flex-shrink-0">
                  <div className="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center text-lg">
                    {getActivityIcon(activity.type)}
                  </div>
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-foreground">{activity.description}</p>
                  <div className="flex items-center gap-2 mt-1">
                    {activity.user && (
                      <span className="text-sm text-muted-foreground">{activity.user.name}</span>
                    )}
                    <span className="text-xs text-muted-foreground">
                      {timeAgo(activity.timestamp)}
                    </span>
                  </div>
                </div>
              </motion.div>
            ))}
          </div>
        )}

        {!loading && !error && activities.length === 0 && (
          <div className="text-center py-8">
            <Activity className="h-12 w-12 text-muted-foreground mx-auto mb-4 opacity-50" />
            <p className="text-sm text-muted-foreground">No recent activity</p>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

