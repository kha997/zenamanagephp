import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useProjectStore } from '@/store/projects';
import { useTaskStore } from '@/store/tasks';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/Tabs';
import { ArrowLeft, Edit, Users, Calendar, DollarSign, BarChart3 } from 'lucide-react';

export const ProjectDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const { currentProject, isLoading, fetchProject } = useProjectStore();
  const { tasks, fetchTasksByProject } = useTaskStore();

  useEffect(() => {
    if (id) {
      fetchProject(parseInt(id));
      fetchTasksByProject(parseInt(id));
    }
  }, [id, fetchProject, fetchTasksByProject]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (!currentProject) {
    return (
      <div className="text-center py-12">
        <h3 className="text-lg font-medium text-gray-900 mb-2">Không tìm thấy dự án</h3>
        <p className="text-gray-600 mb-4">Dự án bạn đang tìm kiếm không tồn tại hoặc đã bị xóa.</p>
        <Button asChild>
          <Link to="/projects">Quay lại danh sách dự án</Link>
        </Button>
      </div>
    );
  }

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active': return 'bg-green-100 text-green-800';
      case 'completed': return 'bg-blue-100 text-blue-800';
      case 'on_hold': return 'bg-yellow-100 text-yellow-800';
      case 'cancelled': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-4">
          <Button variant="outline" size="sm" asChild>
            <Link to="/projects">
              <ArrowLeft className="w-4 h-4 mr-2" />
              Quay lại
            </Link>
          </Button>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">{currentProject.name}</h1>
            <div className="flex items-center space-x-2 mt-1">
              <Badge className={getStatusColor(currentProject.status)}>
                {currentProject.status}
              </Badge>
              <span className="text-gray-500">•</span>
              <span className="text-gray-600">Tiến độ: {currentProject.progress}%</span>
            </div>
          </div>
        </div>
        <Button>
          <Edit className="w-4 h-4 mr-2" />
          Chỉnh sửa
        </Button>
      </div>

      {/* Project Stats */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <Card>
          <div className="p-6">
            <div className="flex items-center">
              <div className="p-2 bg-blue-100 rounded-lg">
                <BarChart3 className="w-6 h-6 text-blue-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Tiến độ</p>
                <p className="text-2xl font-bold text-gray-900">{currentProject.progress}%</p>
              </div>
            </div>
          </div>
        </Card>
        
        <Card>
          <div className="p-6">
            <div className="flex items-center">
              <div className="p-2 bg-green-100 rounded-lg">
                <DollarSign className="w-6 h-6 text-green-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Chi phí thực tế</p>
                <p className="text-2xl font-bold text-gray-900">
                  {currentProject.actual_cost?.toLocaleString('vi-VN')}đ
                </p>
              </div>
            </div>
          </div>
        </Card>
        
        <Card>
          <div className="p-6">
            <div className="flex items-center">
              <div className="p-2 bg-purple-100 rounded-lg">
                <Users className="w-6 h-6 text-purple-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Số nhiệm vụ</p>
                <p className="text-2xl font-bold text-gray-900">{tasks.length}</p>
              </div>
            </div>
          </div>
        </Card>
        
        <Card>
          <div className="p-6">
            <div className="flex items-center">
              <div className="p-2 bg-orange-100 rounded-lg">
                <Calendar className="w-6 h-6 text-orange-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">Thời gian còn lại</p>
                <p className="text-2xl font-bold text-gray-900">
                  {Math.ceil((new Date(currentProject.end_date).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24))} ngày
                </p>
              </div>
            </div>
          </div>
        </Card>
      </div>

      {/* Project Details Tabs */}
      <Tabs defaultValue="overview" className="space-y-4">
        <TabsList>
          <TabsTrigger value="overview">Tổng quan</TabsTrigger>
          <TabsTrigger value="tasks">Nhiệm vụ</TabsTrigger>
          <TabsTrigger value="timeline">Tiến độ</TabsTrigger>
          <TabsTrigger value="team">Nhóm</TabsTrigger>
        </TabsList>
        
        <TabsContent value="overview" className="space-y-4">
          <Card>
            <div className="p-6">
              <h3 className="text-lg font-semibold mb-4">Mô tả dự án</h3>
              <p className="text-gray-600 leading-relaxed">
                {currentProject.description || 'Chưa có mô tả cho dự án này.'}
              </p>
            </div>
          </Card>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <Card>
              <div className="p-6">
                <h3 className="text-lg font-semibold mb-4">Thông tin thời gian</h3>
                <div className="space-y-3">
                  <div className="flex justify-between">
                    <span className="text-gray-600">Ngày bắt đầu:</span>
                    <span className="font-medium">
                      {new Date(currentProject.start_date).toLocaleDateString('vi-VN')}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Ngày kết thúc:</span>
                    <span className="font-medium">
                      {new Date(currentProject.end_date).toLocaleDateString('vi-VN')}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Thời gian thực hiện:</span>
                    <span className="font-medium">
                      {Math.ceil((new Date(currentProject.end_date).getTime() - new Date(currentProject.start_date).getTime()) / (1000 * 60 * 60 * 24))} ngày
                    </span>
                  </div>
                </div>
              </div>
            </Card>
            
            <Card>
              <div className="p-6">
                <h3 className="text-lg font-semibold mb-4">Tiến độ thực hiện</h3>
                <div className="space-y-4">
                  <div>
                    <div className="flex justify-between mb-2">
                      <span className="text-gray-600">Hoàn thành</span>
                      <span className="font-medium">{currentProject.progress}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-3">
                      <div 
                        className="bg-blue-600 h-3 rounded-full transition-all duration-300"
                        style={{ width: `${currentProject.progress}%` }}
                      ></div>
                    </div>
                  </div>
                </div>
              </div>
            </Card>
          </div>
        </TabsContent>
        
        <TabsContent value="tasks">
          <Card>
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-semibold">Danh sách nhiệm vụ</h3>
                <Button size="sm">
                  Thêm nhiệm vụ
                </Button>
              </div>
              
              {tasks.length > 0 ? (
                <div className="space-y-3">
                  {tasks.map((task) => (
                    <div key={task.id} className="flex items-center justify-between p-3 border rounded-lg">
                      <div>
                        <h4 className="font-medium">{task.name}</h4>
                        <p className="text-sm text-gray-600">
                          {new Date(task.start_date).toLocaleDateString('vi-VN')} - {new Date(task.end_date).toLocaleDateString('vi-VN')}
                        </p>
                      </div>
                      <Badge className={getStatusColor(task.status)}>
                        {task.status}
                      </Badge>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-gray-600 text-center py-8">Chưa có nhiệm vụ nào được tạo.</p>
              )}
            </div>
          </Card>
        </TabsContent>
        
        <TabsContent value="timeline">
          <Card>
            <div className="p-6">
              <h3 className="text-lg font-semibold mb-4">Biểu đồ tiến độ</h3>
              <p className="text-gray-600">Biểu đồ Gantt sẽ được hiển thị ở đây.</p>
            </div>
          </Card>
        </TabsContent>
        
        <TabsContent value="team">
          <Card>
            <div className="p-6">
              <h3 className="text-lg font-semibold mb-4">Thành viên nhóm</h3>
              <p className="text-gray-600">Danh sách thành viên tham gia dự án sẽ được hiển thị ở đây.</p>
            </div>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
};