import React from 'react'
import { motion } from 'framer-motion'
import { Card, CardHeader, CardTitle, CardContent } from '../ui/Card'
import { Skeleton } from '../ui/Skeleton'
import { Avatar, AvatarFallback, AvatarImage } from '../ui/Avatar'
import { Badge } from '../ui/Badge'
import { Users } from 'lucide-react'
import { cn } from '../../lib/utils'

export interface TeamMember {
  id: string
  name: string
  avatar?: string
  role: string
  status: 'online' | 'away' | 'offline'
  email?: string
}

interface TeamStatusCardProps {
  members?: TeamMember[]
  loading?: boolean
  error?: Error | null
  dataTestId?: string
}

const statusColors = {
  online: 'bg-green-500',
  away: 'bg-yellow-500',
  offline: 'bg-gray-400'
}

const statusLabels = {
  online: 'Online',
  away: 'Away',
  offline: 'Offline'
}

const getInitials = (name: string) => {
  return name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

export const TeamStatusCard: React.FC<TeamStatusCardProps> = ({
  members = [],
  loading = false,
  error = null,
  dataTestId = 'team-status-widget'
}) => {
  return (
    <Card data-testid={dataTestId}>
      <CardHeader>
        <div className="flex items-center gap-2">
          <Users className="h-5 w-5 text-primary-600" />
          <CardTitle>Team Status</CardTitle>
        </div>
        <p className="text-sm text-muted-foreground">
          Current status of your team members
        </p>
      </CardHeader>
      <CardContent>
        {loading && (
          <div className="space-y-4">
            {Array.from({ length: 5 }).map((_, i) => (
              <div key={i} className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <Skeleton variant="circular" width={40} height={40} />
                  <div>
                    <Skeleton variant="text" className="mb-2" />
                    <Skeleton variant="text" width="40%" />
                  </div>
                </div>
                <Skeleton width={80} height={24} />
              </div>
            ))}
          </div>
        )}

        {error && (
          <div className="text-center py-8">
            <p className="text-sm text-muted-foreground">Failed to load team status</p>
          </div>
        )}

        {!loading && !error && members.length > 0 && (
          <div className="space-y-4">
            {members.map((member, index) => (
              <motion.div
                key={member.id}
                initial={{ opacity: 0, x: -20 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: index * 0.05 }}
                className="flex items-center justify-between"
              >
                <div className="flex items-center gap-3">
                  <div className="relative">
                    <Avatar>
                      <AvatarImage src={member.avatar} alt={member.name} />
                      <AvatarFallback>{getInitials(member.name)}</AvatarFallback>
                    </Avatar>
                    <div
                      className={cn(
                        'absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-background',
                        statusColors[member.status]
                      )}
                      title={statusLabels[member.status]}
                    />
                  </div>
                  <div>
                    <p className="text-sm font-medium">{member.name}</p>
                    <p className="text-xs text-muted-foreground">{member.role}</p>
                  </div>
                </div>
                <Badge
                  variant="secondary"
                  className={cn(
                    'text-xs',
                    member.status === 'online' && 'bg-green-100 text-green-800',
                    member.status === 'away' && 'bg-yellow-100 text-yellow-800',
                    member.status === 'offline' && 'bg-gray-100 text-gray-800'
                  )}
                >
                  {statusLabels[member.status]}
                </Badge>
              </motion.div>
            ))}
          </div>
        )}

        {!loading && !error && members.length === 0 && (
          <div className="text-center py-8">
            <Users className="h-12 w-12 text-muted-foreground mx-auto mb-4 opacity-50" />
            <p className="text-sm text-muted-foreground">No team members</p>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

