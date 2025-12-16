/**
 * downloadBlob utility
 * 
 * Round 228: PDF Export for Contracts, COs, and Payment Certificates
 * 
 * Utility function to download a blob as a file in the browser
 */

/**
 * Download a blob as a file
 * 
 * @param blob The blob to download
 * @param filename The filename for the downloaded file
 */
export function downloadBlob(blob: Blob, filename: string): void {
  // Create a temporary URL for the blob
  const url = window.URL.createObjectURL(blob);
  
  // Create a temporary anchor element
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  
  // Append to body, click, and remove
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  
  // Clean up the URL after a short delay
  setTimeout(() => {
    window.URL.revokeObjectURL(url);
  }, 100);
}
