import React, { useState, useEffect } from 'react';
import { Grid, GridItem, Box, Flex, Text, Button, IconButton, Spinner } from '@chakra-ui/react';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';
import { AddIcon, SettingsIcon, RefreshIcon } from '@chakra-ui/icons';
import DashboardWidget from './DashboardWidget';
import WidgetSelector from './WidgetSelector';
import { useDashboard } from '../../hooks/useDashboard';
import { useAuth } from '../../hooks/useAuth';

interface DashboardLayoutProps {
  role: string;
  projectId?: string;
}

interface Widget {
  id: string;
  name: string;
  type: string;
  category: string;
  position: {
    x: number;
    y: number;
    w: number;
    h: number;
  };
  config: Record<string, any>;
}

interface LayoutConfig {
  columns: number;
  rows: number;
  gap: number;
}

const DashboardLayout: React.FC<DashboardLayoutProps> = ({ role, projectId }) => {
  const { user } = useAuth();
  const {
    dashboard,
    widgets,
    loading,
    error,
    fetchDashboard,
    updateLayout,
    addWidget,
    removeWidget,
    updateWidgetConfig,
    refreshWidgetData
  } = useDashboard();

  const [isWidgetSelectorOpen, setIsWidgetSelectorOpen] = useState(false);
  const [isDragging, setIsDragging] = useState(false);

  useEffect(() => {
    if (user?.id) {
      fetchDashboard(user.id, projectId);
    }
  }, [user?.id, projectId]);

  const handleDragStart = () => {
    setIsDragging(true);
  };

  const handleDragEnd = (result: any) => {
    setIsDragging(false);
    
    if (!result.destination) return;

    const { source, destination } = result;
    
    if (source.index === destination.index) return;

    // Update widget positions
    const newWidgets = Array.from(dashboard?.widgets || []);
    const [reorderedWidget] = newWidgets.splice(source.index, 1);
    newWidgets.splice(destination.index, 0, reorderedWidget);

    // Update positions based on new order
    const updatedWidgets = newWidgets.map((widget: Widget, index: number) => ({
      ...widget,
      position: {
        x: index % (dashboard?.layout_config?.columns || 4),
        y: Math.floor(index / (dashboard?.layout_config?.columns || 4)),
        w: widget.position.w,
        h: widget.position.h
      }
    }));

    updateLayout(dashboard?.layout_config || {}, updatedWidgets);
  };

  const handleAddWidget = (widgetId: string) => {
    const newPosition = {
      x: (dashboard?.widgets?.length || 0) % (dashboard?.layout_config?.columns || 4),
      y: Math.floor((dashboard?.widgets?.length || 0) / (dashboard?.layout_config?.columns || 4)),
      w: 1,
      h: 1
    };

    addWidget(widgetId, newPosition, {});
    setIsWidgetSelectorOpen(false);
  };

  const handleRemoveWidget = (widgetId: string) => {
    removeWidget(widgetId);
  };

  const handleRefreshAll = () => {
    dashboard?.widgets?.forEach((widget: Widget) => {
      refreshWidgetData(widget.id);
    });
  };

  if (loading) {
    return (
      <Flex justify="center" align="center" h="400px">
        <Spinner size="xl" color="blue.500" />
      </Flex>
    );
  }

  if (error) {
    return (
      <Box p={4} bg="red.50" borderRadius="md" border="1px" borderColor="red.200">
        <Text color="red.600">Error loading dashboard: {error}</Text>
      </Box>
    );
  }

  const layoutConfig: LayoutConfig = dashboard?.layout_config || {
    columns: 4,
    rows: 3,
    gap: 16
  };

  return (
    <Box p={6}>
      {/* Dashboard Header */}
      <Flex justify="space-between" align="center" mb={6}>
        <Box>
          <Text fontSize="2xl" fontWeight="bold" color="gray.800">
            {role === 'system_admin' && 'System Dashboard'}
            {role === 'project_manager' && 'Project Dashboard'}
            {role === 'design_lead' && 'Design Dashboard'}
            {role === 'site_engineer' && 'Site Dashboard'}
            {role === 'qc_inspector' && 'QC Dashboard'}
            {role === 'client_rep' && 'Client Dashboard'}
            {role === 'subcontractor_lead' && 'Work Dashboard'}
          </Text>
          <Text color="gray.600" fontSize="sm">
            Welcome back, {user?.name}
          </Text>
        </Box>
        
        <Flex gap={2}>
          <Button
            leftIcon={<AddIcon />}
            colorScheme="blue"
            variant="outline"
            onClick={() => setIsWidgetSelectorOpen(true)}
          >
            Add Widget
          </Button>
          
          <IconButton
            aria-label="Refresh"
            icon={<RefreshIcon />}
            variant="outline"
            onClick={handleRefreshAll}
          />
          
          <IconButton
            aria-label="Settings"
            icon={<SettingsIcon />}
            variant="outline"
          />
        </Flex>
      </Flex>

      {/* Dashboard Grid */}
      <DragDropContext onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
        <Droppable droppableId="dashboard-grid">
          {(provided, snapshot) => (
            <Grid
              ref={provided.innerRef}
              {...provided.droppableProps}
              templateColumns={`repeat(${layoutConfig.columns}, 1fr)`}
              gap={layoutConfig.gap}
              minH="600px"
              bg={snapshot.isDraggingOver ? "gray.50" : "transparent"}
              borderRadius="md"
              p={4}
            >
              {dashboard?.widgets?.map((widget: Widget, index: number) => (
                <Draggable key={widget.id} draggableId={widget.id} index={index}>
                  {(provided, snapshot) => (
                    <GridItem
                      ref={provided.innerRef}
                      {...provided.draggableProps}
                      {...provided.dragHandleProps}
                      colSpan={widget.position.w}
                      rowSpan={widget.position.h}
                      opacity={snapshot.isDragging ? 0.8 : 1}
                      transform={snapshot.isDragging ? 'rotate(5deg)' : 'none'}
                      transition="all 0.2s"
                    >
                      <DashboardWidget
                        widget={widget}
                        onRemove={() => handleRemoveWidget(widget.id)}
                        onConfigUpdate={(config) => updateWidgetConfig(widget.id, config)}
                        isDragging={snapshot.isDragging}
                      />
                    </GridItem>
                  )}
                </Draggable>
              ))}
              
              {provided.placeholder}
              
              {/* Empty state */}
              {(!dashboard?.widgets || dashboard.widgets.length === 0) && (
                <GridItem colSpan={layoutConfig.columns}>
                  <Box
                    textAlign="center"
                    py={20}
                    bg="gray.50"
                    borderRadius="md"
                    border="2px dashed"
                    borderColor="gray.300"
                  >
                    <Text fontSize="lg" color="gray.500" mb={4}>
                      No widgets added yet
                    </Text>
                    <Button
                      leftIcon={<AddIcon />}
                      colorScheme="blue"
                      onClick={() => setIsWidgetSelectorOpen(true)}
                    >
                      Add Your First Widget
                    </Button>
                  </Box>
                </GridItem>
              )}
            </Grid>
          )}
        </Droppable>
      </DragDropContext>

      {/* Widget Selector Modal */}
      <WidgetSelector
        isOpen={isWidgetSelectorOpen}
        onClose={() => setIsWidgetSelectorOpen(false)}
        onSelectWidget={handleAddWidget}
        availableWidgets={widgets}
        role={role}
      />
    </Box>
  );
};

export default DashboardLayout;
