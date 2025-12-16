import React, { useState, useCallback, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { useQuote, useUpdateQuote } from '../hooks';
import { useClients } from '../../clients/hooks';

interface FormData {
  title: string;
  client_id: string;
  amount: string;
  status: 'draft' | 'pending' | 'accepted' | 'rejected';
}

export const EditQuotePage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { data: quoteData, isLoading, error } = useQuote(id!);
  const { data: clientsData } = useClients();
  const updateQuote = useUpdateQuote();
  
  const [formData, setFormData] = useState<FormData>({
    title: '',
    client_id: '',
    amount: '',
    status: 'draft',
  });
  
  const [errors, setErrors] = useState<Partial<Record<keyof FormData, string>>>({});

  useEffect(() => {
    if (quoteData?.data) {
      const quote = quoteData.data;
      setFormData({
        title: quote.title || '',
        client_id: quote.client_id ? String(quote.client_id) : '',
        amount: quote.amount ? String(quote.amount) : '',
        status: quote.status || 'draft',
      });
    }
  }, [quoteData]);

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
    if (!formData.title.trim()) {
      newErrors.title = 'Quote title is required';
    }
    if (formData.amount && isNaN(parseFloat(formData.amount))) {
      newErrors.amount = 'Amount must be a valid number';
    }
    
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }
    
    if (!id) return;
    
    try {
      const payload: Partial<any> = {
        title: formData.title,
        status: formData.status,
      };
      if (formData.client_id) {
        payload.client_id = formData.client_id;
      }
      if (formData.amount) {
        payload.amount = parseFloat(formData.amount);
      }
      
      await updateQuote.mutateAsync({ id, data: payload });
      navigate(`/app/quotes/${id}`);
    } catch (error) {
      console.error('Failed to update quote:', error);
      alert('Failed to update quote. Please try again.');
    }
  }, [formData, updateQuote, navigate, id]);

  if (isLoading) {
    return (
      <Container>
        <div className="animate-pulse">
          <div className="h-8 bg-[var(--muted-surface)] rounded w-1/3 mb-4"></div>
        </div>
      </Container>
    );
  }

  if (error || !quoteData?.data) {
    return (
      <Container>
        <Card>
          <CardContent className="py-8">
            <div className="text-center">
              <p className="text-[var(--muted)] mb-4">
                {error ? `Error: ${(error as Error).message}` : 'Quote not found'}
              </p>
              <Button variant="secondary" onClick={() => navigate('/app/quotes')}>
                Back to Quotes
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
          <CardTitle>Edit Quote</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-1">
                Title <span className="text-red-500">*</span>
              </label>
              <Input
                value={formData.title}
                onChange={(e) => handleInputChange('title', e.target.value)}
                placeholder="Quote title"
                error={errors.title}
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-1">
                Client
              </label>
              <select
                value={formData.client_id}
                onChange={(e) => handleInputChange('client_id', e.target.value)}
                className="w-full px-3 py-2 border border-[var(--border)] rounded-lg bg-[var(--surface)] text-[var(--text)]"
              >
                <option value="">Select a client</option>
                {clientsData?.data?.map((client) => (
                  <option key={client.id} value={client.id}>
                    {client.name}
                  </option>
                ))}
              </select>
            </div>
            
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-1">
                Amount
              </label>
              <Input
                type="number"
                step="0.01"
                value={formData.amount}
                onChange={(e) => handleInputChange('amount', e.target.value)}
                placeholder="0.00"
                error={errors.amount}
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
                <option value="draft">Draft</option>
                <option value="pending">Pending</option>
                <option value="accepted">Accepted</option>
                <option value="rejected">Rejected</option>
              </select>
            </div>
            
            <div className="flex items-center gap-3 pt-4">
              <Button
                type="submit"
                disabled={updateQuote.isPending}
              >
                {updateQuote.isPending ? 'Saving...' : 'Save Changes'}
              </Button>
              <Button
                type="button"
                variant="secondary"
                onClick={() => navigate(`/app/quotes/${id}`)}
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

export default EditQuotePage;

