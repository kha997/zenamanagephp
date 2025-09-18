import { useCallback } from 'react';
import { z } from 'zod';
import { toast } from 'react-hot-toast';

/**
 * Custom hook for form validation
 * 
 * Provides consistent validation across the application
 */
export function useValidation<T extends z.ZodTypeAny>(schema: T) {
  /**
   * Validate form data
   */
  const validate = useCallback((data: unknown): z.infer<T> | null => {
    try {
      return schema.parse(data);
    } catch (error) {
      if (error instanceof z.ZodError) {
        // Handle validation errors
        const errors = error.errors.map(err => ({
          field: err.path.join('.'),
          message: err.message,
        }));
        
        // Show first error as toast
        if (errors.length > 0) {
          toast.error(errors[0].message);
        }
        
        return null;
      }
      throw error;
    }
  }, [schema]);

  /**
   * Validate form data safely (returns errors instead of throwing)
   */
  const validateSafe = useCallback((data: unknown): {
    success: boolean;
    data?: z.infer<T>;
    errors?: Array<{ field: string; message: string }>;
  } => {
    try {
      const result = schema.parse(data);
      return { success: true, data: result };
    } catch (error) {
      if (error instanceof z.ZodError) {
        const errors = error.errors.map(err => ({
          field: err.path.join('.'),
          message: err.message,
        }));
        return { success: false, errors };
      }
      throw error;
    }
  }, [schema]);

  /**
   * Validate individual field
   */
  const validateField = useCallback((field: string, value: unknown): string | null => {
    try {
      // Create a partial schema for the field
      const fieldSchema = schema.shape[field as keyof typeof schema.shape];
      if (fieldSchema) {
        fieldSchema.parse(value);
      }
      return null;
    } catch (error) {
      if (error instanceof z.ZodError) {
        return error.errors[0]?.message || 'Invalid value';
      }
      return 'Invalid value';
    }
  }, [schema]);

  /**
   * Get field error message
   */
  const getFieldError = useCallback((field: string, errors: Array<{ field: string; message: string }>): string | null => {
    return errors.find(err => err.field === field)?.message || null;
  }, []);

  /**
   * Format validation errors for display
   */
  const formatErrors = useCallback((errors: Array<{ field: string; message: string }>): Record<string, string> => {
    const formatted: Record<string, string> = {};
    errors.forEach(error => {
      formatted[error.field] = error.message;
    });
    return formatted;
  }, []);

  return {
    validate,
    validateSafe,
    validateField,
    getFieldError,
    formatErrors,
  };
}

/**
 * Hook for async validation (e.g., checking if email exists)
 */
export function useAsyncValidation() {
  /**
   * Validate email uniqueness
   */
  const validateEmailUnique = useCallback(async (email: string): Promise<boolean> => {
    try {
      const response = await fetch(`/api/v1/users/check-email?email=${encodeURIComponent(email)}`);
      const data = await response.json();
      return !data.exists;
    } catch (error) {
      console.error('Email validation error:', error);
      return true; // Allow on error
    }
  }, []);

  /**
   * Validate project name uniqueness
   */
  const validateProjectNameUnique = useCallback(async (name: string, projectId?: string): Promise<boolean> => {
    try {
      const url = projectId 
        ? `/api/v1/projects/check-name?name=${encodeURIComponent(name)}&exclude=${projectId}`
        : `/api/v1/projects/check-name?name=${encodeURIComponent(name)}`;
      
      const response = await fetch(url);
      const data = await response.json();
      return !data.exists;
    } catch (error) {
      console.error('Project name validation error:', error);
      return true; // Allow on error
    }
  }, []);

  /**
   * Validate task title uniqueness within project
   */
  const validateTaskTitleUnique = useCallback(async (title: string, projectId: string, taskId?: string): Promise<boolean> => {
    try {
      const url = taskId
        ? `/api/v1/tasks/check-title?title=${encodeURIComponent(title)}&project_id=${projectId}&exclude=${taskId}`
        : `/api/v1/tasks/check-title?title=${encodeURIComponent(title)}&project_id=${projectId}`;
      
      const response = await fetch(url);
      const data = await response.json();
      return !data.exists;
    } catch (error) {
      console.error('Task title validation error:', error);
      return true; // Allow on error
    }
  }, []);

  return {
    validateEmailUnique,
    validateProjectNameUnique,
    validateTaskTitleUnique,
  };
}

/**
 * Hook for password validation
 */
export function usePasswordValidation() {
  /**
   * Check password strength
   */
  const checkPasswordStrength = useCallback((password: string): {
    score: number;
    feedback: string[];
    isValid: boolean;
  } => {
    const feedback: string[] = [];
    let score = 0;

    // Length check
    if (password.length >= 8) {
      score += 1;
    } else {
      feedback.push('Password must be at least 8 characters long');
    }

    // Uppercase check
    if (/[A-Z]/.test(password)) {
      score += 1;
    } else {
      feedback.push('Password must contain at least one uppercase letter');
    }

    // Lowercase check
    if (/[a-z]/.test(password)) {
      score += 1;
    } else {
      feedback.push('Password must contain at least one lowercase letter');
    }

    // Number check
    if (/\d/.test(password)) {
      score += 1;
    } else {
      feedback.push('Password must contain at least one number');
    }

    // Special character check
    if (/[@$!%*?&]/.test(password)) {
      score += 1;
    } else {
      feedback.push('Password must contain at least one special character (@$!%*?&)');
    }

    // Length bonus
    if (password.length >= 12) {
      score += 1;
    }

    // Complexity bonus
    if (password.length >= 8 && /[A-Z]/.test(password) && /[a-z]/.test(password) && /\d/.test(password) && /[@$!%*?&]/.test(password)) {
      score += 1;
    }

    const isValid = score >= 4; // Minimum 4 out of 7 for valid password

    return {
      score,
      feedback,
      isValid,
    };
  }, []);

  /**
   * Get password strength label
   */
  const getPasswordStrengthLabel = useCallback((score: number): string => {
    if (score <= 2) return 'Weak';
    if (score <= 4) return 'Fair';
    if (score <= 5) return 'Good';
    if (score <= 6) return 'Strong';
    return 'Very Strong';
  }, []);

  /**
   * Get password strength color
   */
  const getPasswordStrengthColor = useCallback((score: number): string => {
    if (score <= 2) return 'text-red-500';
    if (score <= 4) return 'text-orange-500';
    if (score <= 5) return 'text-yellow-500';
    if (score <= 6) return 'text-green-500';
    return 'text-green-600';
  }, []);

  return {
    checkPasswordStrength,
    getPasswordStrengthLabel,
    getPasswordStrengthColor,
  };
}

/**
 * Hook for file validation
 */
export function useFileValidation() {
  /**
   * Validate file type
   */
  const validateFileType = useCallback((file: File, allowedTypes: string[]): boolean => {
    return allowedTypes.includes(file.type);
  }, []);

  /**
   * Validate file size
   */
  const validateFileSize = useCallback((file: File, maxSizeMB: number): boolean => {
    const maxSizeBytes = maxSizeMB * 1024 * 1024;
    return file.size <= maxSizeBytes;
  }, []);

  /**
   * Validate image file
   */
  const validateImageFile = useCallback((file: File): {
    isValid: boolean;
    error?: string;
  } => {
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const maxSizeMB = 5;

    if (!validateFileType(file, allowedTypes)) {
      return {
        isValid: false,
        error: 'File type not allowed. Allowed types: JPEG, PNG, GIF, WebP',
      };
    }

    if (!validateFileSize(file, maxSizeMB)) {
      return {
        isValid: false,
        error: `File size must be less than ${maxSizeMB}MB`,
      };
    }

    return { isValid: true };
  }, [validateFileType, validateFileSize]);

  /**
   * Validate document file
   */
  const validateDocumentFile = useCallback((file: File): {
    isValid: boolean;
    error?: string;
  } => {
    const allowedTypes = [
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'text/plain',
    ];
    const maxSizeMB = 10;

    if (!validateFileType(file, allowedTypes)) {
      return {
        isValid: false,
        error: 'File type not allowed. Allowed types: PDF, DOC, DOCX, TXT',
      };
    }

    if (!validateFileSize(file, maxSizeMB)) {
      return {
        isValid: false,
        error: `File size must be less than ${maxSizeMB}MB`,
      };
    }

    return { isValid: true };
  }, [validateFileType, validateFileSize]);

  return {
    validateFileType,
    validateFileSize,
    validateImageFile,
    validateDocumentFile,
  };
}
