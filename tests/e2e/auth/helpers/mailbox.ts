/**
 * Mailbox helper for email testing
 * Supports MailHog and Mailpit
 */

const MAILBOX_BASE_URL = process.env.MAILBOX_UI || 'http://localhost:8025';

export interface Email {
  ID: string;
  From: { Mailbox: string; Domain: string; Params: string };
  To: Array<{ Mailbox: string; Domain: string; Params: string }>;
  Content: {
    Headers: Record<string, string[]>;
    Body: string;
    Size: number;
  };
  Created: string;
  MIME?: any;
}

/**
 * Get the last email for a recipient
 */
export async function getLastEmail(recipient: string): Promise<Email | null> {
  try {
    // Try MailHog first
    const mailhogUrl = `${MAILBOX_BASE_URL}/api/v2/search?kind=to&query=${encodeURIComponent(recipient)}`;
    const response = await fetch(mailhogUrl);
    
    if (response.ok) {
      const data = await response.json();
      const items = data.items || [];
      
      // Get most recent email
      if (items.length > 0) {
        // Sort by Created date (most recent first)
        items.sort((a: Email, b: Email) => 
          new Date(b.Created).getTime() - new Date(a.Created).getTime()
        );
        return items[0];
      }
    }
  } catch (error) {
    console.warn('MailHog not available, trying Mailpit...');
  }
  
  try {
    // Try Mailpit
    const mailpitUrl = `${MAILBOX_BASE_URL}/api/v1/search?query=to:${recipient}`;
    const response = await fetch(mailpitUrl);
    
    if (response.ok) {
      const data = await response.json();
      const messages = data.messages || [];
      
      if (messages.length > 0) {
        // Get most recent
        messages.sort((a: any, b: any) => b.Created - a.Created);
        return messages[0];
      }
    }
  } catch (error) {
    console.warn('Neither mail service available');
  }
  
  return null;
}

/**
 * Extract verification link from email
 */
export function extractVerificationLink(email: Email): string | null {
  const body = email.Content.Body;
  
  // Try to find URL patterns
  const urlPattern = /(https?:\/\/[^\s<>"']+)/g;
  const matches = body.match(urlPattern);
  
  if (matches && matches.length > 0) {
    // Prefer verification, reset, or invite links
    const verificationLink = matches.find(url => 
      url.includes('/verify') || 
      url.includes('/reset-password') || 
      url.includes('/accept-invite') ||
      url.includes('token=')
    );
    
    return verificationLink || matches[0];
  }
  
  return null;
}

/**
 * Wait for email to arrive
 */
export async function waitForEmail(
  recipient: string,
  maxWait: number = 30000
): Promise<Email> {
  const startTime = Date.now();
  
  while (Date.now() - startTime < maxWait) {
    const email = await getLastEmail(recipient);
    
    if (email) {
      // Check if email is recent (within last minute)
      const emailTime = new Date(email.Created).getTime();
      const now = Date.now();
      
      if (now - emailTime < 60000) {
        return email;
      }
    }
    
    await new Promise(resolve => setTimeout(resolve, 1000));
  }
  
  throw new Error(`Email not received for ${recipient} within ${maxWait}ms`);
}

/**
 * Extract OTP/TOTP code from email
 */
export function extractOTPCode(email: Email): string | null {
  const body = email.Content.Body;
  
  // Common patterns: 6-digit codes
  const codePattern = /\b(\d{6})\b/g;
  const matches = body.match(codePattern);
  
  return matches && matches.length > 0 ? matches[0] : null;
}

/**
 * Clear all emails in mailbox
 */
export async function clearMailbox(): Promise<void> {
  try {
    // MailHog
    await fetch(`${MAILBOX_BASE_URL}/api/v1/messages`, {
      method: 'DELETE',
    });
  } catch (error) {
    try {
      // Mailpit
      await fetch(`${MAILBOX_BASE_URL}/api/v1/messages`, {
        method: 'DELETE',
      });
    } catch (error) {
      // Silent fail if mailbox not available
    }
  }
}

