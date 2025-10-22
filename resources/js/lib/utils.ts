// Utility functions for the application
export const cn = (...classes: (string | undefined | null | false)[]): string => {
  return classes.filter(Boolean).join(' ');
};

export const formatDate = (date: string | Date): string => {
  const d = new Date(date);
  return d.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

export const formatDateTime = (date: string | Date): string => {
  const d = new Date(date);
  return d.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

export const debounce = <T extends (..._args: any[]) => any>(
  func: T,
  wait: number
): ((..._args: Parameters<T>) => void) => {
  let timeout: NodeJS.Timeout;
  return (..._args: Parameters<T>) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => func(..._args), wait);
  };
};

export const throttle = <T extends (..._args: any[]) => any>(
  func: T,
  limit: number
): ((..._args: Parameters<T>) => void) => {
  let inThrottle: boolean;
  return (..._args: Parameters<T>) => {
    if (!inThrottle) {
      func(..._args);
      inThrottle = true;
      setTimeout(() => (inThrottle = false), limit);
    }
  };
};
