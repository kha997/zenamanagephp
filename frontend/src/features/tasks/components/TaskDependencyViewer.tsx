import React, { useState, useEffect, useCallback } from 'react';
import ReactFlow, {
  Node,
  Edge,
  addEdge,
  Connection,
  useNodesState,
  useEdgesState,
  Controls,
  Background,
  MiniMap,
  ReactFlowProvider,
  MarkerType,
} from 'reactflow';
import 'reactflow/dist/style.css';
import { Task } from '@/lib/types';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertTriangle, Route, Eye, EyeOff } from 'lucide-react';

interface TaskDependencyViewerProps {
  tasks: Task[];
  onUpdateDependencies: (taskId: string, dependencies: string[]) => void;
}

interface TaskNode extends Node {
  data: {
    task: Task;
    isOnCriticalPath: boolean;
  };
}

interface DependencyEdge extends Edge {
  data: {
    isCriticalPath: boolean;
  };
}

/**
 * Component hiển thị biểu đồ phụ thuộc giữa các task
 * Hỗ trợ drag-drop để tạo dependencies, highlight critical path và phát hiện circular dependencies
 */
export const TaskDependencyViewer: React.FC<TaskDependencyViewerProps> = ({
  tasks,
  onUpdateDependencies,
}) => {
  const [nodes, setNodes, onNodesChange] = useNodesState<TaskNode>([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState<DependencyEdge>([]);
  const [criticalPath, setCriticalPath] = useState<string[]>([]);
  const [circularDependencies, setCircularDependencies] = useState<string[]>([]);
  const [showCriticalPath, setShowCriticalPath] = useState(true);
  const [isAnalyzing, setIsAnalyzing] = useState(false);

  /**
   * Tính toán vị trí node dựa trên thuật toán layered layout
   */
  const calculateNodePositions = useCallback((tasks: Task[]) => {
    const taskMap = new Map(tasks.map(task => [task.id, task]));
    const layers: string[][] = [];
    const visited = new Set<string>();
    const visiting = new Set<string>();

    // Topological sort để xác định layers
    const visit = (taskId: string, layer: number = 0): void => {
      if (visiting.has(taskId)) {
        // Phát hiện circular dependency
        setCircularDependencies(prev => [...new Set([...prev, taskId])]);
        return;
      }
      if (visited.has(taskId)) return;

      visiting.add(taskId);
      const task = taskMap.get(taskId);
      if (!task) return;

      // Đảm bảo layer tồn tại
      while (layers.length <= layer) {
        layers.push([]);
      }

      // Xử lý dependencies trước
      let maxDepLayer = layer;
      task.dependencies.forEach(depId => {
        if (taskMap.has(depId)) {
          visit(depId, layer + 1);
          maxDepLayer = Math.max(maxDepLayer, layer + 1);
        }
      });

      // Đặt task vào layer phù hợp
      const targetLayer = Math.max(0, maxDepLayer - task.dependencies.length);
      while (layers.length <= targetLayer) {
        layers.push([]);
      }
      layers[targetLayer].push(taskId);

      visited.add(taskId);
      visiting.delete(taskId);
    };

    // Bắt đầu từ các task không có dependencies
    tasks.forEach(task => {
      if (task.dependencies.length === 0) {
        visit(task.id, 0);
      }
    });

    // Xử lý các task còn lại
    tasks.forEach(task => {
      if (!visited.has(task.id)) {
        visit(task.id, 0);
      }
    });

    // Tính toán vị trí thực tế
    const nodePositions: { [key: string]: { x: number; y: number } } = {};
    const layerWidth = 300;
    const nodeHeight = 100;

    layers.forEach((layer, layerIndex) => {
      layer.forEach((taskId, nodeIndex) => {
        nodePositions[taskId] = {
          x: layerIndex * layerWidth,
          y: nodeIndex * nodeHeight + (nodeIndex * 20), // Thêm spacing
        };
      });
    });

    return nodePositions;
  }, []);

  /**
   * Tính toán critical path sử dụng thuật toán CPM (Critical Path Method)
   */
  const calculateCriticalPath = useCallback((tasks: Task[]) => {
    const taskMap = new Map(tasks.map(task => [task.id, task]));
    const earlyStart: { [key: string]: number } = {};
    const earlyFinish: { [key: string]: number } = {};
    const lateStart: { [key: string]: number } = {};
    const lateFinish: { [key: string]: number } = {};
    const duration: { [key: string]: number } = {};

    // Tính duration cho mỗi task (số ngày)
    tasks.forEach(task => {
      const start = new Date(task.start_date);
      const end = new Date(task.end_date);
      duration[task.id] = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24));
    });

    // Forward pass - tính Early Start và Early Finish
    const calculateEarly = (taskId: string): void => {
      if (earlyStart[taskId] !== undefined) return;

      const task = taskMap.get(taskId);
      if (!task) return;

      let maxEarlyFinish = 0;
      task.dependencies.forEach(depId => {
        calculateEarly(depId);
        maxEarlyFinish = Math.max(maxEarlyFinish, earlyFinish[depId] || 0);
      });

      earlyStart[taskId] = maxEarlyFinish;
      earlyFinish[taskId] = earlyStart[taskId] + duration[taskId];
    };

    tasks.forEach(task => calculateEarly(task.id));

    // Tìm project finish time
    const projectFinish = Math.max(...Object.values(earlyFinish));

    // Backward pass - tính Late Start và Late Finish
    const calculateLate = (taskId: string): void => {
      if (lateFinish[taskId] !== undefined) return;

      const task = taskMap.get(taskId);
      if (!task) return;

      // Tìm các task phụ thuộc vào task này
      const dependents = tasks.filter(t => t.dependencies.includes(taskId));
      
      if (dependents.length === 0) {
        // Task cuối cùng
        lateFinish[taskId] = projectFinish;
      } else {
        let minLateStart = Infinity;
        dependents.forEach(dep => {
          calculateLate(dep.id);
          minLateStart = Math.min(minLateStart, lateStart[dep.id] || Infinity);
        });
        lateFinish[taskId] = minLateStart;
      }

      lateStart[taskId] = lateFinish[taskId] - duration[taskId];
    };

    tasks.forEach(task => calculateLate(task.id));

    // Tìm critical path (các task có slack = 0)
    const criticalTasks = tasks
      .filter(task => {
        const slack = lateStart[task.id] - earlyStart[task.id];
        return Math.abs(slack) < 0.001; // Xử lý floating point precision
      })
      .map(task => task.id);

    return criticalTasks;
  }, []);

  /**
   * Phát hiện circular dependencies sử dụng DFS
   */
  const detectCircularDependencies = useCallback((tasks: Task[]) => {
    const taskMap = new Map(tasks.map(task => [task.id, task]));
    const visited = new Set<string>();
    const recursionStack = new Set<string>();
    const circularTasks = new Set<string>();

    const dfs = (taskId: string, path: string[] = []): boolean => {
      if (recursionStack.has(taskId)) {
        // Tìm thấy cycle, đánh dấu tất cả task trong cycle
        const cycleStart = path.indexOf(taskId);
        path.slice(cycleStart).forEach(id => circularTasks.add(id));
        circularTasks.add(taskId);
        return true;
      }

      if (visited.has(taskId)) return false;

      visited.add(taskId);
      recursionStack.add(taskId);
      
      const task = taskMap.get(taskId);
      if (task) {
        for (const depId of task.dependencies) {
          if (dfs(depId, [...path, taskId])) {
            circularTasks.add(taskId);
          }
        }
      }

      recursionStack.delete(taskId);
      return false;
    };

    tasks.forEach(task => {
      if (!visited.has(task.id)) {
        dfs(task.id);
      }
    });

    return Array.from(circularTasks);
  }, []);

  /**
   * Tạo nodes và edges từ danh sách tasks
   */
  useEffect(() => {
    if (tasks.length === 0) return;

    setIsAnalyzing(true);

    // Tính toán critical path và circular dependencies
    const criticalTasks = calculateCriticalPath(tasks);
    const circularTasks = detectCircularDependencies(tasks);
    
    setCriticalPath(criticalTasks);
    setCircularDependencies(circularTasks);

    // Tính toán vị trí nodes
    const positions = calculateNodePositions(tasks);

    // Tạo nodes
    const newNodes: TaskNode[] = tasks.map(task => ({
      id: task.id,
      type: 'default',
      position: positions[task.id] || { x: 0, y: 0 },
      data: {
        task,
        isOnCriticalPath: criticalTasks.includes(task.id),
      },
      style: {
        background: circularTasks.includes(task.id)
          ? '#fee2e2' // Red background for circular dependencies
          : criticalTasks.includes(task.id) && showCriticalPath
          ? '#fef3c7' // Yellow background for critical path
          : '#f3f4f6', // Default gray
        border: circularTasks.includes(task.id)
          ? '2px solid #dc2626'
          : criticalTasks.includes(task.id) && showCriticalPath
          ? '2px solid #d97706'
          : '1px solid #d1d5db',
        borderRadius: '8px',
        padding: '10px',
        width: 200,
        fontSize: '12px',
      },
    }));

    // Tạo edges
    const newEdges: DependencyEdge[] = [];
    tasks.forEach(task => {
      task.dependencies.forEach(depId => {
        const isCritical = criticalTasks.includes(task.id) && criticalTasks.includes(depId);
        newEdges.push({
          id: `${depId}-${task.id}`,
          source: depId,
          target: task.id,
          type: 'smoothstep',
          animated: isCritical && showCriticalPath,
          style: {
            stroke: isCritical && showCriticalPath ? '#d97706' : '#6b7280',
            strokeWidth: isCritical && showCriticalPath ? 3 : 1,
          },
          markerEnd: {
            type: MarkerType.ArrowClosed,
            color: isCritical && showCriticalPath ? '#d97706' : '#6b7280',
          },
          data: {
            isCriticalPath: isCritical,
          },
        });
      });
    });

    setNodes(newNodes);
    setEdges(newEdges);
    setIsAnalyzing(false);
  }, [tasks, showCriticalPath, calculateCriticalPath, detectCircularDependencies, calculateNodePositions, setNodes, setEdges]);

  /**
   * Xử lý khi tạo connection mới (dependency)
   */
  const onConnect = useCallback(
    (params: Connection) => {
      if (!params.source || !params.target) return;
      
      // Kiểm tra xem có tạo ra circular dependency không
      const sourceTask = tasks.find(t => t.id === params.source);
      if (!sourceTask) return;

      const newDependencies = [...sourceTask.dependencies, params.target];
      
      // Tạm thời cập nhật để kiểm tra circular dependency
      const tempTasks = tasks.map(task => 
        task.id === params.source 
          ? { ...task, dependencies: newDependencies }
          : task
      );
      
      const circularTasks = detectCircularDependencies(tempTasks);
      
      if (circularTasks.includes(params.source) || circularTasks.includes(params.target)) {
        alert('Không thể tạo dependency này vì sẽ tạo ra phụ thuộc vòng tròn!');
        return;
      }

      // Cập nhật dependency
      onUpdateDependencies(params.source, newDependencies);
      
      // Tạo edge mới
      const newEdge: DependencyEdge = {
        id: `${params.target}-${params.source}`,
        source: params.target,
        target: params.source,
        type: 'smoothstep',
        style: { stroke: '#6b7280', strokeWidth: 1 },
        markerEnd: { type: MarkerType.ArrowClosed, color: '#6b7280' },
        data: { isCriticalPath: false },
      };
      
      setEdges((eds) => addEdge(newEdge, eds));
    },
    [tasks, onUpdateDependencies, detectCircularDependencies, setEdges]
  );

  /**
   * Custom node component
   */
  const TaskNode = ({ data }: { data: TaskNode['data'] }) => {
    const { task, isOnCriticalPath } = data;
    
    return (
      <div className="task-node">
        <div className="font-semibold text-sm mb-1 truncate">{task.name}</div>
        <div className="text-xs text-gray-600 mb-2">
          {new Date(task.start_date).toLocaleDateString('vi-VN')} - 
          {new Date(task.end_date).toLocaleDateString('vi-VN')}
        </div>
        <div className="flex justify-between items-center">
          <Badge 
            size="sm" 
            className={`text-xs ${
              task.status === 'completed' ? 'bg-green-100 text-green-800' :
              task.status === 'in_progress' ? 'bg-blue-100 text-blue-800' :
              task.status === 'review' ? 'bg-yellow-100 text-yellow-800' :
              'bg-gray-100 text-gray-800'
            }`}
          >
            {task.status}
          </Badge>
          {isOnCriticalPath && showCriticalPath && (
            <Badge size="sm" className="bg-orange-100 text-orange-800 text-xs">
              Critical
            </Badge>
          )}
        </div>
      </div>
    );
  };

  const nodeTypes = {
    default: TaskNode,
  };

  return (
    <div className="h-full flex flex-col">
      {/* Header Controls */}
      <div className="flex justify-between items-center p-4 border-b">
        <div>
          <h2 className="text-lg font-semibold">Biểu đồ phụ thuộc công việc</h2>
          <p className="text-sm text-gray-600">
            Kéo thả giữa các task để tạo phụ thuộc. Critical path được highlight màu vàng.
          </p>
        </div>
        <div className="flex space-x-2">
          <Button
            variant={showCriticalPath ? 'default' : 'outline'}
            size="sm"
            onClick={() => setShowCriticalPath(!showCriticalPath)}
          >
            {showCriticalPath ? <Eye className="w-4 h-4 mr-1" /> : <EyeOff className="w-4 h-4 mr-1" />}
            Critical Path
          </Button>
          <Button variant="outline" size="sm">
            <Route className="w-4 h-4 mr-1" />
            Auto Layout
          </Button>
        </div>
      </div>

      {/* Alerts */}
      {circularDependencies.length > 0 && (
        <Alert className="m-4 border-red-200 bg-red-50">
          <AlertTriangle className="h-4 w-4 text-red-600" />
          <AlertDescription className="text-red-800">
            <strong>Phát hiện phụ thuộc vòng tròn!</strong> Các task sau có phụ thuộc vòng tròn: {' '}
            {circularDependencies.map(taskId => {
              const task = tasks.find(t => t.id === taskId);
              return task?.name || taskId;
            }).join(', ')}
          </AlertDescription>
        </Alert>
      )}

      {/* React Flow */}
      <div className="flex-1">
        <ReactFlowProvider>
          <ReactFlow
            nodes={nodes}
            edges={edges}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
            onConnect={onConnect}
            nodeTypes={nodeTypes}
            connectionMode="loose"
            fitView
            attributionPosition="bottom-left"
          >
            <Background />
            <Controls />
            <MiniMap 
              nodeColor={(node) => {
                const taskNode = node as TaskNode;
                return taskNode.data.isOnCriticalPath && showCriticalPath ? '#d97706' : '#6b7280';
              }}
              nodeStrokeWidth={3}
              zoomable
              pannable
            />
          </ReactFlow>
        </ReactFlowProvider>
      </div>

      {/* Stats */}
      <div className="p-4 border-t bg-gray-50">
        <div className="grid grid-cols-4 gap-4 text-center">
          <div>
            <div className="text-lg font-semibold">{tasks.length}</div>
            <div className="text-xs text-gray-600">Tổng công việc</div>
          </div>
          <div>
            <div className="text-lg font-semibold text-orange-600">{criticalPath.length}</div>
            <div className="text-xs text-gray-600">Critical Path</div>
          </div>
          <div>
            <div className="text-lg font-semibold text-red-600">{circularDependencies.length}</div>
            <div className="text-xs text-gray-600">Phụ thuộc vòng</div>
          </div>
          <div>
            <div className="text-lg font-semibold text-blue-600">
              {tasks.reduce((sum, task) => sum + task.dependencies.length, 0)}
            </div>
            <div className="text-xs text-gray-600">Tổng phụ thuộc</div>
          </div>
        </div>
      </div>
    </div>
  );
};