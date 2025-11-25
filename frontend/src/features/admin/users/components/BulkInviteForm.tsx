import React, { useState, useCallback } from 'react';
import { Input } from '../../../../components/ui/primitives/Input';

interface BulkInviteFormProps {
  emails: string[];
  onEmailsChange: (emails: string[]) => void;
}

export const BulkInviteForm: React.FC<BulkInviteFormProps> = ({
  emails,
  onEmailsChange,
}) => {
  const [textareaValue, setTextareaValue] = useState('');

  const parseEmails = useCallback((text: string): string[] => {
    // Split by newlines, commas, or semicolons
    const lines = text
      .split(/[\n,;]+/)
      .map((line) => line.trim())
      .filter((line) => line.length > 0);

    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const validEmails: string[] = [];
    const invalidEmails: string[] = [];

    lines.forEach((line) => {
      if (emailRegex.test(line)) {
        if (!validEmails.includes(line.toLowerCase())) {
          validEmails.push(line.toLowerCase());
        }
      } else {
        invalidEmails.push(line);
      }
    });

    return validEmails;
  }, []);

  const handleTextareaChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    const value = e.target.value;
    setTextareaValue(value);
    const parsedEmails = parseEmails(value);
    onEmailsChange(parsedEmails);
  };

  const invalidEmails = textareaValue
    .split(/[\n,;]+/)
    .map((line) => line.trim())
    .filter((line) => {
      if (!line) return false;
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return !emailRegex.test(line);
    });

  return (
    <div className="space-y-2">
      <label className="block text-sm font-medium text-[var(--color-text-primary)] mb-1">
        Email Addresses *
      </label>
      <textarea
        className="w-full min-h-[120px] px-3 py-2 border border-[var(--color-border-default)] rounded-[var(--radius-md)] bg-transparent text-[var(--color-text-primary)] resize-none focus:outline-none focus:ring-2 focus:ring-[var(--color-border-focus)] font-mono text-sm"
        placeholder="Enter email addresses, one per line, or separated by commas:&#10;user1@example.com&#10;user2@example.com&#10;user3@example.com, user4@example.com"
        value={textareaValue}
        onChange={handleTextareaChange}
      />
      <div className="flex items-center justify-between text-xs">
        <div>
          <span className="text-[var(--color-text-secondary)]">
            {emails.length} valid email{emails.length !== 1 ? 's' : ''}
          </span>
          {invalidEmails.length > 0 && (
            <span className="text-[var(--color-semantic-danger-600)] ml-2">
              {invalidEmails.length} invalid
            </span>
          )}
        </div>
        <span className="text-[var(--color-text-muted)]">
          Max 50 emails per invitation
        </span>
      </div>

      {/* Preview valid emails */}
      {emails.length > 0 && (
        <div className="mt-2 p-2 bg-[var(--color-surface-muted)] rounded-[var(--radius-md)] max-h-[120px] overflow-y-auto">
          <p className="text-xs font-medium text-[var(--color-text-secondary)] mb-1">
            Valid emails ({emails.length}):
          </p>
          <div className="flex flex-wrap gap-1">
            {emails.slice(0, 10).map((email, index) => (
              <span
                key={index}
                className="inline-block px-2 py-0.5 bg-[var(--color-surface-card)] rounded text-xs text-[var(--color-text-primary)]"
              >
                {email}
              </span>
            ))}
            {emails.length > 10 && (
              <span className="inline-block px-2 py-0.5 text-xs text-[var(--color-text-muted)]">
                +{emails.length - 10} more
              </span>
            )}
          </div>
        </div>
      )}

      {/* Show invalid emails */}
      {invalidEmails.length > 0 && (
        <div className="mt-2 p-2 bg-[var(--color-semantic-danger-50)] border border-[var(--color-semantic-danger-200)] rounded-[var(--radius-md)]">
          <p className="text-xs font-medium text-[var(--color-semantic-danger-700)] mb-1">
            Invalid emails ({invalidEmails.length}):
          </p>
          <div className="flex flex-wrap gap-1">
            {invalidEmails.slice(0, 5).map((email, index) => (
              <span
                key={index}
                className="inline-block px-2 py-0.5 bg-white rounded text-xs text-[var(--color-semantic-danger-700)]"
              >
                {email}
              </span>
            ))}
            {invalidEmails.length > 5 && (
              <span className="inline-block px-2 py-0.5 text-xs text-[var(--color-semantic-danger-600)]">
                +{invalidEmails.length - 5} more
              </span>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

