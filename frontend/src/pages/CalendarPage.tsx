import React from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../shared/ui/card';

export default function CalendarPage() {
  return (
    <div className="space-y-6" data-testid="calendar-page">
      <div>
        <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Calendar</h1>
        <p className="mt-1 text-sm text-[var(--color-text-secondary)]">
          View milestones, deadlines, and project schedules.
        </p>
      </div>
      
      <Card>
        <CardHeader>
          <CardTitle>Calendar View</CardTitle>
          <CardDescription>
            This calendar view is under development
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <p className="text-sm text-[var(--color-text-muted)]">
              This calendar view is under development. In the future it will centralize 
              milestones and deadlines across all your projects.
            </p>
            <div className="rounded-lg bg-[var(--color-surface-subtle)] p-4 border border-[var(--color-border-subtle)]">
              <p className="text-sm font-medium text-[var(--color-text-primary)] mb-1">
                Coming Soon
              </p>
              <p className="text-xs text-[var(--color-text-muted)]">
                Calendar features will include project milestones, task deadlines, 
                team events, and customizable views (month, week, day).
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

