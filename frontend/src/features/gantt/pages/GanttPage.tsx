import React from 'react';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';

export const GanttPage: React.FC = () => {
  return (
    <Container>
      <Card>
        <CardHeader>
          <CardTitle>Gantt Chart</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-[var(--color-text-muted)]">
            Gantt Chart view - Coming soon
          </p>
        </CardContent>
      </Card>
    </Container>
  );
};

export default GanttPage;

