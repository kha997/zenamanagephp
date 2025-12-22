import React from 'react';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';

export const QCModulePage: React.FC = () => {
  return (
    <Container>
      <Card>
        <CardHeader>
          <CardTitle>Quality Control</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-[var(--color-text-muted)]">
            QC Module - Coming soon
          </p>
        </CardContent>
      </Card>
    </Container>
  );
};

export default QCModulePage;

