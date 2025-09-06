import React, { useState, useMemo } from 'react';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Progress } from '@/components/ui/Progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/Tabs';
import { Alert, AlertDescription } from '@/components/ui/Alert';
import { 
  DollarSign, 
  TrendingUp, 
  TrendingDown,
  AlertTriangle,
  BarChart3,
  PieChart,
  Target,
  Calculator,
  Calendar,
  ArrowUpRight,
  ArrowDownRight
} from 'lucide-react';
import { Component, ComponentCostSummary } from '../types/component';
import { formatCurrency } from '@/lib/utils';
import { cn } from '@/lib/utils';

interface ComponentCostTrackerProps {
  component: Component;
  costSummary?: ComponentCostSummary;
  className?: string;
  showDetails?: boolean;
  onUpdateCost?: (componentId: string, actualCost: number) => void;
}

interface CostBreakdown {
  category: string;
  planned: number;
  actual: number;
  variance: number;
  percentage: number;
}

export const ComponentCostTracker: React.FC<ComponentCostTrackerProps> = ({
  component,
  costSummary,
  className,
  showDetails = true,
  onUpdateCost
}) => {
  const [activeTab, setActiveTab] = useState('overview');
  const [isEditing, setIsEditing] = useState(false);
  const [editCost, setEditCost] = useState(component.actual_cost.toString());

  // Tính toán các chỉ số chi phí
  const costMetrics = useMemo(() => {
    const planned = component.planned_cost;
    const actual = component.actual_cost;
    const variance = actual - planned;
    const variancePercent = planned > 0 ? (variance / planned) * 100 : 0;
    const isOverBudget = actual > planned;
    const utilizationRate = planned > 0 ? (actual / planned) * 100 : 0;
    
    return {
      planned,
      actual,
      variance,
      variancePercent,
      isOverBudget,
      utilizationRate,
      remaining: Math.max(0, planned - actual)
    };
  }, [component.planned_cost, component.actual_cost]);

  // Mock data cho cost breakdown (trong thực tế sẽ lấy từ API)
  const costBreakdown: CostBreakdown[] = useMemo(() => [
    {
      category: 'Vật liệu',
      planned: component.planned_cost * 0.6,
      actual: component.actual_cost * 0.65,
      variance: (component.actual_cost * 0.65) - (component.planned_cost * 0.6),
      percentage: 65
    },
    {
      category: 'Nhân công',
      planned: component.planned_cost * 0.3,
      actual: component.actual_cost * 0.25,
      variance: (component.actual_cost * 0.25) - (component.planned_cost * 0.3),
      percentage: 25
    },
    {
      category: 'Thiết bị',
      planned: component.planned_cost * 0.1,
      actual: component.actual_cost * 0.1,
      variance: (component.actual_cost * 0.1) - (component.planned_cost * 0.1),
      percentage: 10
    }
  ], [component.planned_cost, component.actual_cost]);

  const handleSaveCost = () => {
    const newCost = parseFloat(editCost);
    if (!isNaN(newCost) && newCost >= 0 && onUpdateCost) {
      onUpdateCost(component.id, newCost);
      setIsEditing(false);
    }
  };

  const getCostStatusColor = (variance: number) => {
    if (variance > 0) return 'text-red-600';
    if (variance < 0) return 'text-green-600';
    return 'text-gray-600';
  };

  const getCostStatusIcon = (variance: number) => {
    if (variance > 0) return <ArrowUpRight className="h-4 w-4" />;
    if (variance < 0) return <ArrowDownRight className="h-4 w-4" />;
    return null;
  };

  return (
    <div className={cn('space-y-6', className)}>
      {/* Cost Overview Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Planned Cost */}
        <Card className="p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Kế hoạch</p>
              <p className="text-2xl font-bold text-blue-600">
                {formatCurrency(costMetrics.planned)}
              </p>
            </div>
            <div className="p-2 bg-blue-100 rounded-lg">
              <Target className="h-6 w-6 text-blue-600" />
            </div>
          </div>
        </Card>

        {/* Actual Cost */}
        <Card className="p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Thực tế</p>
              <div className="flex items-center space-x-2">
                {isEditing ? (
                  <div className="flex items-center space-x-2">
                    <input
                      type="number"
                      value={editCost}
                      onChange={(e) => setEditCost(e.target.value)}
                      className="text-xl font-bold border rounded px-2 py-1 w-32"
                      min="0"
                      step="0.01"
                    />
                    <Button size="sm" onClick={handleSaveCost}>
                      Lưu
                    </Button>
                    <Button 
                      size="sm" 
                      variant="outline" 
                      onClick={() => {
                        setIsEditing(false);
                        setEditCost(component.actual_cost.toString());
                      }}
                    >
                      Hủy
                    </Button>
                  </div>
                ) : (
                  <>
                    <p className="text-2xl font-bold text-green-600">
                      {formatCurrency(costMetrics.actual)}
                    </p>
                    {onUpdateCost && (
                      <Button 
                        size="sm" 
                        variant="ghost" 
                        onClick={() => setIsEditing(true)}
                      >
                        Sửa
                      </Button>
                    )}
                  </>
                )}
              </div>
            </div>
            <div className="p-2 bg-green-100 rounded-lg">
              <DollarSign className="h-6 w-6 text-green-600" />
            </div>
          </div>
        </Card>

        {/* Variance */}
        <Card className="p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Chênh lệch</p>
              <div className="flex items-center space-x-1">
                <p className={cn(
                  'text-2xl font-bold',
                  getCostStatusColor(costMetrics.variance)
                )}>
                  {formatCurrency(Math.abs(costMetrics.variance))}
                </p>
                {getCostStatusIcon(costMetrics.variance)}
              </div>
              <p className={cn(
                'text-sm',
                getCostStatusColor(costMetrics.variance)
              )}>
                {costMetrics.variancePercent > 0 ? '+' : ''}
                {costMetrics.variancePercent.toFixed(1)}%
              </p>
            </div>
            <div className={cn(
              'p-2 rounded-lg',
              costMetrics.isOverBudget ? 'bg-red-100' : 'bg-green-100'
            )}>
              {costMetrics.isOverBudget ? (
                <TrendingUp className="h-6 w-6 text-red-600" />
              ) : (
                <TrendingDown className="h-6 w-6 text-green-600" />
              )}
            </div>
          </div>
        </Card>

        {/* Utilization Rate */}
        <Card className="p-4">
          <div className="flex items-center justify-between">
            <div className="flex-1">
              <p className="text-sm font-medium text-gray-600">Tỷ lệ sử dụng</p>
              <p className="text-2xl font-bold text-purple-600">
                {costMetrics.utilizationRate.toFixed(1)}%
              </p>
              <Progress 
                value={Math.min(costMetrics.utilizationRate, 100)} 
                className="mt-2"
                color={costMetrics.utilizationRate > 100 ? 'danger' : 'primary'}
              />
            </div>
            <div className="p-2 bg-purple-100 rounded-lg">
              <Calculator className="h-6 w-6 text-purple-600" />
            </div>
          </div>
        </Card>
      </div>

      {/* Budget Alert */}
      {costMetrics.isOverBudget && (
        <Alert variant="destructive">
          <AlertTriangle className="h-4 w-4" />
          <AlertDescription>
            Component này đã vượt ngân sách {formatCurrency(costMetrics.variance)} 
            ({costMetrics.variancePercent.toFixed(1)}%). Cần xem xét điều chỉnh hoặc kiểm soát chi phí.
          </AlertDescription>
        </Alert>
      )}

      {/* Detailed Analysis */}
      {showDetails && (
        <Card className="p-6">
          <Tabs value={activeTab} onValueChange={setActiveTab}>
            <TabsList className="grid w-full grid-cols-3">
              <TabsTrigger value="overview">Tổng quan</TabsTrigger>
              <TabsTrigger value="breakdown">Phân tích</TabsTrigger>
              <TabsTrigger value="trends">Xu hướng</TabsTrigger>
            </TabsList>

            <TabsContent value="overview" className="mt-6">
              <div className="space-y-4">
                <h3 className="text-lg font-semibold flex items-center space-x-2">
                  <BarChart3 className="h-5 w-5" />
                  <span>Tổng quan chi phí</span>
                </h3>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {/* Cost Progress */}
                  <div className="space-y-3">
                    <h4 className="font-medium">Tiến độ chi phí</h4>
                    <div className="space-y-2">
                      <div className="flex justify-between text-sm">
                        <span>Đã sử dụng</span>
                        <span>{costMetrics.utilizationRate.toFixed(1)}%</span>
                      </div>
                      <Progress 
                        value={Math.min(costMetrics.utilizationRate, 100)}
                        className="h-3"
                        color={costMetrics.utilizationRate > 100 ? 'danger' : 'primary'}
                      />
                      <div className="flex justify-between text-xs text-gray-500">
                        <span>{formatCurrency(costMetrics.actual)}</span>
                        <span>{formatCurrency(costMetrics.planned)}</span>
                      </div>
                    </div>
                  </div>

                  {/* Remaining Budget */}
                  <div className="space-y-3">
                    <h4 className="font-medium">Ngân sách còn lại</h4>
                    <div className="text-center p-4 bg-gray-50 rounded-lg">
                      <p className="text-2xl font-bold text-blue-600">
                        {formatCurrency(costMetrics.remaining)}
                      </p>
                      <p className="text-sm text-gray-600 mt-1">
                        {costMetrics.remaining > 0 ? 'Còn lại' : 'Đã vượt ngân sách'}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </TabsContent>

            <TabsContent value="breakdown" className="mt-6">
              <div className="space-y-4">
                <h3 className="text-lg font-semibold flex items-center space-x-2">
                  <PieChart className="h-5 w-5" />
                  <span>Phân tích chi phí theo danh mục</span>
                </h3>
                
                <div className="space-y-3">
                  {costBreakdown.map((item, index) => (
                    <div key={index} className="border rounded-lg p-4">
                      <div className="flex items-center justify-between mb-2">
                        <h4 className="font-medium">{item.category}</h4>
                        <Badge variant={item.variance > 0 ? 'destructive' : 'success'}>
                          {item.variance > 0 ? '+' : ''}{formatCurrency(item.variance)}
                        </Badge>
                      </div>
                      
                      <div className="grid grid-cols-3 gap-4 text-sm">
                        <div>
                          <p className="text-gray-600">Kế hoạch</p>
                          <p className="font-semibold">{formatCurrency(item.planned)}</p>
                        </div>
                        <div>
                          <p className="text-gray-600">Thực tế</p>
                          <p className="font-semibold">{formatCurrency(item.actual)}</p>
                        </div>
                        <div>
                          <p className="text-gray-600">Tỷ lệ</p>
                          <p className="font-semibold">{item.percentage}%</p>
                        </div>
                      </div>
                      
                      <Progress 
                        value={item.percentage} 
                        className="mt-3 h-2"
                        color={item.variance > 0 ? 'danger' : 'success'}
                      />
                    </div>
                  ))}
                </div>
              </div>
            </TabsContent>

            <TabsContent value="trends" className="mt-6">
              <div className="space-y-4">
                <h3 className="text-lg font-semibold flex items-center space-x-2">
                  <Calendar className="h-5 w-5" />
                  <span>Xu hướng chi phí theo thời gian</span>
                </h3>
                
                <div className="text-center py-8 text-gray-500">
                  <BarChart3 className="h-12 w-12 mx-auto mb-4 opacity-50" />
                  <p>Biểu đồ xu hướng chi phí sẽ được hiển thị ở đây</p>
                  <p className="text-sm mt-2">Tích hợp với thư viện biểu đồ như Chart.js hoặc Recharts</p>
                </div>
              </div>
            </TabsContent>
          </Tabs>
        </Card>
      )}
    </div>
  );
};