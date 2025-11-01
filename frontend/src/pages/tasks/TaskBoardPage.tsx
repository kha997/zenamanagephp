import React, { useState, useEffect } from 'react';
import { DragDropContext, Droppable, Draggable, DropResult } from 'react-beautiful-dnd';
import { useTasksStore } from '@/store/tasks';
import { useProjectsStore } from '@/store/projects';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card';
import { Loading } from '@/components/ui/Loading';
import { Select } from '@/components/ui/Select';
import { 
  Plus, 
  Calendar,
  User,
  AlertTriangle,
  Clock,
  List,
  Filter
} from 'lucide-react';
import { Link } from 'react-router-dom';
import type { Task } from '@/lib/types';

/**
 * Trang Kanban board để quản lý nhiệm vụ theo trạng thái
 * Hỗ trợ drag & drop để thay đổi trạng thái nhiệm vụ
 */
export const TaskBoardPage: React.FC = () => {
  const { 
    tasks, 
    isLoading, 
    fetchTasks,
    updateTaskStatus 
  } = useTasksStore();
  
  const { projects } = useProjectsStore();
  
  const [selectedProject, setSelectedProject] = useState('');
  const [selectedAssignee, setSelectedAssignee] = useState('');

  // Load dữ liệu khi component mount
  useEffect(() => {
    if (selectedProject) {
      fetchTasks(selectedProject, { 
        assigned_to: selectedAssignee 
      });
    }
  }, [selectedProject, selectedAssignee, fetchTasks]);

  // Định nghĩa các cột trạng thái
  const columns = [
    {
      id: 'pending',
      title: 'Chờ xử lý',
      color: 'bg-gray-100',
      headerColor: 'bg-gray-200'
    },
    {
      id: 'in_progress',
      title: 'Đang thực hiện',
      color: 'bg-blue-50',
      headerColor: 'bg-blue-100'
    },
    {
      id: 'review',
      title: 'Đang kiểm tra',
      color: 'bg-yellow-50',
      headerColor: 'bg-yellow-100'
    },
    {
      id: 'completed',
      title: 'Hoàn thành',
      color: 'bg-green-50',
      headerColor: 'bg-green-100'
    }
  ];

  // Nhóm tasks theo trạng thái
  const tasksByStatus = React.useMemo(() => {
    const grouped: Record<string, Task[]> = {};
    columns.forEach(col => {
      grouped[col.id] = tasks.filter(task => task.status === col.id);
    });
    return grouped;
  }, [tasks]);

  // Xử lý drag & drop
  const handleDragEnd = async (result: DropResult) => {
    const { destination, source, draggableId } = result;

    // Không có destination hoặc không thay đổi vị trí
    if (!destination || (
      destination.droppableId === source.droppableId &&
      destination.index === source.index
    )) {
      return;
    }

    // Cần có project được chọn để cập nhật task
    if (!selectedProject) {
      console.warn('No project selected for task update');
      return;
    }

    // Cập nhật trạng thái task
    const newStatus = destination.droppableId;
    await updateTaskStatus(selectedProject, draggableId, newStatus);
  };

  // Component TaskCard
  const TaskCard: React.FC<{ task: Task; index: number }> = ({ task, index }) => {
    const isOverdue = new Date(task.end_date) < new Date() && task.status !== 'completed';
    const project = projects.find(p => p.id === task.project_id);

    return (
      <Draggable draggableId={task.id} index={index}>
        {(provided, snapshot) => (
          <div
            ref={provided.innerRef}
            {...provided.draggableProps}
            {...provided.dragHandleProps}
            className={`mb-3 ${
              snapshot.isDragging ? 'rotate-2 shadow-lg' : ''
            }`}
          >
            <Card className="cursor-pointer hover:shadow-md transition-shadow">
              <CardContent className="p-4">
                <div className="space-y-3">
                  {/* Task Title */}
                  <div>
                    <Link 
                      to={`/tasks/${task.id}`}
                      className="font-medium text-gray-900 hover:text-blue-600 line-clamp-2"
                    >
                      {task.name}
                    </Link>
                    {project && (
                      <p className="text-xs text-gray-500 mt-1">{project.name}</p>
                    )}
                  </div>

                  {/* Priority Badge */}
                  <div className="flex items-center justify-between">
                    <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                      task.priority === 'high' 
                        ? 'bg-red-100 text-red-800'
                        : task.priority === 'medium'
                        ? 'bg-yellow-100 text-yellow-800'
                        : 'bg-green-100 text-green-800'
                    }`}>
                      {task.priority === 'high' ? 'Cao' :
                       task.priority === 'medium' ? 'TB' : 'Thấp'}
                    </span>
                  </div>

                  {/* Assignee */}
                  {task.assigned_to && (
                    <div className="flex items-center text-sm text-gray-600">
                      <User className="w-4 h-4 mr-2" />
                      <span className="truncate">{task.assigned_to.name}</span>
                    </div>
                  )}

                  {/* Due Date */}
                  <div className={`flex items-center text-sm ${
                    isOverdue ? 'text-red-600' : 'text-gray-600'
                  }`}>
                    <Calendar className="w-4 h-4 mr-2" />
                    <span>{new Date(task.end_date).toLocaleDateString('vi-VN')}</span>
                    {isOverdue && <AlertTriangle className="w-4 h-4 ml-1" />}
                  </div>

                  {/* Progress Bar */}
                  {task.progress !== undefined && (
                    <div>
                      <div className="flex items-center justify-between mb-1">
                        <span className="text-xs text-gray-600">Tiến độ</span>
                        <span className="text-xs font-medium">{task.progress}%</span>
                      </div>
                      <div className="w-full bg-gray-200 rounded-full h-1.5">
                        <div 
                          className="bg-blue-600 h-1.5 rounded-full transition-all duration-300"
                          style={{ width: `${task.progress}%` }}
                        />
                      </div>
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>
          </div>
        )}
      </Draggable>
    );
  };

  if (isLoading) {
    return <Loading.Skeleton />;
  }

  // Show message when no project is selected
  if (!selectedProject) {
    return (
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Bảng Kanban</h1>
            <p className="text-gray-600 mt-1">
              Quản lý nhiệm vụ theo trạng thái
            </p>
          </div>
          <div className="flex gap-3">
            <Button
              variant="outline"
              as={Link}
              to="/tasks"
            >
              <List className="w-4 h-4 mr-2" />
              Danh sách
            </Button>
            <Button as={Link} to="/tasks/create">
              <Plus className="w-4 h-4 mr-2" />
              Tạo nhiệm vụ
            </Button>
          </div>
        </div>

        {/* Filters */}
        <Card>
          <CardContent className="p-4">
            <div className="flex gap-4">
              <div className="w-64">
                <Select
                  placeholder="Chọn dự án"
                  value={selectedProject}
                  onChange={setSelectedProject}
                  options={[
                    { value: '', label: 'Tất cả dự án' },
                    ...projects.map(p => ({ value: p.id, label: p.name }))
                  ]}
                />
              </div>
              <div className="w-64">
                <Select
                  placeholder="Người thực hiện"
                  value={selectedAssignee}
                  onChange={setSelectedAssignee}
                  options={[
                    { value: '', label: 'Tất cả người dùng' }
                    // TODO: Add users from store
                  ]}
                />
              </div>
            </div>
          </CardContent>
        </Card>

        {/* No Project Selected Message */}
        <Card>
          <CardContent className="p-8 text-center">
            <div className="space-y-4">
              <div className="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center">
                <Filter className="w-8 h-8 text-gray-400" />
              </div>
              <div>
                <h3 className="text-lg font-medium text-gray-900 mb-2">
                  Chọn dự án để xem bảng Kanban
                </h3>
                <p className="text-gray-600">
                  Vui lòng chọn một dự án từ danh sách trên để xem và quản lý các nhiệm vụ.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Bảng Kanban</h1>
          <p className="text-gray-600 mt-1">
            Quản lý nhiệm vụ theo trạng thái
          </p>
        </div>
        <div className="flex gap-3">
          <Button
            variant="outline"
            as={Link}
            to="/tasks"
          >
            <List className="w-4 h-4 mr-2" />
            Danh sách
          </Button>
          <Button as={Link} to="/tasks/create">
            <Plus className="w-4 h-4 mr-2" />
            Tạo nhiệm vụ
          </Button>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-4">
          <div className="flex gap-4">
            <div className="w-64">
              <Select
                placeholder="Chọn dự án"
                value={selectedProject}
                onChange={setSelectedProject}
                options={[
                  { value: '', label: 'Tất cả dự án' },
                  ...projects.map(p => ({ value: p.id, label: p.name }))
                ]}
              />
            </div>
            <div className="w-64">
              <Select
                placeholder="Người thực hiện"
                value={selectedAssignee}
                onChange={setSelectedAssignee}
                options={[
                  { value: '', label: 'Tất cả người dùng' }
                  // TODO: Add users from store
                ]}
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Kanban Board */}
      <DragDropContext onDragEnd={handleDragEnd}>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {columns.map((column) => (
            <div key={column.id} className="flex flex-col">
              {/* Column Header */}
              <div className={`${column.headerColor} rounded-t-lg p-4`}>
                <div className="flex items-center justify-between">
                  <h3 className="font-semibold text-gray-900">{column.title}</h3>
                  <span className="bg-white px-2 py-1 rounded-full text-sm font-medium">
                    {tasksByStatus[column.id]?.length || 0}
                  </span>
                </div>
              </div>

              {/* Column Content */}
              <Droppable droppableId={column.id}>
                {(provided, snapshot) => (
                  <div
                    ref={provided.innerRef}
                    {...provided.droppableProps}
                    className={`${column.color} rounded-b-lg p-4 min-h-[500px] flex-1 ${
                      snapshot.isDraggingOver ? 'bg-opacity-50' : ''
                    }`}
                  >
                    {tasksByStatus[column.id]?.map((task, index) => (
                      <TaskCard key={task.id} task={task} index={index} />
                    ))}
                    {provided.placeholder}
                    
                    {/* Empty State */}
                    {(!tasksByStatus[column.id] || tasksByStatus[column.id].length === 0) && (
                      <div className="flex items-center justify-center h-32 text-gray-400">
                        <div className="text-center">
                          <Clock className="w-8 h-8 mx-auto mb-2" />
                          <p className="text-sm">Không có nhiệm vụ</p>
                        </div>
                      </div>
                    )}
                  </div>
                )}
              </Droppable>
            </div>
          ))}
        </div>
      </DragDropContext>
    </div>
  );
};