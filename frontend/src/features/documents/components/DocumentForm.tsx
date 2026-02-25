
import React, { useState, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/Input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { 
  Upload, 
  FileText, 
  X, 
  AlertCircle, 
  CheckCircle, 
  Loader2,
  Link,
  Info
} from 'lucide-react';
import { 
  CreateDocumentData, 
  UpdateDocumentData, 
  UploadVersionData,
  Document 
} from '../types/document';
import { 
  createDocumentSchema, 
  updateDocumentSchema, 
  uploadVersionSchema 
} from '../validations/documentValidation';
import { cn } from '@/lib/utils';

interface DocumentFormProps {
  mode: 'create' | 'edit' | 'upload-version';
  document?: Document;
  projectId?: string;
  onSubmit: (data: CreateDocumentData | UpdateDocumentData | UploadVersionData) => Promise<void>;
  onCancel: () => void;
  loading?: boolean;
  error?: string | null;
  className?: string;
}

interface FileUploadState {
  file: File | null;
  progress: number;
  error: string | null;
  preview: string | null;
}

/**
 * DocumentForm - Component form tạo, chỉnh sửa tài liệu và upload phiên bản mới
 * 
 * Features:
 * - Hỗ trợ 3 chế độ: tạo mới, chỉnh sửa, upload phiên bản
 * - File upload với drag & drop
 * - Preview file trước khi upload
 * - Validation với Zod schemas
 * - Progress tracking cho upload
 * - Responsive design
 * - Accessibility support
 */
export const DocumentForm: React.FC<DocumentFormProps> = ({
  mode,
  document,
  projectId,
  onSubmit,
  onCancel,
  loading = false,
  error = null,
  className
}) => {
  const [fileUpload, setFileUpload] = useState<FileUploadState>({
    file: null,
    progress: 0,
    error: null,
    preview: null
  });
  const [dragActive, setDragActive] = useState(false);

  // Xác định schema validation dựa trên mode
  const getValidationSchema = () => {
    switch (mode) {
      case 'create':
        return createDocumentSchema;
      case 'edit':
        return updateDocumentSchema;
      case 'upload-version':
        return uploadVersionSchema;
      default:
        return createDocumentSchema;
    }
  };

  // Xác định default values
  const getDefaultValues = () => {
    switch (mode) {
      case 'edit':
        return {
          title: document?.title || '',
          description: document?.description || '',
          linked_entity_type: document?.linked_entity_type || '',
          linked_entity_id: document?.linked_entity_id || ''
        };
      case 'upload-version':
        return {
          comment: ''
        };
      default:
        return {
          title: '',
          description: '',
          project_id: projectId || '',
          linked_entity_type: '',
          linked_entity_id: ''
        };
    }
  };

  const {
    register,
    handleSubmit,
    formState: { errors, isValid },
    setValue,
    watch,
    reset
  } = useForm({
    resolver: zodResolver(getValidationSchema()),
    defaultValues: getDefaultValues(),
    mode: 'onChange'
  });

  const watchedValues = watch();

  // Xử lý file upload
  const handleFileSelect = useCallback((file: File) => {
    // Validate file type và size
    const maxSize = 50 * 1024 * 1024; // 50MB
    const allowedTypes = [
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.ms-excel',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/vnd.ms-powerpoint',
      'application/vnd.openxmlformats-officedocument.presentationml.presentation',
      'image/jpeg',
      'image/png',
      'image/gif',
      'text/plain'
    ];

    if (file.size > maxSize) {
      setFileUpload(prev => ({
        ...prev,
        error: 'Kích thước file không được vượt quá 50MB'
      }));
      return;
    }

    if (!allowedTypes.includes(file.type)) {
      setFileUpload(prev => ({
        ...prev,
        error: 'Loại file không được hỗ trợ'
      }));
      return;
    }

    // Tạo preview cho image files
    let preview = null;
    if (file.type.startsWith('image/')) {
      preview = URL.createObjectURL(file);
    }

    setFileUpload({
      file,
      progress: 0,
      error: null,
      preview
    });

    // Set file vào form
    setValue('file', file);
  }, [setValue]);

  // Drag & Drop handlers
  const handleDrag = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === 'dragenter' || e.type === 'dragover') {
      setDragActive(true);
    } else if (e.type === 'dragleave') {
      setDragActive(false);
    }
  }, []);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);

    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFileSelect(e.dataTransfer.files[0]);
    }
  }, [handleFileSelect]);

  // Xử lý submit form
  const onFormSubmit = async (data: any) => {
    try {
      // Thêm file vào data nếu có
      if (fileUpload.file) {
        data.file = fileUpload.file;
      }

      await onSubmit(data);
      
      // Reset form sau khi submit thành công
      if (mode === 'create') {
        reset();
        setFileUpload({
          file: null,
          progress: 0,
          error: null,
          preview: null
        });
      }
    } catch (error) {
      console.error('Form submission error:', error);
    }
  };

  // Remove file
  const removeFile = () => {
    if (fileUpload.preview) {
      URL.revokeObjectURL(fileUpload.preview);
    }
    setFileUpload({
      file: null,
      progress: 0,
      error: null,
      preview: null
    });
    setValue('file', undefined);
  };

  // Format file size
  const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  // Get form title
  const getFormTitle = () => {
    switch (mode) {
      case 'create':
        return 'Tạo tài liệu mới';
      case 'edit':
        return 'Chỉnh sửa tài liệu';
      case 'upload-version':
        return 'Upload phiên bản mới';
      default:
        return 'Tài liệu';
    }
  };

  return (
    <Card className={cn('w-full max-w-2xl mx-auto', className)}>
      <CardHeader>
        <CardTitle className="flex items-center space-x-2">
          <FileText className="h-5 w-5" />
          <span>{getFormTitle()}</span>
        </CardTitle>
        {mode === 'upload-version' && document && (
          <div className="text-sm text-gray-600">
            Tài liệu: <span className="font-medium">{document.title}</span>
            <Badge variant="outline" className="ml-2">
              Phiên bản hiện tại: v{document.current_version?.version_number || 1}
            </Badge>
          </div>
        )}
      </CardHeader>

      <CardContent className="space-y-6">
        {/* Error Alert */}
        {error && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        <form onSubmit={handleSubmit(onFormSubmit)} className="space-y-6">
          {/* Basic Information - Chỉ hiển thị khi create hoặc edit */}
          {(mode === 'create' || mode === 'edit') && (
            <div className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {/* Title */}
                <div className="md:col-span-2">
                  <Label htmlFor="title">Tên tài liệu *</Label>
                  <Input
                    id="title"
                    {...register('title')}
                    placeholder="Nhập tên tài liệu"
                    className={errors.title ? 'border-red-500' : ''}
                  />
                  {errors.title && (
                    <p className="text-sm text-red-500 mt-1">
                      {errors.title.message}
                    </p>
                  )}
                </div>

                {/* Description */}
                <div className="md:col-span-2">
                  <Label htmlFor="description">Mô tả</Label>
                  <Textarea
                    id="description"
                    {...register('description')}
                    placeholder="Nhập mô tả tài liệu"
                    rows={3}
                    className={errors.description ? 'border-red-500' : ''}
                  />
                  {errors.description && (
                    <p className="text-sm text-red-500 mt-1">
                      {errors.description.message}
                    </p>
                  )}
                </div>

                {/* Linked Entity Type */}
                <div>
                  <Label htmlFor="linked_entity_type">Loại liên kết</Label>
                  <Select
                    value={watchedValues.linked_entity_type || ''}
                    onValueChange={(value) => setValue('linked_entity_type', value)}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Chọn loại liên kết" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="">Không liên kết</SelectItem>
                      <SelectItem value="task">Công việc</SelectItem>
                      <SelectItem value="diary">Nhật ký</SelectItem>
                      <SelectItem value="cr">Yêu cầu thay đổi</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                {/* Linked Entity ID */}
                {watchedValues.linked_entity_type && (
                  <div>
                    <Label htmlFor="linked_entity_id">ID liên kết</Label>
                    <Input
                      id="linked_entity_id"
                      {...register('linked_entity_id')}
                      placeholder="Nhập ID của đối tượng liên kết"
                      className={errors.linked_entity_id ? 'border-red-500' : ''}
                    />
                    {errors.linked_entity_id && (
                      <p className="text-sm text-red-500 mt-1">
                        {errors.linked_entity_id.message}
                      </p>
                    )}
                  </div>
                )}
              </div>
            </div>
          )}

          {/* Upload Version Comment - Chỉ hiển thị khi upload-version */}
          {mode === 'upload-version' && (
            <div>
              <Label htmlFor="comment">Ghi chú phiên bản</Label>
              <Textarea
                id="comment"
                {...register('comment')}
                placeholder="Nhập ghi chú cho phiên bản mới"
                rows={3}
                className={errors.comment ? 'border-red-500' : ''}
              />
              {errors.comment && (
                <p className="text-sm text-red-500 mt-1">
                  {errors.comment.message}
                </p>
              )}
            </div>
          )}

          <Separator />

          {/* File Upload */}
          <div className="space-y-4">
            <Label>File tài liệu {mode === 'create' ? '*' : ''}</Label>
            
            {!fileUpload.file ? (
              <div
                className={cn(
                  'border-2 border-dashed rounded-lg p-8 text-center transition-colors',
                  dragActive ? 'border-blue-500 bg-blue-50' : 'border-gray-300',
                  'hover:border-gray-400 cursor-pointer'
                )}
                onDragEnter={handleDrag}
                onDragLeave={handleDrag}
                onDragOver={handleDrag}
                onDrop={handleDrop}
                onClick={() => document.getElementById('file-input')?.click()}
              >
                <Upload className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                <p className="text-lg font-medium text-gray-900 mb-2">
                  Kéo thả file vào đây hoặc click để chọn
                </p>
                <p className="text-sm text-gray-500">
                  Hỗ trợ: PDF, Word, Excel, PowerPoint, hình ảnh (tối đa 50MB)
                </p>
                <input
                  id="file-input"
                  type="file"
                  className="hidden"
                  accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt"
                  onChange={(e) => {
                    if (e.target.files && e.target.files[0]) {
                      handleFileSelect(e.target.files[0]);
                    }
                  }}
                />
              </div>
            ) : (
              <div className="border rounded-lg p-4 space-y-3">
                <div className="flex items-start justify-between">
                  <div className="flex items-start space-x-3 flex-1">
                    {fileUpload.preview ? (
                      <img
                        src={fileUpload.preview}
                        alt="Preview"
                        className="w-12 h-12 object-cover rounded"
                      />
                    ) : (
                      <div className="w-12 h-12 bg-blue-100 rounded flex items-center justify-center">
                        <FileText className="h-6 w-6 text-blue-600" />
                      </div>
                    )}
                    <div className="flex-1 min-w-0">
                      <p className="font-medium text-gray-900 truncate">
                        {fileUpload.file.name}
                      </p>
                      <p className="text-sm text-gray-500">
                        {formatFileSize(fileUpload.file.size)}
                      </p>
                      {fileUpload.progress > 0 && fileUpload.progress < 100 && (
                        <div className="mt-2">
                          <Progress value={fileUpload.progress} className="h-2" />
                          <p className="text-xs text-gray-500 mt-1">
                            Đang upload... {fileUpload.progress}%
                          </p>
                        </div>
                      )}
                    </div>
                  </div>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={removeFile}
                    className="text-red-500 hover:text-red-700"
                  >
                    <X className="h-4 w-4" />
                  </Button>
                </div>
                
                {fileUpload.error && (
                  <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>{fileUpload.error}</AlertDescription>
                  </Alert>
                )}
              </div>
            )}

            {errors.file && (
              <p className="text-sm text-red-500">
                {errors.file.message}
              </p>
            )}
          </div>

          {/* Form Actions */}
          <div className="flex items-center justify-end space-x-3 pt-6 border-t">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={loading}
            >
              Hủy
            </Button>
            <Button
              type="submit"
              disabled={loading || !isValid || (mode === 'create' && !fileUpload.file)}
              className="min-w-[120px]"
            >
              {loading ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Đang xử lý...
                </>
              ) : (
                <>
                  <CheckCircle className="mr-2 h-4 w-4" />
                  {mode === 'create' ? 'Tạo mới' : 
                   mode === 'edit' ? 'Cập nhật' : 'Upload'}
                </>
              )}
            </Button>
          </div>
        </form>

        {/* Help Text */}
        <div className="bg-blue-50 rounded-lg p-4">
          <div className="flex items-start space-x-2">
            <Info className="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" />
            <div className="text-sm text-blue-800">
              <p className="font-medium mb-1">Lưu ý:</p>
              <ul className="list-disc list-inside space-y-1 text-xs">
                <li>File tối đa 50MB</li>
                <li>Hỗ trợ các định dạng: PDF, Word, Excel, PowerPoint, hình ảnh</li>
                {mode === 'upload-version' && (
                  <li>Phiên bản mới sẽ được tạo tự động</li>
                )}
                <li>Tài liệu có thể liên kết với công việc, nhật ký hoặc yêu cầu thay đổi</li>
              </ul>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
};

export default DocumentForm;