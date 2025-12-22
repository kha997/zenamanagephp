import React from 'react'
import { motion } from 'framer-motion'
import { Card, CardHeader, CardTitle, CardContent } from '../ui/Card'
import { Skeleton } from '../ui/Skeleton'
import { FolderOpen } from 'lucide-react'
import { cn } from '../../lib/utils'

export interface Project {
  id: string
  name: string
  status: string
  progress: number
  updated_at: string
}

interface RecentProjectsCardProps {
  projects?: Project[]
  loading?: boolean
  error?: Error | null
  dataTestId?: string
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'completed':
      return 'bg-green-400'
    case 'active':
      return 'bg-yellow-400'
    case 'planning':
      return 'bg-blue-400'
    case 'on_hold':
      return 'bg-orange-400'
    case 'cancelled':
      return 'bg-red-400'
    default:
      return 'bg-gray-400'
  }
}

const getStatusText = (status: string, progress?: number) => {
  switch (status) {
    case 'completed':
      return 'Project completed successfully'
    case 'active':
      return `In progress - ${progress}% complete`
    case 'planning':
      return 'Planning phase started'
    case 'on_hold':
      return 'Project on hold'
    case 'cancelled':
      return 'Project cancelled'
    default:
      return 'Status unknown'
  }
}

const timeAgo = (date: string) => {
  const now = new Date()
  const projectDate = new Date(date)
  const diffInHours = Math.floor((now.getTime() - projectDate.getTime()) / (1000 * 60 * 60))

  if (diffInHours < 1) return 'Just now'
  if (diffInHours < 24) return `${diffInHours}h ago`
  const diffInDays = Math.floor(diffInHours / 24)
  return `${diffInDays}d ago`
}

export const RecentProjectsCard: React.FC<RecentProjectsCardProps> = ({
  projects = [],
  loading = false,
  error = null,
  dataTestId = 'recent-projects-widget'
}) => {
  return (
    <Card data-testid={dataTestId}>
      <CardHeader>
        <div className="flex items-center gap-2">
          <FolderOpen className="h-5 w-5 text-primary-600" />
          <CardTitle>Recent Projects</CardTitle>
        </div>
        <p className="text-sm text-muted-foreground">
          Your latest project updates and milestones
        </p>
      </CardHeader>
      <CardContent>
        {loading && (
          <div className="space-y-4">
            {Array.from({ length: 3 }).map((_, i) => (
              <div key={i} className="flex items-center gap-4">
                <Skeleton variant="circular" width={8} height={8} />
                <div className="flex-1">
                  <Skeleton variant="text" className="mb-2" />
                  <Skeleton variant="text" width="60%" />
                </div>
                <Skeleton width={60} height={20} />
              </div>
            ))}
          </div>
        )}

        {error && (
          <div className="text-center py-8">
            <p className="text-sm text-muted-foreground">Failed to load recent projects</p>
          </div>
        )}

        {!loading && !error && projects.length > 0 && (
          <div className="space-y-4">
            {projects.slice(0, 5).map((project, index) => (
              <motion.div
                key={project.id}
                initial={{ opacity: 0, x: -20 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: index * 0.1 }}
                className="flex items-center gap-4"
              >
                <div className="flex-shrink-0">
                  <div className={cn('h-2 w-2 rounded-full', getStatusColor(project.status))} />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium truncate">{project.name}</p>
                  <p className="text-sm text-muted-foreground">
                    {getStatusText(project.status, project.progress)}
                  </p>
                </div>
                <div className="text-sm text-muted-foreground whitespace-nowrap">
                  {timeAgo(project.updated_at)}
                </div>
              </motion.div>
            ))}
          </div>
        )}

        {!loading && !error && projects.length === 0 && (
          <div className="text-center py-8">
            <FolderOpen className="h-12 w-12 text-muted-foreground mx-auto mb-4 opacity-50" />
            <p className="text-sm text-muted-foreground mb-2">No recent projects found</p>
            <button
              onClick={() => window.location.href = '/app/projects/create'}
              className="text-sm text-primary-600 hover:text-primary-800 font-medium"
              data-testid={`${dataTestId}-create-link`}
            >
              Create your first project
            </button>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

