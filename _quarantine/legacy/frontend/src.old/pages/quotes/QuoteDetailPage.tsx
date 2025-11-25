import { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { Badge } from '../../shared/ui/badge';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../shared/api/client';
import toast from 'react-hot-toast';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';

export default function QuoteDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const { data: quote, isLoading } = useQuery({
    queryKey: ['quotes', id],
    queryFn: async () => {
      const response = await apiClient.get(`/app/quotes/${id}`);
      return response.data;
    },
  });

  const sendMutation = useMutation({
    mutationFn: async () => {
      const response = await apiClient.post(`/app/quotes/${id}/send`);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['quotes', id] });
      toast.success('Quote sent successfully');
    },
  });

  const acceptMutation = useMutation({
    mutationFn: async () => {
      const response = await apiClient.post(`/app/quotes/${id}/accept`);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['quotes', id] });
      toast.success('Quote accepted');
    },
  });

  const rejectMutation = useMutation({
    mutationFn: async () => {
      const response = await apiClient.post(`/app/quotes/${id}/reject`);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['quotes', id] });
      toast.success('Quote rejected');
    },
  });

  if (isLoading) {
    return <div className="text-center py-8">Loading...</div>;
  }

  if (!quote?.data) {
    return <div className="text-center py-8">Quote not found</div>;
  }

  const quoteData = quote.data;

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <Button variant="ghost" onClick={() => navigate('/app/quotes')}>
          <ArrowLeftIcon className="h-5 w-5 mr-2" />
          Back
        </Button>
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">{quoteData.title}</h1>
          <p className="text-[var(--color-text-muted)]">Quote #{quoteData.code}</p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card className="md:col-span-2">
          <CardHeader>
            <CardTitle>Quote Details</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div>
              <h3 className="font-semibold mb-2">Description</h3>
              <p className="text-[var(--color-text-muted)]">{quoteData.description}</p>
            </div>
            <div>
              <h3 className="font-semibold mb-2">Amount</h3>
              <p className="text-2xl font-bold">
                {quoteData.amount.toLocaleString()} {quoteData.currency}
              </p>
            </div>
            <div>
              <h3 className="font-semibold mb-2">Valid Until</h3>
              <p>{new Date(quoteData.valid_until).toLocaleDateString()}</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Actions</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <Badge className="w-full justify-center">{quoteData.status}</Badge>
            {quoteData.status === 'draft' && (
              <Button onClick={() => sendMutation.mutate()} className="w-full" disabled={sendMutation.isPending}>
                Send Quote
              </Button>
            )}
            {quoteData.status === 'sent' && (
              <>
                <Button onClick={() => acceptMutation.mutate()} className="w-full" disabled={acceptMutation.isPending}>
                  Accept
                </Button>
                <Button onClick={() => rejectMutation.mutate()} variant="destructive" className="w-full" disabled={rejectMutation.isPending}>
                  Reject
                </Button>
              </>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

