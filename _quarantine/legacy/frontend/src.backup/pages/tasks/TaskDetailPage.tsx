import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { Badge } from '../../shared/ui/badge';
import { Modal } from '../../shared/ui/modal';
import { Input } from '../../components/ui/Input';
import { Label } from '../../components/ui/label';
import { Textarea } from '../../components/ui/Textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../components/ui/Select';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../shared/api/client';
import toast from 'react-hot-toast';
import { 
  ArrowLeftIcon,
  PencilIcon,
  UserIcon,
  CalendarIcon,
  FlagIcon,
  ChartBarIcon,
  PaperClipIcon,
  ChatBubbleLeftRightIcon,
  CheckCircleIcon,
  ClockIcon,
} from '@heroicons/react/24/outline';
import { useAuthStore } from '../../shared/auth/store';
import { formatDate } from '../../lib/utils';

interface Task {
  id: string;
  name: string;
  title: string;
  description: string;
  status: string;
  priority: string;
  assignee_id?: string;
  assignee?: {
    id: string;
    name: string;
    email: string;
  };
  project_id: string;
  project?: {
    id: string;
    name: string;
  };
  start_date?: string;
  end_date?: string;
  progress_percent: number;
  estimated_hours?: number;
  actual_hours?: number;
  created_at: string;
  updated_at: string;
}

interface Comment {
  id: string;
  content: string;
  user: {
    id: string;
    name: string;
    email: string;
  };
  created_at: string;
  updated_at: string;
}

interface Attachment {
  id: string;
  filename: string;
  file_path: string;
  file_size: number;
  mime_type: string;
  uploaded_by: {
    id: string;
    name: string;
  };
  created_at: string;
}

export default function TaskDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { user } = useAuthStore();
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isCommentModalOpen, setIsCommentModalOpen] = useState(false);
  const [commentText, setCommentText] = useState('');
  const [editForm, setEditForm] = useState({
    name: '',
    description: '',
    status: '',
    priority: '',
    assignee_id: '',
    start_date: '',
    end_date: '',
    progress_percent: 0,
  });

  // Fetch task details
  const { data: taskResponse, isLoading: taskLoading } = useQuery({
    queryKey: ['tasks', id],
    queryFn: async () => {
      const response = await apiClient.get(`/app/tasks/${id}`);
      return response.data;
    },
    enabled: !!id,
  });

  // Fetch comments
  const { data: commentsResponse, isLoading: commentsLoading } = useQuery({
    queryKey: ['task-comments', id],
    queryFn: async () => {
      const response = await apiClient.get(`/api/task-comments/task/${id}`);
      return response.data;
    },
    enabled: !!id,
  });

  // Fetch attachments
  const { data: attachmentsResponse, isLoading: attachmentsLoading } = useQuery({
    queryKey: ['task-attachments', id],
    queryFn: async () => {
      const response = await apiClient.get(`/api/task-attachments/task/${id}`);
      return response.data;
    },
    enabled: !!id,
  });

  // Fetch team members for assign dropdown
  const { data: teamResponse } = useQuery({
    queryKey: ['team-members'],
    queryFn: async () => {
      const response = await apiClient.get('/app/dashboard/team-status');
      return response.data;
    },
  });

  const task: Task | undefined = taskResponse?.data;
  const comments: Comment[] = commentsResponse?.data || [];
  const attachments: Attachment[] = attachmentsResponse?.data || [];
  const teamMembers = teamResponse?.data || [];

  // Update task mutation
  const updateTaskMutation = useMutation({
    mutationFn: async (data: any) => {
      const response = await apiClient.put(`/app/tasks/${id}`, data);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks', id] });
      setIsEditModalOpen(false);
      toast.success('Task updated successfully');
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message || 'Failed to update task');
    },
  });

  // Assign task mutation
  const assignTaskMutation = useMutation({
    mutationFn: async (userId: string) => {
      const response = await apiClient.post(`/app/tasks/${id}/assign`, { assignee_id: userId });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks', id] });
      toast.success('Task assigned successfully');
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message || 'Failed to assign task');
    },
  });

  // Unassign task mutation
  const unassignTaskMutation = useMutation({
    mutationFn: async () => {
      const response = await apiClient.post(`/app/tasks/${id}/unassign`);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks', id] });
      toast.success('Task unassigned successfully');
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message || 'Failed to unassign task');
    },
  });

  // Update progress mutation
  const updateProgressMutation = useMutation({
    mutationFn: async (progress: number) => {
      const response = await apiClient.post(`/app/tasks/${id}/progress`, { progress_percent: progress });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks', id] });
      toast.success('Progress updated successfully');
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message || 'Failed to update progress');
    },
  });

  // Add comment mutation
  const addCommentMutation = useMutation({
    mutationFn: async (content: string) => {
      const response = await apiClient.post('/api/task-comments', {
        task_id: id,
        content,
      });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['task-comments', id] });
      setCommentText('');
      setIsCommentModalOpen(false);
      toast.success('Comment added successfully');
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message || 'Failed to add comment');
    },
  });

  // Upload attachment mutation
  const uploadAttachmentMutation = useMutation({
    mutationFn: async (file: File) => {
      const formData = new FormData();
      formData.append('task_id', id!);
      formData.append('file', file);
      const response = await apiClient.post('/api/task-attachments', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['task-attachments', id] });
      toast.success('Attachment uploaded successfully');
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message || 'Failed to upload attachment');
    },
  });

  // Initialize edit form when task loads
  useEffect(() => {
    if (task && !isEditModalOpen) {
      setEditForm({
        name: task.name || task.title || '',
        description: task.description || '',
        status: task.status || '',
        priority: task.priority || '',
        assignee_id: task.assignee_id || '',
        start_date: task.start_date || '',
        end_date: task.end_date || '',
        progress_percent: task.progress_percent || 0,
      });
    }
  }, [task, isEditModalOpen]);

  const handleEdit = () => {
    if (task) {
      setEditForm({
        name: task.name || task.title || '',
        description: task.description || '',
        status: task.status || '',
        priority: task.priority || '',
        assignee_id: task.assignee_id || '',
        start_date: task.start_date || '',
        end_date: task.end_date || '',
        progress_percent: task.progress_percent || 0,
      });
      setIsEditModalOpen(true);
    }
  };

  const handleSave = () => {
    updateTaskMutation.mutate(editForm);
  };

  const handleAssign = (userId: string) => {
    assignTaskMutation.mutate(userId);
  };

  const handleUnassign = () => {
    unassignTaskMutation.mutate();
  };

  const handleAddComment = () => {
    if (commentText.trim()) {
      addCommentMutation.mutate(commentText);
    }
  };

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      uploadAttachmentMutation.mutate(file);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'done':
      case 'completed':
        return 'bg-green-100 text-green-800';
      case 'in_progress':
        return 'bg-blue-100 text-blue-800';
      case 'blocked':
        return 'bg-red-100 text-red-800';
      case 'backlog':
        return 'bg-gray-100 text-gray-800';
      default:
        return 'bg-yellow-100 text-yellow-800';
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'urgent':
        return 'bg-red-100 text-red-800';
      case 'high':
        return 'bg-orange-100 text-orange-800';
      case 'medium':
        return 'bg-yellow-100 text-yellow-800';
      case 'low':
        return 'bg-green-100 text-green-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  if (taskLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-center h-64">
          <div className="text-[var(--color-text-muted)]">Loading task...</div>
        </div>
      </div>
    );
  }

  if (!task) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-center h-64">
          <div className="text-red-500">
            <h3 className="text-lg font-medium mb-2">Task not found</h3>
            <Button variant="outline" onClick={() => navigate('/app/tasks')}>
              Back to Tasks
            </Button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button variant="ghost" onClick={() => navigate('/app/tasks')}>
            <ArrowLeftIcon className="h-5 w-5 mr-2" />
            Back
          </Button>
          <div>
            <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">{task.name || task.title}</h1>
            {task.project && (
              <p className="text-sm text-[var(--color-text-muted)]">
                Project: {task.project.name}
              </p>
            )}
          </div>
        </div>
        <Button onClick={handleEdit}>
          <PencilIcon className="h-5 w-5 mr-2" />
          Edit
        </Button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-6">
          {/* Task Info */}
          <Card>
            <CardHeader>
              <CardTitle>Task Details</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <Label>Description</Label>
                <p className="text-[var(--color-text-muted)] mt-1">
                  {task.description || 'No description provided'}
                </p>
              </div>

              <div>
                <Label>Status</Label>
                <div className="mt-1">
                  <Badge className={getStatusColor(task.status)}>{task.status}</Badge>
                </div>
              </div>
              <div>
                <Label>Priority</Label>
                <div className="mt-1">
                  <Badge className={getPriorityColor(task.priority)}>{task.priority}</Badge>
                </div>
              </div>

              {task.start_date && (
                <div className="flex items-center gap-2">
                  <CalendarIcon className="h-5 w-5 text-[var(--color-text-muted)]" />
                  <span className="text-sm text-[var(--color-text-muted)]">
                    {formatDate(task.start_date)} - {task.end_date ? formatDate(task.end_date) : 'No due date'}
                  </span>
                </div>
              )}

              <div>
                <Label>Progress</Label>
                <div className="mt-2">
                  <div className="flex items-center gap-2">
                    <div className="flex-1 bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-blue-600 h-2 rounded-full"
                        style={{ width: `${task.progress_percent}%` }}
                      />
                    </div>
                    <span className="text-sm font-medium">{task.progress_percent}%</span>
                  </div>
                </div>
              </div>

              {task.estimated_hours && (
                <div className="flex items-center gap-2">
                  <ClockIcon className="h-5 w-5 text-[var(--color-text-muted)]" />
                  <span className="text-sm text-[var(--color-text-muted)]">
                    Estimated: {task.estimated_hours}h
                    {task.actual_hours && ` | Actual: ${task.actual_hours}h`}
                  </span>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Comments Section */}
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                  <ChatBubbleLeftRightIcon className="h-5 w-5" />
                  Comments ({comments.length})
                </CardTitle>
                <Button size="sm" onClick={() => setIsCommentModalOpen(true)}>
                  Add Comment
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              {commentsLoading ? (
                <div className="text-center py-4">Loading comments...</div>
              ) : comments.length === 0 ? (
                <div className="text-center py-8 text-[var(--color-text-muted)]">
                  No comments yet. Be the first to comment!
                </div>
              ) : (
                <div className="space-y-4">
                  {comments.map((comment) => (
                    <div key={comment.id} className="border-b pb-4 last:border-b-0">
                      <div className="flex items-start gap-3">
                        <div className="flex-1">
                          <div className="flex items-center gap-2 mb-1">
                            <span className="font-medium text-[var(--color-text-primary)]">
                              {comment.user.name}
                            </span>
                            <span className="text-xs text-[var(--color-text-muted)]">
                              {formatDate(comment.created_at)}
                            </span>
                          </div>
                          <p className="text-sm text-[var(--color-text-muted)]">{comment.content}</p>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Attachments Section */}
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                  <PaperClipIcon className="h-5 w-5" />
                  Attachments ({attachments.length})
                </CardTitle>
                <label>
                  <input
                    type="file"
                    className="hidden"
                    onChange={handleFileUpload}
                    disabled={uploadAttachmentMutation.isPending}
                  />
                  <Button size="sm" asChild>
                    <span>Upload</span>
                  </Button>
                </label>
              </div>
            </CardHeader>
            <CardContent>
              {attachmentsLoading ? (
                <div className="text-center py-4">Loading attachments...</div>
              ) : attachments.length === 0 ? (
                <div className="text-center py-8 text-[var(--color-text-muted)]">
                  No attachments yet.
                </div>
              ) : (
                <div className="space-y-2">
                  {attachments.map((attachment) => (
                    <div
                      key={attachment.id}
                      className="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50"
                    >
                      <div className="flex items-center gap-3">
                        <PaperClipIcon className="h-5 w-5 text-[var(--color-text-muted)]" />
                        <div>
                          <p className="text-sm font-medium text-[var(--color-text-primary)]">
                            {attachment.filename}
                          </p>
                          <p className="text-xs text-[var(--color-text-muted)]">
                            {(attachment.file_size / 1024).toFixed(2)} KB
                          </p>
                        </div>
                      </div>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                          window.open(`/api/task-attachments/${attachment.id}/download`, '_blank');
                        }}
                      >
                        Download
                      </Button>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Assignee Card */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <UserIcon className="h-5 w-5" />
                Assignee
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {task.assignee ? (
                <div className="space-y-2">
                  <div className="flex items-center gap-2">
                    <div className="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                      <span className="text-sm font-medium text-blue-800">
                        {task.assignee.name.charAt(0).toUpperCase()}
                      </span>
                    </div>
                    <div>
                      <p className="font-medium text-[var(--color-text-primary)]">{task.assignee.name}</p>
                      <p className="text-xs text-[var(--color-text-muted)]">{task.assignee.email}</p>
                    </div>
                  </div>
                  <Button variant="outline" size="sm" onClick={handleUnassign} className="w-full">
                    Unassign
                  </Button>
                </div>
              ) : (
                <div className="space-y-2">
                  <p className="text-sm text-[var(--color-text-muted)]">No assignee</p>
                  <Select onValueChange={handleAssign}>
                    <SelectTrigger>
                      <SelectValue placeholder="Assign to..." />
                    </SelectTrigger>
                    <SelectContent>
                      {teamMembers.map((member: any) => (
                        <SelectItem key={member.id} value={member.id}>
                          {member.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Quick Actions */}
          <Card>
            <CardHeader>
              <CardTitle>Quick Actions</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              <Button
                variant="outline"
                className="w-full justify-start"
                onClick={() => {
                  const newProgress = Math.min(task.progress_percent + 25, 100);
                  updateProgressMutation.mutate(newProgress);
                }}
                disabled={updateProgressMutation.isPending || task.progress_percent >= 100}
              >
                <ChartBarIcon className="h-5 w-5 mr-2" />
                Update Progress
              </Button>
              {task.status !== 'done' && task.status !== 'completed' && (
                <Button
                  variant="outline"
                  className="w-full justify-start"
                  onClick={() => {
                    updateTaskMutation.mutate({ status: 'done' });
                  }}
                >
                  <CheckCircleIcon className="h-5 w-5 mr-2" />
                  Mark as Done
                </Button>
              )}
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Comment Modal */}
      <Modal
        open={isCommentModalOpen}
        onOpenChange={setIsCommentModalOpen}
        title="Add Comment"
        primaryAction={{
          label: addCommentMutation.isPending ? 'Adding...' : 'Add Comment',
          variant: 'primary',
          loading: addCommentMutation.isPending,
          onClick: handleAddComment,
        }}
        secondaryAction={{
          label: 'Cancel',
          variant: 'outline',
          onClick: () => setIsCommentModalOpen(false),
        }}
      >
        <div className="space-y-4">
          <div>
            <Label>Comment</Label>
            <Textarea
              value={commentText}
              onChange={(e) => setCommentText(e.target.value)}
              placeholder="Add a comment..."
              rows={4}
            />
          </div>
        </div>
      </Modal>

      {/* Edit Modal */}
      <Modal
        open={isEditModalOpen}
        onOpenChange={setIsEditModalOpen}
        title="Edit Task"
        primaryAction={{
          label: updateTaskMutation.isPending ? 'Saving...' : 'Save Changes',
          variant: 'primary',
          loading: updateTaskMutation.isPending,
          onClick: handleSave,
        }}
        secondaryAction={{
          label: 'Cancel',
          variant: 'outline',
          onClick: () => setIsEditModalOpen(false),
        }}
      >
        <div className="space-y-4">
            <div>
              <Label>Task Name</Label>
              <Input
                value={editForm.name}
                onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
                placeholder="Task name"
              />
            </div>
            <div>
              <Label>Description</Label>
              <Textarea
                value={editForm.description}
                onChange={(e) => setEditForm({ ...editForm, description: e.target.value })}
                placeholder="Task description"
                rows={4}
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Status</Label>
                <Select value={editForm.status} onValueChange={(value) => setEditForm({ ...editForm, status: value })}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="backlog">Backlog</SelectItem>
                    <SelectItem value="in_progress">In Progress</SelectItem>
                    <SelectItem value="blocked">Blocked</SelectItem>
                    <SelectItem value="done">Done</SelectItem>
                    <SelectItem value="canceled">Canceled</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>Priority</Label>
                <Select value={editForm.priority} onValueChange={(value) => setEditForm({ ...editForm, priority: value })}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="low">Low</SelectItem>
                    <SelectItem value="normal">Normal</SelectItem>
                    <SelectItem value="high">High</SelectItem>
                    <SelectItem value="urgent">Urgent</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Start Date</Label>
                <Input
                  type="date"
                  value={editForm.start_date}
                  onChange={(e) => setEditForm({ ...editForm, start_date: e.target.value })}
                />
              </div>
              <div>
                <Label>End Date</Label>
                <Input
                  type="date"
                  value={editForm.end_date}
                  onChange={(e) => setEditForm({ ...editForm, end_date: e.target.value })}
                />
              </div>
            </div>
            <div>
              <Label>Progress ({editForm.progress_percent}%)</Label>
              <Input
                type="range"
                min="0"
                max="100"
                value={editForm.progress_percent}
                onChange={(e) => setEditForm({ ...editForm, progress_percent: parseInt(e.target.value) })}
                className="w-full"
              />
            </div>
            <div>
              <Label>Assign To</Label>
              <Select value={editForm.assignee_id} onValueChange={(value) => setEditForm({ ...editForm, assignee_id: value })}>
                <SelectTrigger>
                  <SelectValue placeholder="Select assignee" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">Unassigned</SelectItem>
                  {teamMembers.map((member: any) => (
                    <SelectItem key={member.id} value={member.id}>
                      {member.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
        </div>
      </Modal>
    </div>
  );
}

