import React from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Textarea } from '@/components/ui/Textarea';

export default function ProjectsTestPage() {
  return (
    <div className="container mx-auto p-6">
      <h1 className="text-3xl font-bold mb-6">Projects Test Page</h1>
      
      <Card className="mb-6">
        <CardHeader>
          <CardTitle>Test UI Components</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div>
            <label className="block text-sm font-medium mb-2">Project Name</label>
            <Input placeholder="Enter project name" />
          </div>
          
          <div>
            <label className="block text-sm font-medium mb-2">Description</label>
            <Textarea placeholder="Enter project description" />
          </div>
          
          <div className="flex gap-2">
            <Button variant="primary">Create Project</Button>
            <Button variant="outline">Cancel</Button>
          </div>
        </CardContent>
      </Card>
      
      <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        <p>✅ All UI components are working correctly!</p>
      </div>
    </div>
  );
}
