import React, { useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { useQuote, useDeleteQuote } from '../hooks';

export const QuoteDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { data: quoteData, isLoading, error } = useQuote(id!);
  const deleteQuote = useDeleteQuote();
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  
  const quote = quoteData?.data;

  const handleEdit = useCallback(() => {
    if (id) {
      navigate(`/app/quotes/${id}/edit`);
    }
  }, [navigate, id]);

  const handleDelete = useCallback(async () => {
    if (!id) return;
    
    try {
      await deleteQuote.mutateAsync(id);
      navigate('/app/quotes');
    } catch (error) {
      console.error('Failed to delete quote:', error);
      alert('Failed to delete quote. Please try again.');
    } finally {
      setShowDeleteConfirm(false);
    }
  }, [id, deleteQuote, navigate]);

  if (isLoading) {
    return (
      <Container>
        <div className="animate-pulse">
          <div className="h-8 bg-[var(--muted-surface)] rounded w-1/3 mb-4"></div>
        </div>
      </Container>
    );
  }

  if (error || !quote) {
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
      <div className="space-y-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex-1">
            <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)] mb-2">
              {quote.title}
            </h1>
            {quote.status && (
              <span className={`text-xs px-2 py-1 rounded capitalize ${
                quote.status === 'accepted' ? 'bg-green-100 text-green-700' :
                quote.status === 'rejected' ? 'bg-red-100 text-red-700' :
                quote.status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                'bg-gray-100 text-gray-700'
              }`}>
                {quote.status}
              </span>
            )}
          </div>
          
          <div className="flex items-center gap-2">
            <Button variant="secondary" onClick={handleEdit}>
              Edit
            </Button>
            <Button
              variant="secondary"
              onClick={() => setShowDeleteConfirm(true)}
              style={{ color: 'var(--color-semantic-danger-600)' }}
            >
              Delete
            </Button>
          </div>
        </div>

        {showDeleteConfirm && (
          <Card style={{ borderColor: 'var(--color-semantic-danger-200)' }}>
            <CardContent className="py-6">
              <h3 className="text-lg font-semibold text-[var(--text)] mb-2">
                Delete Quote?
              </h3>
              <p className="text-sm text-[var(--muted)] mb-4">
                Are you sure you want to delete "{quote.title}"? This action cannot be undone.
              </p>
              <div className="flex items-center gap-3">
                <Button
                  variant="secondary"
                  onClick={() => setShowDeleteConfirm(false)}
                  disabled={deleteQuote.isPending}
                >
                  Cancel
                </Button>
                <Button
                  onClick={handleDelete}
                  disabled={deleteQuote.isPending}
                  style={{ backgroundColor: 'var(--color-semantic-danger-600)' }}
                >
                  {deleteQuote.isPending ? 'Deleting...' : 'Delete'}
                </Button>
              </div>
            </CardContent>
          </Card>
        )}

        <Card>
          <CardHeader>
            <CardTitle>Quote Information</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {quote.amount && (
                <div>
                  <label className="text-sm font-medium text-[var(--muted)]">Amount</label>
                  <p className="text-[var(--text)] mt-1">
                    ${quote.amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  </p>
                </div>
              )}
              {quote.client_id && (
                <div>
                  <label className="text-sm font-medium text-[var(--muted)]">Client ID</label>
                  <p className="text-[var(--text)] mt-1">{quote.client_id}</p>
                </div>
              )}
              <div>
                <label className="text-sm font-medium text-[var(--muted)]">Created</label>
                <p className="text-[var(--text)] mt-1">
                  {new Date(quote.created_at).toLocaleDateString()}
                </p>
              </div>
              <div>
                <label className="text-sm font-medium text-[var(--muted)]">Last Updated</label>
                <p className="text-[var(--text)] mt-1">
                  {new Date(quote.updated_at).toLocaleDateString()}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </Container>
  );
};

export default QuoteDetailPage;

