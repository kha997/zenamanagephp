import React, { useState, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { useUploadDocument } from '../hooks';
import { useProjects } from '../../projects/hooks';
import type { UploadDocumentRequest } from '../types';

const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
const ALLOWED_MIME_TYPES = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'image/jpeg',
  'image/png',
  'image/gif',
  'text/plain',
];

const validateFile = (file: File): { valid: boolean; error?: string } => {
  if (file.size > MAX_FILE_SIZE) {
    return { valid: false, error: `File size must be less than 10MB. Current size: ${(file.size / 1024 / 1024).toFixed(2)}MB` };
  }
  if (!ALLOWED_MIME_TYPES.includes(file.type)) {
    return { valid: false, error: `File type not allowed. Allowed types: PDF, Word, Excel, Images, and Text files` };
  }
  return { valid: true };
};

export const CreateDocumentPage: React.FC = () => {
  const navigate = useNavigate();
  const uploadDocument = useUploadDocument();
  const { data: projectsData } = useProjects();
  const fileInputRef = useRef<HTMLInputElement>(null);
  
  const [file, setFile] = useState<File | null>(null);
  const [description, setDescription] = useState('');
  const [tags, setTags] = useState('');
  const [projectId, setProjectId] = useState<string>('');
  const [isPublic, setIsPublic] = useState(false);
  const [errors, setErrors] = useState<{ file?: string }>({});

  const handleFileSelect = useCallback((event: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = event.target.files?.[0];
    if (!selectedFile) return;
    
    const validation = validateFile(selectedFile);
    if (!validation.valid) {
      setErrors({ file: validation.error });
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
      return;
    }
    
    setFile(selectedFile);
    setErrors({});
  }, []);

  const handleSubmit = useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!file) {
      setErrors({ file: 'Please select a file to upload' });
      return;
    }
    
    try {
      const uploadData: UploadDocumentRequest = {
        file,
        description: description || undefined,
        tags: tags.split(',').map(tag => tag.trim()).filter(Boolean),
        is_public: isPublic,
      };
      
      if (projectId) {
        uploadData.project_id = parseInt(projectId);
      }
      
      const result = await uploadDocument.mutateAsync(uploadData);
      toast.success('Document uploaded successfully');
      navigate('/app/documents');
    } catch (error: any) {
      toast.error(error?.message || 'Failed to upload document');
    }
  }, [file, description, tags, projectId, isPublic, uploadDocument, navigate]);

  const handleCancel = useCallback(() => {
    navigate('/app/documents');
  }, [navigate]);

  return (
    <Container>
      <Card>
        <CardHeader>
          <CardTitle>Upload New Document</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-1">
                File <span className="text-red-500">*</span>
              </label>
              <input
                ref={fileInputRef}
                type="file"
                onChange={handleFileSelect}
                className="w-full px-3 py-2 border border-[var(--border)] rounded-lg bg-[var(--surface)] text-[var(--text)]"
                accept={ALLOWED_MIME_TYPES.join(',')}
              />
              {errors.file && (
                <p className="text-sm text-red-600 mt-1">{errors.file}</p>
              )}
              {file && (
                <p className="text-sm text-[var(--muted)] mt-1">
                  Selected: {file.name} ({(file.size / 1024 / 1024).toFixed(2)} MB)
                </p>
              )}
            </div>
            
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-1">
                Project
              </label>
              <select
                value={projectId}
                onChange={(e) => setProjectId(e.target.value)}
                className="w-full px-3 py-2 border border-[var(--border)] rounded-lg bg-[var(--surface)] text-[var(--text)]"
              >
                <option value="">No project</option>
                {projectsData?.data?.map((project) => (
                  <option key={project.id} value={project.id}>
                    {project.name}
                  </option>
                ))}
              </select>
            </div>
            
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-1">
                Description
              </label>
              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                rows={4}
                className="w-full px-3 py-2 border border-[var(--border)] rounded-lg bg-[var(--surface)] text-[var(--text)]"
                placeholder="Document description..."
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-1">
                Tags (comma-separated)
              </label>
              <Input
                value={tags}
                onChange={(e) => setTags(e.target.value)}
                placeholder="tag1, tag2, tag3"
              />
            </div>
            
            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                id="is_public"
                checked={isPublic}
                onChange={(e) => setIsPublic(e.target.checked)}
                className="w-4 h-4"
              />
              <label htmlFor="is_public" className="text-sm text-[var(--text)]">
                Make this document public
              </label>
            </div>
            
            <div className="flex items-center gap-3 pt-4">
              <Button
                type="submit"
                disabled={uploadDocument.isPending || !file}
              >
                {uploadDocument.isPending ? 'Uploading...' : 'Upload Document'}
              </Button>
              <Button
                type="button"
                variant="secondary"
                onClick={handleCancel}
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

export default CreateDocumentPage;

