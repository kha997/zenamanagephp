import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useState, useCallback } from 'react';
import {
  createDocumentSchema,
  updateDocumentSchema,
  uploadVersionSchema,
  type CreateDocumentFormData,
  type UpdateDocumentFormData,
  type UploadVersionFormData
} from '../validations/documentValidation';
import type { Document } from '../types/document';

// Hook cho form tạo document mới
export const useCreateDocumentForm = (onSubmit: (data: CreateDocumentFormData) => void) => {
  const [isSubmitting, setIsSubmitting] = useState(false);
  
  const form = useForm<CreateDocumentFormData>({
    resolver: zodResolver(createDocumentSchema),
    defaultValues: {
      title: '',
      project_id: undefined,
      linked_entity_type: undefined,
      linked_entity_id: undefined,
      file: undefined,
      comment: '',
    },
  });

  const handleSubmit = useCallback(async (data: CreateDocumentFormData) => {
    setIsSubmitting(true);
    try {
      await onSubmit(data);
      form.reset();
    } finally {
      setIsSubmitting(false);
    }
  }, [onSubmit, form]);

  return {
    form,
    isSubmitting,
    handleSubmit: form.handleSubmit(handleSubmit),
    reset: form.reset,
    watch: form.watch,
    setValue: form.setValue,
    getValues: form.getValues,
  };
};

// Hook cho form cập nhật document
export const useUpdateDocumentForm = (
  document: Document | undefined,
  onSubmit: (data: UpdateDocumentFormData) => void
) => {
  const [isSubmitting, setIsSubmitting] = useState(false);
  
  const form = useForm<UpdateDocumentFormData>({
    resolver: zodResolver(updateDocumentSchema),
    defaultValues: {
      title: document?.title || '',
      linked_entity_type: document?.linked_entity_type,
      linked_entity_id: document?.linked_entity_id,
    },
  });

  // Reset form khi document thay đổi
  React.useEffect(() => {
    if (document) {
      form.reset({
        title: document.title,
        linked_entity_type: document.linked_entity_type,
        linked_entity_id: document.linked_entity_id,
      });
    }
  }, [document, form]);

  const handleSubmit = useCallback(async (data: UpdateDocumentFormData) => {
    setIsSubmitting(true);
    try {
      await onSubmit(data);
    } finally {
      setIsSubmitting(false);
    }
  }, [onSubmit]);

  return {
    form,
    isSubmitting,
    handleSubmit: form.handleSubmit(handleSubmit),
    reset: form.reset,
    watch: form.watch,
    setValue: form.setValue,
    getValues: form.getValues,
  };
};

// Hook cho form upload version mới
export const useUploadVersionForm = (onSubmit: (data: UploadVersionFormData) => void) => {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  
  const form = useForm<UploadVersionFormData>({
    resolver: zodResolver(uploadVersionSchema),
    defaultValues: {
      file: undefined,
      comment: '',
    },
  });

  const handleSubmit = useCallback(async (data: UploadVersionFormData) => {
    setIsSubmitting(true);
    setUploadProgress(0);
    
    try {
      // Simulate upload progress (trong thực tế sẽ được handle bởi API)
      const progressInterval = setInterval(() => {
        setUploadProgress(prev => {
          if (prev >= 90) {
            clearInterval(progressInterval);
            return prev;
          }
          return prev + 10;
        });
      }, 200);
      
      await onSubmit(data);
      
      clearInterval(progressInterval);
      setUploadProgress(100);
      
      // Reset form sau khi upload thành công
      setTimeout(() => {
        form.reset();
        setUploadProgress(0);
      }, 1000);
    } finally {
      setIsSubmitting(false);
    }
  }, [onSubmit, form]);

  return {
    form,
    isSubmitting,
    uploadProgress,
    handleSubmit: form.handleSubmit(handleSubmit),
    reset: () => {
      form.reset();
      setUploadProgress(0);
    },
    watch: form.watch,
    setValue: form.setValue,
    getValues: form.getValues,
  };
};

// Hook cho file drag & drop
export const useFileDropzone = (onFileSelect: (file: File) => void) => {
  const [isDragOver, setIsDragOver] = useState(false);
  
  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(true);
  }, []);
  
  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
  }, []);
  
  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
    
    const files = Array.from(e.dataTransfer.files);
    if (files.length > 0) {
      onFileSelect(files[0]);
    }
  }, [onFileSelect]);
  
  return {
    isDragOver,
    dragProps: {
      onDragOver: handleDragOver,
      onDragLeave: handleDragLeave,
      onDrop: handleDrop,
    },
  };
};