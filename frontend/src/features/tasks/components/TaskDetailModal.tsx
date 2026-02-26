import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Progress } from '@/components/ui/Progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/Tabs';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/Avatar';
import { Separator } from '@/components/ui/separator';
import {
  Calendar,
  Clock,
  User,
  Flag,
  FileText,
  MessageSquare,
  History,
  Edit,
  Trash2,
  CheckCircle,
  AlertCircle,
  Link as LinkIcon,
  Download,
  Eye
} from 'lucide-react';
import { format } from 'date-fns';
import { vi } from 'date-fns/locale';
import { cn } from '@/lib/utils';

// Types
interface Task {
  id: string;
  name: string;
  description: string;
  status: 'pending' | 'in_progress' | 'review' | 'completed' | 'cancelled';
  priority: 'low' | 'medium' | 'high' | 'critical';
  progress: number;
  start_date: string;
  end_date: string;
  created_at: string;
  updated_at: string;
  assignedTo?: {
    id: string;
    name: string;
    email: string;
    avatar?: string;
  };
  project: {
    id: string;
    name: string;
  };
  component?: {
    id: string;
    name: string;
  };
  dependencies: string[];
  attachments: Array<{
    id: string;
    name: string;
    size: number;
    type: string;
    url: string;
    uploaded_at: string;
  }>;
  comments: Array<{
    id: string;
    content: string;
    author: {
      id: string;
      name: string;
      avatar?: string;
    };
    created_at: string;
  }>;
  history: Array<{
    id: string;
    action: string;
    description: string;
    user: {
      id: string;
      name: string;
    };
    created_at: string;
  }>;
}

interface TaskDetailModalProps {
  task: Task | null;
  isOpen: boolean;
  onClose: () => void;
  onEdit: (taskId: string) => void;
  onDelete: (taskId: string) => void;
  onStatusChange: (taskId: string, newStatus: string) => void;
  onAddComment: (taskId: string, comment: string) => void;
}

const TaskDetailModal: React.FC<TaskDetailModalProps> = ({
  task,
  isOpen,
  onClose,
  onEdit,
  onDelete,
  onStatusChange,
  onAddComment
}) => {
  const [newComment, setNewComment] = useState('');
  const [activeTab, setActiveTab] = useState('overview');

  // Reset state when modal opens/closes
  useEffect(() => {
    if (isOpen) {
      setActiveTab('overview');
      setNewComment('');
    }
  }, [isOpen]);

  if (!task) return null;

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'pending': return 'bg-gray-100 text-gray-800';
      case 'in_progress': return 'bg-blue-100 text-blue-800';
      case 'review': return 'bg-yellow-100 text-yellow-800';
      case 'completed': return 'bg-green-100 text-green-800';
      case 'cancelled': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'low': return 'text-green-600';
      case 'medium': return 'text-yellow-600';
      case 'high': return 'text-orange-600';
      case 'critical': return 'text-red-600';
      default: return 'text-gray-600';
    }
  };

  const handleAddComment = () => {
    if (newComment.trim()) {
      onAddComment(task.id, newComment.trim());
      setNewComment('');
    }
  };

  const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-4xl max-h-[90vh] overflow-hidden">
        <DialogHeader>
          <DialogTitle className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <span className="text-xl font-semibold">{task.name}</span>
              <Badge className={cn('text-xs', getStatusColor(task.status))}>
                {task.status}
              </Badge>
              <Badge className={cn('text-xs', getPriorityColor(task.priority))}>
                <Flag className="h-3 w-3 mr-1" />
                {task.priority}
              </Badge>
            </div>
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => onEdit(task.id)}
              >
                <Edit className="h-4 w-4 mr-2" />
                Chỉnh sửa
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => onDelete(task.id)}
                className="text-red-600 hover:text-red-700"
              >
                <Trash2 className="h-4 w-4 mr-2" />
                Xóa
              </Button>
            </div>
          </DialogTitle>
        </DialogHeader>

        <div className="flex-1 overflow-hidden">
          <Tabs value={activeTab} onValueChange={setActiveTab} className="h-full">
            <TabsList className="grid w-full grid-cols-4">
              <TabsTrigger value="overview">Tổng quan</TabsTrigger>
              <TabsTrigger value="comments">Bình luận ({task.comments.length})</TabsTrigger>
              <TabsTrigger value="attachments">Tệp đính kèm ({task.attachments.length})</TabsTrigger>
              <TabsTrigger value="history">Lịch sử</TabsTrigger>
            </TabsList>

            <div className="mt-4 h-[calc(100%-3rem)] overflow-y-auto">
              {/* Overview Tab */}
              <TabsContent value="overview" className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {/* Task Information */}
                  <Card>
                    <CardHeader>
                      <CardTitle className="flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        Thông tin nhiệm vụ
                      </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                      <div>
                        <label className="text-sm font-medium text-gray-500">Mô tả</label>
                        <p className="mt-1 text-sm">{task.description || 'Không có mô tả'}</p>
                      </div>
                      
                      <div>
                        <label className="text-sm font-medium text-gray-500">Tiến độ</label>
                        <div className="mt-2">
                          <Progress value={task.progress} className="h-2" />
                          <span className="text-sm text-gray-600 mt-1">{task.progress}% hoàn thành</span>
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-4">
                        <div>
                          <label className="text-sm font-medium text-gray-500 flex items-center gap-1">
                            <Calendar className="h-4 w-4" />
                            Ngày bắt đầu
                          </label>
                          <p className="mt-1 text-sm">
                            {format(new Date(task.start_date), 'dd/MM/yyyy', { locale: vi })}
                          </p>
                        </div>
                        <div>
                          <label className="text-sm font-medium text-gray-500 flex items-center gap-1">
                            <Calendar className="h-4 w-4" />
                            Ngày kết thúc
                          </label>
                          <p className="mt-1 text-sm">
                            {format(new Date(task.end_date), 'dd/MM/yyyy', { locale: vi })}
                          </p>
                        </div>
                      </div>
                    </CardContent>
                  </Card>

                  {/* Assignment & Project Info */}
                  <Card>
                    <CardHeader>
                      <CardTitle className="flex items-center gap-2">
                        <User className="h-5 w-5" />
                        Phân công & Dự án
                      </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                      <div>
                        <label className="text-sm font-medium text-gray-500">Người thực hiện</label>
                        {task.assignedTo ? (
                          <div className="mt-2 flex items-center gap-3">
                            <Avatar className="h-8 w-8">
                              <AvatarImage src={task.assignedTo.avatar} />
                              <AvatarFallback>
                                {task.assignedTo.name.charAt(0).toUpperCase()}
                              </AvatarFallback>
                            </Avatar>
                            <div>
                              <p className="text-sm font-medium">{task.assignedTo.name}</p>
                              <p className="text-xs text-gray-500">{task.assignedTo.email}</p>
                            </div>
                          </div>
                        ) : (
                          <p className="mt-1 text-sm text-gray-500">Chưa phân công</p>
                        )}
                      </div>

                      <Separator />

                      <div>
                        <label className="text-sm font-medium text-gray-500">Dự án</label>
                        <p className="mt-1 text-sm font-medium">{task.project.name}</p>
                      </div>

                      {task.component && (
                        <div>
                          <label className="text-sm font-medium text-gray-500">Thành phần</label>
                          <p className="mt-1 text-sm">{task.component.name}</p>
                        </div>
                      )}

                      {task.dependencies.length > 0 && (
                        <div>
                          <label className="text-sm font-medium text-gray-500 flex items-center gap-1">
                            <LinkIcon className="h-4 w-4" />
                            Phụ thuộc ({task.dependencies.length})
                          </label>
                          <div className="mt-2 space-y-1">
                            {task.dependencies.map((depId) => (
                              <Badge key={depId} variant="outline" className="text-xs">
                                Task #{depId}
                              </Badge>
                            ))}
                          </div>
                        </div>
                      )}
                    </CardContent>
                  </Card>
                </div>

                {/* Quick Actions */}
                <Card>
                  <CardHeader>
                    <CardTitle>Hành động nhanh</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="flex flex-wrap gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onStatusChange(task.id, 'in_progress')}
                        disabled={task.status === 'in_progress'}
                      >
                        <Clock className="h-4 w-4 mr-2" />
                        Bắt đầu thực hiện
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onStatusChange(task.id, 'review')}
                        disabled={task.status === 'review' || task.status === 'completed'}
                      >
                        <Eye className="h-4 w-4 mr-2" />
                        Gửi kiểm tra
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onStatusChange(task.id, 'completed')}
                        disabled={task.status === 'completed'}
                      >
                        <CheckCircle className="h-4 w-4 mr-2" />
                        Hoàn thành
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>

              {/* Comments Tab */}
              <TabsContent value="comments" className="space-y-4">
                {/* Add Comment */}
                <Card>
                  <CardContent className="pt-6">
                    <div className="space-y-3">
                      <textarea
                        placeholder="Thêm bình luận..."
                        value={newComment}
                        onChange={(e) => setNewComment(e.target.value)}
                        className="w-full p-3 border rounded-md resize-none"
                        rows={3}
                      />
                      <div className="flex justify-end">
                        <Button
                          onClick={handleAddComment}
                          disabled={!newComment.trim()}
                          size="sm"
                        >
                          <MessageSquare className="h-4 w-4 mr-2" />
                          Thêm bình luận
                        </Button>
                      </div>
                    </div>
                  </CardContent>
                </Card>

                {/* Comments List */}
                <div className="space-y-4">
                  {task.comments.map((comment) => (
                    <Card key={comment.id}>
                      <CardContent className="pt-4">
                        <div className="flex gap-3">
                          <Avatar className="h-8 w-8">
                            <AvatarImage src={comment.author.avatar} />
                            <AvatarFallback>
                              {comment.author.name.charAt(0).toUpperCase()}
                            </AvatarFallback>
                          </Avatar>
                          <div className="flex-1">
                            <div className="flex items-center gap-2 mb-2">
                              <span className="font-medium text-sm">{comment.author.name}</span>
                              <span className="text-xs text-gray-500">
                                {format(new Date(comment.created_at), 'dd/MM/yyyy HH:mm', { locale: vi })}
                              </span>
                            </div>
                            <p className="text-sm text-gray-700">{comment.content}</p>
                          </div>
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                  {task.comments.length === 0 && (
                    <div className="text-center py-8 text-gray-500">
                      <MessageSquare className="h-12 w-12 mx-auto mb-3 opacity-50" />
                      <p>Chưa có bình luận nào</p>
                    </div>
                  )}
                </div>
              </TabsContent>

              {/* Attachments Tab */}
              <TabsContent value="attachments" className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {task.attachments.map((attachment) => (
                    <Card key={attachment.id}>
                      <CardContent className="pt-4">
                        <div className="flex items-center gap-3">
                          <div className="p-2 bg-gray-100 rounded">
                            <FileText className="h-6 w-6 text-gray-600" />
                          </div>
                          <div className="flex-1 min-w-0">
                            <p className="font-medium text-sm truncate">{attachment.name}</p>
                            <p className="text-xs text-gray-500">
                              {formatFileSize(attachment.size)} • {attachment.type}
                            </p>
                            <p className="text-xs text-gray-500">
                              {format(new Date(attachment.uploaded_at), 'dd/MM/yyyy', { locale: vi })}
                            </p>
                          </div>
                          <Button variant="ghost" size="sm">
                            <Download className="h-4 w-4" />
                          </Button>
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                  {task.attachments.length === 0 && (
                    <div className="col-span-2 text-center py-8 text-gray-500">
                      <FileText className="h-12 w-12 mx-auto mb-3 opacity-50" />
                      <p>Chưa có tệp đính kèm nào</p>
                    </div>
                  )}
                </div>
              </TabsContent>

              {/* History Tab */}
              <TabsContent value="history" className="space-y-4">
                <div className="space-y-4">
                  {task.history.map((entry) => (
                    <Card key={entry.id}>
                      <CardContent className="pt-4">
                        <div className="flex gap-3">
                          <div className="p-2 bg-blue-100 rounded-full">
                            <History className="h-4 w-4 text-blue-600" />
                          </div>
                          <div className="flex-1">
                            <div className="flex items-center gap-2 mb-1">
                              <span className="font-medium text-sm">{entry.action}</span>
                              <span className="text-xs text-gray-500">
                                {format(new Date(entry.created_at), 'dd/MM/yyyy HH:mm', { locale: vi })}
                              </span>
                            </div>
                            <p className="text-sm text-gray-700 mb-1">{entry.description}</p>
                            <p className="text-xs text-gray-500">bởi {entry.user.name}</p>
                          </div>
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                  {task.history.length === 0 && (
                    <div className="text-center py-8 text-gray-500">
                      <History className="h-12 w-12 mx-auto mb-3 opacity-50" />
                      <p>Chưa có lịch sử thay đổi</p>
                    </div>
                  )}
                </div>
              </TabsContent>
            </div>
          </Tabs>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Đóng
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};

export default TaskDetailModal;