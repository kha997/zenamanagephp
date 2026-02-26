import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { useTaskStore } from '@/store/tasks';
import { useProjectStore } from '@/store/projects';
import { Task } from '@/lib/types';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/Tabs';
import { Plus, Calendar, User, Clock, Network } from 'lucide-react';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';
import { TaskDependencyViewer } from '../components/TaskDependencyViewer';

interface TaskColumn {
  id: string;
  title: string;
  tasks: Task[];
}

export const TaskBoard: React.FC = () => {
  const { projectId } = useParams<{ projectId: string }>();
  const { tasks, isLoading, fetchTasks, updateTaskStatus, updateTaskDependencies } = useTaskStore();
  const { currentProject, fetchProject } = useProjectStore();
  const [columns, setColumns] = useState<TaskColumn[]>([]);
  const [activeTab, setActiveTab] = useState('kanban');

  useEffect(() => {
    if (projectId) {
      fetchProject(projectId);
      fetchTasks(projectId);
    }
  }, [projectId, fetchProject, fetchTasks]);

  useEffect(() => {
    // Tổ chức tasks theo trạng thái
    const taskColumns: TaskColumn[] = [
      {
        id: 'pending',
        title: 'Chờ thực hiện',
        tasks: tasks.filter(task => task.status === 'pending')
      },
      {
        id: 'in_progress',
        title: 'Đang thực hiện',
        tasks: tasks.filter(task => task.status === 'in_progress')
      },
      {
        id: 'review',
        title: 'Đang kiểm tra',
        tasks: tasks.filter(task => task.status === 'review')
      },
      {
        id: 'completed',
        title: 'Hoàn thành',
        tasks: tasks.filter(task => task.status === 'completed')
      }
    ];
    setColumns(taskColumns);
  }, [tasks]);

  const handleDragEnd = (result: any) => {
    if (!result.destination) return;

    const { source, destination, draggableId } = result;
    
    if (source.droppableId !== destination.droppableId) {
      // Cập nhật trạng thái task khi di chuyển giữa các cột
      const taskId = draggableId;
      const newStatus = destination.droppableId;
      const isTaskStatus = (
        status: string
      ): status is Task['status'] =>
        ['pending', 'in_progress', 'review', 'completed', 'cancelled'].includes(status);

      if (projectId && isTaskStatus(newStatus)) {
        updateTaskStatus(projectId, taskId, newStatus);
      }
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'pending': return 'bg-gray-100 text-gray-800';
      case 'in_progress': return 'bg-blue-100 text-blue-800';
      case 'review': return 'bg-yellow-100 text-yellow-800';
      case 'completed': return 'bg-green-100 text-green-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'high': return 'bg-red-100 text-red-800';
      case 'medium': return 'bg-orange-100 text-orange-800';
      case 'low': return 'bg-green-100 text-green-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const handleUpdateDependencies = (taskId: string, dependencies: string[]) => {
    updateTaskDependencies(taskId, dependencies);
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Bảng công việc</h1>
          <p className="text-gray-600">
            {currentProject ? `Dự án: ${currentProject.name}` : 'Quản lý công việc'}
          </p>
        </div>
        <Button>
          <Plus className="w-4 h-4 mr-2" />
          Thêm công việc
        </Button>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList>
          <TabsTrigger value="kanban">
            <Calendar className="w-4 h-4 mr-2" />
            Kanban Board
          </TabsTrigger>
          <TabsTrigger value="dependencies">
            <Network className="w-4 h-4 mr-2" />
            Dependency Viewer
          </TabsTrigger>
        </TabsList>

        <TabsContent value="kanban" className="mt-6">
          {/* Existing Kanban Board Content */}
          <DragDropContext onDragEnd={handleDragEnd}>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              {columns.map((column) => (
                <div key={column.id} className="bg-gray-50 rounded-lg p-4">
                  <div className="flex justify-between items-center mb-4">
                    <h3 className="font-semibold text-gray-900">{column.title}</h3>
                    <Badge variant="secondary">{column.tasks.length}</Badge>
                  </div>
                  
                  <Droppable droppableId={column.id}>
                    {(provided, snapshot) => (
                      <div
                        ref={provided.innerRef}
                        {...provided.droppableProps}
                        className={`space-y-3 min-h-[200px] ${
                          snapshot.isDraggingOver ? 'bg-blue-50' : ''
                        }`}
                      >
                        {column.tasks.map((task, index) => (
                          <Draggable
                            key={task.id}
                            draggableId={task.id.toString()}
                            index={index}
                          >
                            {(provided, snapshot) => (
                              <div
                                ref={provided.innerRef}
                                {...provided.draggableProps}
                                {...provided.dragHandleProps}
                              >
                              <Card
                                className={`p-4 cursor-move ${
                                  snapshot.isDragging ? 'shadow-lg' : 'hover:shadow-md'
                                } transition-shadow`}
                              >
                                <div className="space-y-3">
                                  <div className="flex justify-between items-start">
                                    <h4 className="font-medium text-gray-900 text-sm leading-tight">
                                      {task.name}
                                    </h4>
                                    {task.priority && (
                                      <Badge 
                                        className={getPriorityColor(task.priority)}
                                      >
                                        {task.priority}
                                      </Badge>
                                    )}
                                  </div>
                                  
                                  <div className="flex items-center space-x-2 text-xs text-gray-500">
                                    <Calendar className="w-3 h-3" />
                                    <span>
                                      {new Date(task.start_date).toLocaleDateString('vi-VN')} - 
                                      {new Date(task.end_date).toLocaleDateString('vi-VN')}
                                    </span>
                                  </div>
                                  
                                  {task.assignees && task.assignees.length > 0 && (
                                    <div className="flex items-center space-x-2 text-xs text-gray-500">
                                      <User className="w-3 h-3" />
                                      <span>{task.assignees.length} người thực hiện</span>
                                    </div>
                                  )}
                                  
                                  <div className="flex items-center justify-between">
                                    <Badge 
                                      className={getStatusColor(task.status)}
                                    >
                                      {task.status}
                                    </Badge>
                                    
                                    <div className="flex items-center space-x-1 text-xs text-gray-500">
                                      <Clock className="w-3 h-3" />
                                      <span>{task.progress}%</span>
                                    </div>
                                  </div>
                                </div>
                              </Card>
                              </div>
                            )}
                          </Draggable>
                        ))}
                        {provided.placeholder}
                      </div>
                    )}
                  </Droppable>
                </div>
              ))}
            </div>
          </DragDropContext>

          {tasks.length === 0 && (
            <div className="text-center py-12">
              <div className="text-gray-400 mb-4">
                <Calendar className="w-12 h-12 mx-auto" />
              </div>
              <h3 className="text-lg font-medium text-gray-900 mb-2">Chưa có công việc</h3>
              <p className="text-gray-600 mb-4">
                Bắt đầu bằng cách tạo công việc đầu tiên cho dự án này.
              </p>
              <Button>
                <Plus className="w-4 h-4 mr-2" />
                Tạo công việc đầu tiên
              </Button>
            </div>
          )}
        </TabsContent>

        <TabsContent value="dependencies" className="mt-6">
          <div className="h-[600px] border rounded-lg">
            <TaskDependencyViewer 
              tasks={tasks} 
              onUpdateDependencies={handleUpdateDependencies}
            />
          </div>
        </TabsContent>
      </Tabs>
    </div>
  );
};
