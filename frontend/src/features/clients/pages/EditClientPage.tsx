import React, { useState, useCallback, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { useClient, useUpdateClient } from '../hooks';

interface FormData {
  name: string;
  email: string;
  phone: string;
  status: 'active' | 'inactive' | 'prospect';
}

export const EditClientPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { data: clientData, isLoading, error } = useClient(id!);
  const updateClient = useUpdateClient();
  
  const [formData, setFormData] = useState<FormData>({
    name: '',
    email: '',
    phone: '',
    status: 'prospect',
  });
  
  const [errors, setErrors] = useState<Partial<Record<keyof FormData, string>>>({});

  useEffect(() => {
    if (clientData?.data) {
      const client = clientData.data;
      setFormData({
        name: client.name || '',
        email: client.email || '',
        phone: client.phone || '',
        status: client.status || 'prospect',
      });
    }
  }, [clientData]);

  const handleInputChange = useCallback((field: keyof FormData, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    if (errors[field]) {
      setErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors[field];
        return newErrors;
      });
    }
  }, [errors]);

  const handleSubmit = useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    
    const newErrors: Partial<Record<keyof FormData, string>> = {};
    if (!formData.name.trim()) {
      newErrors.name = 'Client name is required';
    }
    if (formData.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = 'Invalid email format';
    }
    
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }
    
    if (!id) return;
    
    try {
      await updateClient.mutateAsync({ id, data: formData });
      navigate(`/app/clients/${id}`);
    } catch (error) {
      console.error('Failed to update client:', error);
      alert('Failed to update client. Please try again.');
    }
  }, [formData, updateClient, navigate, id]);

  if (isLoading) {
    return (
      <Container>
        <div className="animate-pulse">
          <div className="h-8 bg-[var(--muted-surface)] rounded w-1/3 mb-4"></div>
        </div>
      </Container>
    );
  }

  if (error || !clientData?.data) {
    return (
      <Container>
        <Card>
          <CardContent className="py-8">
            <div className="text-center">
              <p className="text-[var(--muted)] mb-4">
                {error ? `Error: ${(error as Error).message}` : 'Client not found'}
              </p>
              <Button variant="secondary" onClick={() => navigate('/app/clients')}>
                Back to Clients
              </Button>
            </div>
          </CardContent>
        </Card>
      </Container>
    );
  }

  return (
    <Container>
      <Card>
        <CardHeader>
          <CardTitle>Edit Client</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-1">
                Name <span className="text-red-500">*</span>
              </label>
              <Input
                value={formData.name}
                onChange={(e) => handleInputChange('name', e.target.value)}
                placeholder="Client name"
                error={errors.name}
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-1">
                Email
              </label>
              <Input
                type="email"
                value={formData.email}
                onChange={(e) => handleInputChange('email', e.target.value)}
                placeholder="client@example.com"
                error={errors.email}
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-1">
                Phone
              </label>
              <Input
                type="tel"
                value={formData.phone}
                onChange={(e) => handleInputChange('phone', e.target.value)}
                placeholder="+1 (555) 123-4567"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-1">
                Status
              </label>
              <select
                value={formData.status}
                onChange={(e) => handleInputChange('status', e.target.value)}
                className="w-full px-3 py-2 border border-[var(--border)] rounded-lg bg-[var(--surface)] text-[var(--text)]"
              >
                <option value="prospect">Prospect</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            
            <div className="flex items-center gap-3 pt-4">
              <Button
                type="submit"
                disabled={updateClient.isPending}
              >
                {updateClient.isPending ? 'Saving...' : 'Save Changes'}
              </Button>
              <Button
                type="button"
                variant="secondary"
                onClick={() => navigate(`/app/clients/${id}`)}
              >
                Cancel
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </Container>
  );
};

export default EditClientPage;

