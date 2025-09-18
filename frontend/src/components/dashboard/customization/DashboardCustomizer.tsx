import React, { useState, useEffect, useCallback } from 'react';
import {
  Box,
  VStack,
  HStack,
  Button,
  Text,
  Badge,
  IconButton,
  useDisclosure,
  useToast,
  Spinner,
  Alert,
  AlertIcon,
  AlertTitle,
  AlertDescription,
  Divider,
  Flex,
  Tooltip,
  Menu,
  MenuButton,
  MenuList,
  MenuItem,
  Modal,
  ModalOverlay,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  ModalCloseButton,
  FormControl,
  FormLabel,
  Input,
  Select,
  Switch,
  NumberInput,
  NumberInputField,
  NumberInputStepper,
  NumberIncrementStepper,
  NumberDecrementStepper,
  Tabs,
  TabList,
  TabPanels,
  Tab,
  TabPanel,
  Grid,
  GridItem,
  Card,
  CardBody,
  CardHeader,
  Heading,
  Image,
  useColorModeValue
} from '@chakra-ui/react';
import {
  SettingsIcon,
  AddIcon,
  DeleteIcon,
  CopyIcon,
  DownloadIcon,
  UploadIcon,
  RefreshIcon,
  ViewIcon,
  EditIcon,
  DragHandleIcon
} from '@chakra-ui/icons';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';
import { useDashboard } from '../../hooks/useDashboard';
import { useRealTimeUpdates } from '../../hooks/useRealTimeUpdates';
import WidgetSelector from './WidgetSelector';
import LayoutTemplateSelector from './LayoutTemplateSelector';
import WidgetConfigModal from './WidgetConfigModal';
import DashboardPreferences from './DashboardPreferences';
import { DashboardWidget, WidgetInstance, LayoutTemplate, CustomizationOptions } from '../../types/dashboard';

interface DashboardCustomizerProps {
  dashboard: any;
  onDashboardUpdate: (dashboard: any) => void;
}

const DashboardCustomizer: React.FC<DashboardCustomizerProps> = ({
  dashboard,
  onDashboardUpdate
}) => {
  const [isCustomizing, setIsCustomizing] = useState(false);
  const [availableWidgets, setAvailableWidgets] = useState<DashboardWidget[]>([]);
  const [layoutTemplates, setLayoutTemplates] = useState<LayoutTemplate[]>([]);
  const [customizationOptions, setCustomizationOptions] = useState<CustomizationOptions | null>(null);
  const [selectedWidget, setSelectedWidget] = useState<WidgetInstance | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const toast = useToast();
  const { updateDashboardLayout, addWidget, removeWidget, updateWidgetConfig } = useDashboard();
  const { onDashboardUpdate: onRealTimeUpdate } = useRealTimeUpdates();

  // Modal controls
  const { isOpen: isWidgetSelectorOpen, onOpen: onWidgetSelectorOpen, onClose: onWidgetSelectorClose } = useDisclosure();
  const { isOpen: isTemplateSelectorOpen, onOpen: onTemplateSelectorOpen, onClose: onTemplateSelectorClose } = useDisclosure();
  const { isOpen: isWidgetConfigOpen, onOpen: onWidgetConfigOpen, onClose: onWidgetConfigClose } = useDisclosure();
  const { isOpen: isPreferencesOpen, onOpen: onPreferencesOpen, onClose: onPreferencesClose } = useDisclosure();
  const { isOpen: isExportOpen, onOpen: onExportOpen, onClose: onExportClose } = useDisclosure();
  const { isOpen: isImportOpen, onOpen: onImportOpen, onClose: onImportClose } = useDisclosure();

  const bgColor = useColorModeValue('white', 'gray.800');
  const borderColor = useColorModeValue('gray.200', 'gray.600');

  // Load customization data
  useEffect(() => {
    loadCustomizationData();
  }, []);

  // Listen for real-time updates
  useEffect(() => {
    const unsubscribe = onRealTimeUpdate((data) => {
      if (data.type === 'layout_updated' || data.type === 'widget_added' || data.type === 'widget_removed') {
        loadCustomizationData();
      }
    });

    return unsubscribe;
  }, [onRealTimeUpdate]);

  const loadCustomizationData = async () => {
    try {
      setIsLoading(true);
      setError(null);

      const [widgetsResponse, templatesResponse, optionsResponse] = await Promise.all([
        fetch('/api/v1/dashboard/customization/widgets'),
        fetch('/api/v1/dashboard/customization/templates'),
        fetch('/api/v1/dashboard/customization/options')
      ]);

      if (!widgetsResponse.ok || !templatesResponse.ok || !optionsResponse.ok) {
        throw new Error('Failed to load customization data');
      }

      const [widgetsData, templatesData, optionsData] = await Promise.all([
        widgetsResponse.json(),
        templatesResponse.json(),
        optionsResponse.json()
      ]);

      setAvailableWidgets(widgetsData.data.widgets);
      setLayoutTemplates(templatesData.data.templates);
      setCustomizationOptions(optionsData.data);

    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load customization data');
      toast({
        title: 'Error',
        description: 'Failed to load customization data',
        status: 'error',
        duration: 5000,
        isClosable: true
      });
    } finally {
      setIsLoading(false);
    }
  };

  const handleDragEnd = useCallback(async (result: any) => {
    if (!result.destination) return;

    const newLayout = Array.from(dashboard.layout);
    const [reorderedItem] = newLayout.splice(result.source.index, 1);
    newLayout.splice(result.destination.index, 0, reorderedItem);

    // Update positions
    const updatedLayout = newLayout.map((widget: WidgetInstance, index: number) => ({
      ...widget,
      position: { x: 0, y: index * 4 }
    }));

    try {
      await updateDashboardLayout(updatedLayout);
      onDashboardUpdate({ ...dashboard, layout: updatedLayout });
      
      toast({
        title: 'Success',
        description: 'Dashboard layout updated',
        status: 'success',
        duration: 3000,
        isClosable: true
      });
    } catch (err) {
      toast({
        title: 'Error',
        description: 'Failed to update layout',
        status: 'error',
        duration: 5000,
        isClosable: true
      });
    }
  }, [dashboard, updateDashboardLayout, onDashboardUpdate, toast]);

  const handleAddWidget = useCallback(async (widgetId: string, config: any) => {
    try {
      const result = await addWidget(widgetId, config);
      if (result.success) {
        onDashboardUpdate({
          ...dashboard,
          layout: [...dashboard.layout, result.widget_instance]
        });
        
        toast({
          title: 'Success',
          description: 'Widget added successfully',
          status: 'success',
          duration: 3000,
          isClosable: true
        });
      }
    } catch (err) {
      toast({
        title: 'Error',
        description: 'Failed to add widget',
        status: 'error',
        duration: 5000,
        isClosable: true
      });
    }
  }, [dashboard, addWidget, onDashboardUpdate, toast]);

  const handleRemoveWidget = useCallback(async (widgetInstanceId: string) => {
    try {
      const result = await removeWidget(widgetInstanceId);
      if (result.success) {
        const updatedLayout = dashboard.layout.filter((widget: WidgetInstance) => widget.id !== widgetInstanceId);
        onDashboardUpdate({ ...dashboard, layout: updatedLayout });
        
        toast({
          title: 'Success',
          description: 'Widget removed successfully',
          status: 'success',
          duration: 3000,
          isClosable: true
        });
      }
    } catch (err) {
      toast({
        title: 'Error',
        description: 'Failed to remove widget',
        status: 'error',
        duration: 5000,
        isClosable: true
      });
    }
  }, [dashboard, removeWidget, onDashboardUpdate, toast]);

  const handleUpdateWidgetConfig = useCallback(async (widgetInstanceId: string, config: any) => {
    try {
      const result = await updateWidgetConfig(widgetInstanceId, config);
      if (result.success) {
        const updatedLayout = dashboard.layout.map((widget: WidgetInstance) =>
          widget.id === widgetInstanceId ? { ...widget, config: { ...widget.config, ...config } } : widget
        );
        onDashboardUpdate({ ...dashboard, layout: updatedLayout });
        
        toast({
          title: 'Success',
          description: 'Widget configuration updated',
          status: 'success',
          duration: 3000,
          isClosable: true
        });
      }
    } catch (err) {
      toast({
        title: 'Error',
        description: 'Failed to update widget configuration',
        status: 'error',
        duration: 5000,
        isClosable: true
      });
    }
  }, [dashboard, updateWidgetConfig, onDashboardUpdate, toast]);

  const handleApplyTemplate = useCallback(async (templateId: string) => {
    try {
      const response = await fetch('/api/v1/dashboard/customization/apply-template', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        },
        body: JSON.stringify({ template_id: templateId })
      });

      if (!response.ok) {
        throw new Error('Failed to apply template');
      }

      const result = await response.json();
      if (result.success) {
        onDashboardUpdate({ ...dashboard, layout: result.layout });
        
        toast({
          title: 'Success',
          description: 'Template applied successfully',
          status: 'success',
          duration: 3000,
          isClosable: true
        });
      }
    } catch (err) {
      toast({
        title: 'Error',
        description: 'Failed to apply template',
        status: 'error',
        duration: 5000,
        isClosable: true
      });
    }
  }, [dashboard, onDashboardUpdate, toast]);

  const handleExportDashboard = useCallback(async () => {
    try {
      const response = await fetch('/api/v1/dashboard/customization/export');
      if (!response.ok) {
        throw new Error('Failed to export dashboard');
      }

      const result = await response.json();
      if (result.success) {
        const dataStr = JSON.stringify(result.data, null, 2);
        const dataBlob = new Blob([dataStr], { type: 'application/json' });
        const url = URL.createObjectURL(dataBlob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `dashboard-${new Date().toISOString().split('T')[0]}.json`;
        link.click();
        URL.revokeObjectURL(url);
        
        toast({
          title: 'Success',
          description: 'Dashboard exported successfully',
          status: 'success',
          duration: 3000,
          isClosable: true
        });
      }
    } catch (err) {
      toast({
        title: 'Error',
        description: 'Failed to export dashboard',
        status: 'error',
        duration: 5000,
        isClosable: true
      });
    }
  }, [toast]);

  const handleImportDashboard = useCallback(async (file: File) => {
    try {
      const text = await file.text();
      const config = JSON.parse(text);

      const response = await fetch('/api/v1/dashboard/customization/import', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        },
        body: JSON.stringify({ dashboard_config: config })
      });

      if (!response.ok) {
        throw new Error('Failed to import dashboard');
      }

      const result = await response.json();
      if (result.success) {
        onDashboardUpdate({ ...dashboard, layout: result.data.layout });
        
        toast({
          title: 'Success',
          description: 'Dashboard imported successfully',
          status: 'success',
          duration: 3000,
          isClosable: true
        });
      }
    } catch (err) {
      toast({
        title: 'Error',
        description: 'Failed to import dashboard',
        status: 'error',
        duration: 5000,
        isClosable: true
      });
    }
  }, [dashboard, onDashboardUpdate, toast]);

  if (isLoading) {
    return (
      <Box p={8} textAlign="center">
        <Spinner size="xl" />
        <Text mt={4}>Loading customization options...</Text>
      </Box>
    );
  }

  if (error) {
    return (
      <Alert status="error" borderRadius="md">
        <AlertIcon />
        <AlertTitle>Error!</AlertTitle>
        <AlertDescription>{error}</AlertDescription>
      </Alert>
    );
  }

  return (
    <Box>
      {/* Customization Toolbar */}
      <Box
        p={4}
        bg={bgColor}
        borderBottom="1px"
        borderColor={borderColor}
        position="sticky"
        top={0}
        zIndex={10}
      >
        <Flex justify="space-between" align="center">
          <HStack spacing={4}>
            <Button
              leftIcon={<SettingsIcon />}
              colorScheme={isCustomizing ? 'blue' : 'gray'}
              variant={isCustomizing ? 'solid' : 'outline'}
              onClick={() => setIsCustomizing(!isCustomizing)}
            >
              {isCustomizing ? 'Exit Customization' : 'Customize Dashboard'}
            </Button>

            {isCustomizing && (
              <>
                <Button
                  leftIcon={<AddIcon />}
                  size="sm"
                  onClick={onWidgetSelectorOpen}
                >
                  Add Widget
                </Button>

                <Button
                  leftIcon={<ViewIcon />}
                  size="sm"
                  variant="outline"
                  onClick={onTemplateSelectorOpen}
                >
                  Templates
                </Button>

                <Button
                  leftIcon={<SettingsIcon />}
                  size="sm"
                  variant="outline"
                  onClick={onPreferencesOpen}
                >
                  Preferences
                </Button>
              </>
            )}
          </HStack>

          <HStack spacing={2}>
            <Tooltip label="Export Dashboard">
              <IconButton
                icon={<DownloadIcon />}
                size="sm"
                variant="ghost"
                onClick={onExportOpen}
              />
            </Tooltip>

            <Tooltip label="Import Dashboard">
              <IconButton
                icon={<UploadIcon />}
                size="sm"
                variant="ghost"
                onClick={onImportOpen}
              />
            </Tooltip>

            <Tooltip label="Refresh">
              <IconButton
                icon={<RefreshIcon />}
                size="sm"
                variant="ghost"
                onClick={loadCustomizationData}
              />
            </Tooltip>
          </HStack>
        </Flex>
      </Box>

      {/* Dashboard Layout */}
      <Box p={4}>
        {isCustomizing ? (
          <DragDropContext onDragEnd={handleDragEnd}>
            <Droppable droppableId="dashboard">
              {(provided, snapshot) => (
                <Box
                  ref={provided.innerRef}
                  {...provided.droppableProps}
                  minHeight="400px"
                  bg={snapshot.isDraggingOver ? 'blue.50' : 'transparent'}
                  borderRadius="md"
                  p={2}
                >
                  <Grid templateColumns="repeat(12, 1fr)" gap={4}>
                    {dashboard.layout.map((widget: WidgetInstance, index: number) => (
                      <Draggable key={widget.id} draggableId={widget.id} index={index}>
                        {(provided, snapshot) => (
                          <GridItem
                            ref={provided.innerRef}
                            {...provided.draggableProps}
                            colSpan={getColSpan(widget.size)}
                            rowSpan={getRowSpan(widget.size)}
                          >
                            <Card
                              bg={bgColor}
                              border="2px"
                              borderColor={snapshot.isDragging ? 'blue.500' : borderColor}
                              borderRadius="md"
                              boxShadow={snapshot.isDragging ? 'lg' : 'sm'}
                              position="relative"
                              group
                            >
                              <CardHeader pb={2}>
                                <Flex justify="space-between" align="center">
                                  <HStack>
                                    <DragHandleIcon cursor="grab" {...provided.dragHandleProps} />
                                    <Heading size="sm">{widget.title}</Heading>
                                    <Badge size="sm" colorScheme="blue">
                                      {widget.type}
                                    </Badge>
                                  </HStack>

                                  <HStack spacing={1} opacity={0} _groupHover={{ opacity: 1 }}>
                                    <Tooltip label="Configure">
                                      <IconButton
                                        icon={<EditIcon />}
                                        size="xs"
                                        variant="ghost"
                                        onClick={() => {
                                          setSelectedWidget(widget);
                                          onWidgetConfigOpen();
                                        }}
                                      />
                                    </Tooltip>

                                    <Tooltip label="Duplicate">
                                      <IconButton
                                        icon={<CopyIcon />}
                                        size="xs"
                                        variant="ghost"
                                        onClick={() => handleAddWidget(widget.widget_id, {
                                          ...widget.config,
                                          title: `${widget.title} (Copy)`
                                        })}
                                      />
                                    </Tooltip>

                                    <Tooltip label="Remove">
                                      <IconButton
                                        icon={<DeleteIcon />}
                                        size="xs"
                                        variant="ghost"
                                        colorScheme="red"
                                        onClick={() => handleRemoveWidget(widget.id)}
                                      />
                                    </Tooltip>
                                  </HStack>
                                </Flex>
                              </CardHeader>

                              <CardBody pt={0}>
                                <Box
                                  height="120px"
                                  bg="gray.50"
                                  borderRadius="md"
                                  display="flex"
                                  align="center"
                                  justify="center"
                                  color="gray.500"
                                >
                                  <Text fontSize="sm">Widget Preview</Text>
                                </Box>
                              </CardBody>
                            </Card>
                          </GridItem>
                        )}
                      </Draggable>
                    ))}
                  </Grid>
                  {provided.placeholder}
                </Box>
              )}
            </Droppable>
          </DragDropContext>
        ) : (
          <Grid templateColumns="repeat(12, 1fr)" gap={4}>
            {dashboard.layout.map((widget: WidgetInstance) => (
              <GridItem key={widget.id} colSpan={getColSpan(widget.size)} rowSpan={getRowSpan(widget.size)}>
                <Card bg={bgColor} border="1px" borderColor={borderColor}>
                  <CardHeader>
                    <Heading size="sm">{widget.title}</Heading>
                  </CardHeader>
                  <CardBody>
                    <Box
                      height="120px"
                      bg="gray.50"
                      borderRadius="md"
                      display="flex"
                      align="center"
                      justify="center"
                      color="gray.500"
                    >
                      <Text fontSize="sm">Widget Content</Text>
                    </Box>
                  </CardBody>
                </Card>
              </GridItem>
            ))}
          </Grid>
        )}
      </Box>

      {/* Modals */}
      <WidgetSelector
        isOpen={isWidgetSelectorOpen}
        onClose={onWidgetSelectorClose}
        availableWidgets={availableWidgets}
        onAddWidget={handleAddWidget}
      />

      <LayoutTemplateSelector
        isOpen={isTemplateSelectorOpen}
        onClose={onTemplateSelectorClose}
        templates={layoutTemplates}
        onApplyTemplate={handleApplyTemplate}
      />

      <WidgetConfigModal
        isOpen={isWidgetConfigOpen}
        onClose={onWidgetConfigClose}
        widget={selectedWidget}
        onUpdateConfig={handleUpdateWidgetConfig}
      />

      <DashboardPreferences
        isOpen={isPreferencesOpen}
        onClose={onPreferencesClose}
        options={customizationOptions}
      />

      {/* Export Modal */}
      <Modal isOpen={isExportOpen} onClose={onExportClose}>
        <ModalOverlay />
        <ModalContent>
          <ModalHeader>Export Dashboard</ModalHeader>
          <ModalCloseButton />
          <ModalBody>
            <Text mb={4}>
              Export your dashboard configuration to a JSON file. This includes your layout, 
              widget configurations, and preferences.
            </Text>
          </ModalBody>
          <ModalFooter>
            <Button variant="ghost" mr={3} onClick={onExportClose}>
              Cancel
            </Button>
            <Button colorScheme="blue" onClick={handleExportDashboard}>
              Export Dashboard
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Import Modal */}
      <Modal isOpen={isImportOpen} onClose={onImportClose}>
        <ModalOverlay />
        <ModalContent>
          <ModalHeader>Import Dashboard</ModalHeader>
          <ModalCloseButton />
          <ModalBody>
            <Text mb={4}>
              Import a dashboard configuration from a JSON file. This will replace your current dashboard.
            </Text>
            <Input
              type="file"
              accept=".json"
              onChange={(e) => {
                const file = e.target.files?.[0];
                if (file) {
                  handleImportDashboard(file);
                  onImportClose();
                }
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="ghost" mr={3} onClick={onImportClose}>
              Cancel
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </Box>
  );
};

// Helper functions
const getColSpan = (size: string): number => {
  const sizeMap: { [key: string]: number } = {
    'small': 3,
    'medium': 6,
    'large': 9,
    'extra-large': 12
  };
  return sizeMap[size] || 6;
};

const getRowSpan = (size: string): number => {
  const sizeMap: { [key: string]: number } = {
    'small': 2,
    'medium': 4,
    'large': 6,
    'extra-large': 8
  };
  return sizeMap[size] || 4;
};

export default DashboardCustomizer;
