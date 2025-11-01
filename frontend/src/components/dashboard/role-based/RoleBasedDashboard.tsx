import React, { useState, useEffect, useCallback } from 'react';
import {
  Box,
  VStack,
  HStack,
  Text,
  Badge,
  Button,
  Select,
  useToast,
  Spinner,
  Alert,
  AlertIcon,
  AlertTitle,
  AlertDescription,
  Flex,
  Spacer,
  IconButton,
  Tooltip,
  useColorModeValue,
  Grid,
  GridItem,
  Card,
  CardBody,
  CardHeader,
  Heading,
  Divider,
  Tabs,
  TabList,
  TabPanels,
  Tab,
  TabPanel,
  Stat,
  StatLabel,
  StatNumber,
  StatHelpText,
  StatArrow,
  Progress,
  SimpleGrid,
  Wrap,
  WrapItem,
  Avatar,
  Menu,
  MenuButton,
  MenuList,
  MenuItem,
  MenuDivider,
  useDisclosure
} from '@chakra-ui/react';
import {
  ChevronDownIcon,
  SettingsIcon,
  RefreshIcon,
  ViewIcon,
  EditIcon,
  DownloadIcon,
  BellIcon,
  WarningIcon,
  CheckCircleIcon,
  InfoIcon,
  TimeIcon,
  CalendarIcon,
  UserIcon,
  ProjectIcon
} from '@chakra-ui/icons';
import { useAuth } from '../../hooks/useAuth';
import { useRealTimeUpdates } from '../../hooks/useRealTimeUpdates';
import DashboardCustomizer from '../customization/DashboardCustomizer';
import { DashboardWidget, WidgetInstance, DashboardAlert, DashboardMetric } from '../../types/dashboard';

interface RoleBasedDashboardProps {
  projectId?: string;
  onProjectChange?: (projectId: string) => void;
}

interface RoleConfig {
  name: string;
  description: string;
  default_widgets: string[];
  widget_categories: string[];
  data_access: string;
  project_access: string;
  customization_level: string;
  priority_metrics: string[];
  alert_types: string[];
  dashboard_layout: string;
}

interface DashboardData {
  dashboard: {
    id: string;
    name: string;
    layout: WidgetInstance[];
    preferences: Record<string, any>;
    is_default: boolean;
  };
  widgets: Array<{
    widget: DashboardWidget;
    data: any;
    permissions: Record<string, boolean>;
  }>;
  metrics: Array<{
    metric: DashboardMetric;
    value: number;
    trend: string;
    target: number;
  }>;
  alerts: DashboardAlert[];
  permissions: Record<string, string[]>;
  role_config: RoleConfig;
  project_context: {
    current_project?: any;
    available_projects: any[];
  };
}

const RoleBasedDashboard: React.FC<RoleBasedDashboardProps> = ({
  projectId,
  onProjectChange
}) => {
  const { user } = useAuth();
  const [dashboardData, setDashboardData] = useState<DashboardData | null>(null);
  const [availableProjects, setAvailableProjects] = useState<any[]>([]);
  const [selectedProject, setSelectedProject] = useState<string>(projectId || '');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isCustomizing, setIsCustomizing] = useState(false);
  const [activeTab, setActiveTab] = useState(0);

  const toast = useToast();
  const { onDashboardUpdate } = useRealTimeUpdates();

  const bgColor = useColorModeValue('white', 'gray.800');
  const borderColor = useColorModeValue('gray.200', 'gray.600');
  const headerBg = useColorModeValue('gray.50', 'gray.700');

  // Load dashboard data
  useEffect(() => {
    loadDashboardData();
  }, [selectedProject]);

  // Load available projects
  useEffect(() => {
    loadAvailableProjects();
  }, []);

  // Listen for real-time updates
  useEffect(() => {
    if (!onDashboardUpdate) return;

    const unsubscribe = onDashboardUpdate((data) => {
      if (data.type === 'dashboard_update' || data.type === 'widget_update') {
        loadDashboardData();
      }
    });

    return unsubscribe;
  }, [onDashboardUpdate]);

  const loadDashboardData = async () => {
    try {
      setIsLoading(true);
      setError(null);

      const params = new URLSearchParams();
      if (selectedProject) {
        params.append('project_id', selectedProject);
      }

      const response = await fetch(`/api/v1/dashboard/role-based?${params}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });

      if (!response.ok) {
        throw new Error('Failed to load dashboard');
      }

      const result = await response.json();
      if (result.success) {
        setDashboardData(result.data);
      } else {
        throw new Error(result.message);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load dashboard');
      toast({
        title: 'Error',
        description: 'Failed to load dashboard data',
        status: 'error',
        duration: 5000,
        isClosable: true
      });
    } finally {
      setIsLoading(false);
    }
  };

  const loadAvailableProjects = async () => {
    try {
      const response = await fetch('/api/v1/dashboard/role-based/projects', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });

      if (!response.ok) {
        throw new Error('Failed to load projects');
      }

      const result = await response.json();
      if (result.success) {
        setAvailableProjects(result.data.projects);
      }
    } catch (err) {
      console.error('Failed to load projects:', err);
    }
  };

  const handleProjectChange = async (newProjectId: string) => {
    try {
      const response = await fetch('/api/v1/dashboard/role-based/switch-project', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        },
        body: JSON.stringify({ project_id: newProjectId })
      });

      if (!response.ok) {
        throw new Error('Failed to switch project');
      }

      const result = await response.json();
      if (result.success) {
        setSelectedProject(newProjectId);
        setDashboardData(result.data.dashboard);
        onProjectChange?.(newProjectId);
        
        toast({
          title: 'Success',
          description: 'Project context switched successfully',
          status: 'success',
          duration: 3000,
          isClosable: true
        });
      }
    } catch (err) {
      toast({
        title: 'Error',
        description: 'Failed to switch project context',
        status: 'error',
        duration: 5000,
        isClosable: true
      });
    }
  };

  const handleRefresh = () => {
    loadDashboardData();
  };

  const getRoleColor = (role: string) => {
    const roleColors: { [key: string]: string } = {
      'system_admin': 'purple',
      'project_manager': 'blue',
      'design_lead': 'green',
      'site_engineer': 'orange',
      'qc_inspector': 'red',
      'client_rep': 'teal',
      'subcontractor_lead': 'gray'
    };
    return roleColors[role] || 'gray';
  };

  const getSeverityColor = (severity: string) => {
    const severityColors: { [key: string]: string } = {
      'low': 'green',
      'medium': 'yellow',
      'high': 'orange',
      'critical': 'red'
    };
    return severityColors[severity] || 'gray';
  };

  const getTrendIcon = (trend: string) => {
    switch (trend) {
      case 'up':
        return <StatArrow type="increase" />;
      case 'down':
        return <StatArrow type="decrease" />;
      default:
        return <TimeIcon />;
    }
  };

  if (isLoading) {
    return (
      <Box p={8} textAlign="center">
        <Spinner size="xl" />
        <Text mt={4}>Loading role-based dashboard...</Text>
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

  if (!dashboardData) {
    return (
      <Box p={8} textAlign="center">
        <Text>No dashboard data available</Text>
      </Box>
    );
  }

  return (
    <Box>
      {/* Header */}
      <Box
        p={4}
        bg={headerBg}
        borderBottom="1px"
        borderColor={borderColor}
        position="sticky"
        top={0}
        zIndex={10}
      >
        <Flex justify="space-between" align="center">
          <HStack spacing={4}>
            <VStack align="start" spacing={1}>
              <HStack>
                <Heading size="md">{dashboardData.role_config.name} Dashboard</Heading>
                <Badge colorScheme={getRoleColor(user?.role || '')}>
                  {user?.role?.replace('_', ' ')}
                </Badge>
              </HStack>
              <Text fontSize="sm" color="gray.600">
                {dashboardData.role_config.description}
              </Text>
            </VStack>

            {/* Project Selector */}
            {availableProjects.length > 0 && (
              <Select
                value={selectedProject}
                onChange={(e) => handleProjectChange(e.target.value)}
                placeholder="Select Project"
                width="200px"
              >
                <option value="">All Projects</option>
                {availableProjects.map((project) => (
                  <option key={project.id} value={project.id}>
                    {project.name}
                  </option>
                ))}
              </Select>
            )}
          </HStack>

          <HStack spacing={2}>
            <Tooltip label="Refresh Dashboard">
              <IconButton
                icon={<RefreshIcon />}
                size="sm"
                variant="ghost"
                onClick={handleRefresh}
              />
            </Tooltip>

            {dashboardData.permissions.widgets?.includes('edit') && (
              <Button
                leftIcon={<SettingsIcon />}
                size="sm"
                colorScheme={isCustomizing ? 'blue' : 'gray'}
                variant={isCustomizing ? 'solid' : 'outline'}
                onClick={() => setIsCustomizing(!isCustomizing)}
              >
                {isCustomizing ? 'Exit Customization' : 'Customize'}
              </Button>
            )}

            <Menu>
              <MenuButton as={Button} rightIcon={<ChevronDownIcon />} size="sm">
                Actions
              </MenuButton>
              <MenuList>
                <MenuItem icon={<ViewIcon />}>View Reports</MenuItem>
                <MenuItem icon={<DownloadIcon />}>Export Data</MenuItem>
                <MenuDivider />
                <MenuItem icon={<SettingsIcon />}>Dashboard Settings</MenuItem>
              </MenuList>
            </Menu>
          </HStack>
        </Flex>
      </Box>

      {/* Dashboard Content */}
      <Box p={4}>
        {isCustomizing ? (
          <DashboardCustomizer
            dashboard={dashboardData.dashboard}
            onDashboardUpdate={(updatedDashboard) => {
              setDashboardData(prev => prev ? {
                ...prev,
                dashboard: updatedDashboard
              } : null);
            }}
          />
        ) : (
          <Tabs index={activeTab} onChange={setActiveTab}>
            <TabList>
              <Tab>
                <HStack spacing={2}>
                  <ViewIcon />
                  <Text>Overview</Text>
                </HStack>
              </Tab>
              <Tab>
                <HStack spacing={2}>
                  <BellIcon />
                  <Text>Alerts</Text>
                  {dashboardData.alerts.length > 0 && (
                    <Badge colorScheme="red" size="sm">
                      {dashboardData.alerts.filter(alert => !alert.is_read).length}
                    </Badge>
                  )}
                </HStack>
              </Tab>
              <Tab>
                <HStack spacing={2}>
                  <CheckCircleIcon />
                  <Text>Metrics</Text>
                </HStack>
              </Tab>
              <Tab>
                <HStack spacing={2}>
                  <ProjectIcon />
                  <Text>Projects</Text>
                </HStack>
              </Tab>
            </TabList>

            <TabPanels>
              {/* Overview Tab */}
              <TabPanel p={0} pt={4}>
                <VStack spacing={6} align="stretch">
                  {/* Quick Stats */}
                  <SimpleGrid columns={{ base: 2, md: 4 }} spacing={4}>
                    <Card>
                      <CardBody>
                        <Stat>
                          <StatLabel>Total Widgets</StatLabel>
                          <StatNumber>{dashboardData.widgets.length}</StatNumber>
                          <StatHelpText>
                            {dashboardData.role_config.widget_categories.length} categories
                          </StatHelpText>
                        </Stat>
                      </CardBody>
                    </Card>

                    <Card>
                      <CardBody>
                        <Stat>
                          <StatLabel>Active Alerts</StatLabel>
                          <StatNumber color="red.500">
                            {dashboardData.alerts.filter(alert => !alert.is_read).length}
                          </StatNumber>
                          <StatHelpText>
                            {dashboardData.alerts.length} total alerts
                          </StatHelpText>
                        </Stat>
                      </CardBody>
                    </Card>

                    <Card>
                      <CardBody>
                        <Stat>
                          <StatLabel>Key Metrics</StatLabel>
                          <StatNumber>{dashboardData.metrics.length}</StatNumber>
                          <StatHelpText>
                            {dashboardData.role_config.priority_metrics.length} priority metrics
                          </StatHelpText>
                        </Stat>
                      </CardBody>
                    </Card>

                    <Card>
                      <CardBody>
                        <Stat>
                          <StatLabel>Projects</StatLabel>
                          <StatNumber>{availableProjects.length}</StatNumber>
                          <StatHelpText>
                            {dashboardData.project_context.current_project ? '1 selected' : 'All projects'}
                          </StatHelpText>
                        </Stat>
                      </CardBody>
                    </Card>
                  </SimpleGrid>

                  {/* Widget Grid */}
                  <Box>
                    <Heading size="md" mb={4}>Dashboard Widgets</Heading>
                    <Grid templateColumns="repeat(12, 1fr)" gap={4}>
                      {dashboardData.dashboard.layout.map((widgetInstance) => {
                        const widgetData = dashboardData.widgets.find(w => w.widget.id === widgetInstance.widget_id);
                        const colSpan = getColSpan(widgetInstance.size);
                        const rowSpan = getRowSpan(widgetInstance.size);

                        return (
                          <GridItem key={widgetInstance.id} colSpan={colSpan} rowSpan={rowSpan}>
                            <Card bg={bgColor} border="1px" borderColor={borderColor}>
                              <CardHeader pb={2}>
                                <Flex justify="space-between" align="center">
                                  <Heading size="sm">{widgetInstance.title}</Heading>
                                  <Badge size="sm" colorScheme="blue">
                                    {widgetInstance.type}
                                  </Badge>
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
                                  <VStack spacing={2}>
                                    <Text fontSize="sm">Widget Preview</Text>
                                    {widgetData && (
                                      <Text fontSize="xs">
                                        {Object.keys(widgetData.data).length} data points
                                      </Text>
                                    )}
                                  </VStack>
                                </Box>
                              </CardBody>
                            </Card>
                          </GridItem>
                        );
                      })}
                    </Grid>
                  </Box>
                </VStack>
              </TabPanel>

              {/* Alerts Tab */}
              <TabPanel p={0} pt={4}>
                <VStack spacing={4} align="stretch">
                  <Heading size="md">Recent Alerts</Heading>
                  
                  {dashboardData.alerts.length === 0 ? (
                    <Alert status="info" borderRadius="md">
                      <AlertIcon />
                      <AlertTitle>No Alerts</AlertTitle>
                      <AlertDescription>
                        You have no alerts at this time.
                      </AlertDescription>
                    </Alert>
                  ) : (
                    <VStack spacing={3} align="stretch">
                      {dashboardData.alerts.slice(0, 10).map((alert) => (
                        <Card key={alert.id} bg={bgColor} border="1px" borderColor={borderColor}>
                          <CardBody>
                            <Flex justify="space-between" align="start">
                              <VStack align="start" spacing={2}>
                                <HStack>
                                  <Badge colorScheme={getSeverityColor(alert.severity)}>
                                    {alert.severity}
                                  </Badge>
                                  <Badge variant="outline">{alert.type}</Badge>
                                  {!alert.is_read && (
                                    <Badge colorScheme="blue" size="sm">New</Badge>
                                  )}
                                </HStack>
                                <Text fontWeight="medium">{alert.message}</Text>
                                <Text fontSize="sm" color="gray.600">
                                  {new Date(alert.triggered_at).toLocaleString()}
                                </Text>
                              </VStack>
                              <IconButton
                                icon={<BellIcon />}
                                size="sm"
                                variant="ghost"
                                aria-label="Mark as read"
                              />
                            </Flex>
                          </CardBody>
                        </Card>
                      ))}
                    </VStack>
                  )}
                </VStack>
              </TabPanel>

              {/* Metrics Tab */}
              <TabPanel p={0} pt={4}>
                <VStack spacing={4} align="stretch">
                  <Heading size="md">Key Performance Metrics</Heading>
                  
                  <SimpleGrid columns={{ base: 1, md: 2, lg: 3 }} spacing={4}>
                    {dashboardData.metrics.map((metricData) => (
                      <Card key={metricData.metric.id} bg={bgColor} border="1px" borderColor={borderColor}>
                        <CardBody>
                          <Stat>
                            <StatLabel>{metricData.metric.name}</StatLabel>
                            <StatNumber>{metricData.value}</StatNumber>
                            <StatHelpText>
                              {getTrendIcon(metricData.trend)}
                              Target: {metricData.target}
                            </StatHelpText>
                          </Stat>
                        </CardBody>
                      </Card>
                    ))}
                  </SimpleGrid>
                </VStack>
              </TabPanel>

              {/* Projects Tab */}
              <TabPanel p={0} pt={4}>
                <VStack spacing={4} align="stretch">
                  <Heading size="md">Project Overview</Heading>
                  
                  {dashboardData.project_context.current_project ? (
                    <Card bg={bgColor} border="1px" borderColor={borderColor}>
                      <CardHeader>
                        <Heading size="sm">Current Project</Heading>
                      </CardHeader>
                      <CardBody>
                        <VStack align="start" spacing={3}>
                          <Text fontWeight="medium">{dashboardData.project_context.current_project.name}</Text>
                          <HStack spacing={4}>
                            <Badge colorScheme="blue">{dashboardData.project_context.current_project.status}</Badge>
                            <Text fontSize="sm">
                              Progress: {dashboardData.project_context.current_project.progress}%
                            </Text>
                          </HStack>
                          <Progress
                            value={dashboardData.project_context.current_project.progress}
                            colorScheme="blue"
                            width="100%"
                          />
                        </VStack>
                      </CardBody>
                    </Card>
                  ) : (
                    <SimpleGrid columns={{ base: 1, md: 2, lg: 3 }} spacing={4}>
                      {availableProjects.map((project) => (
                        <Card key={project.id} bg={bgColor} border="1px" borderColor={borderColor}>
                          <CardBody>
                            <VStack align="start" spacing={3}>
                              <Text fontWeight="medium">{project.name}</Text>
                              <HStack spacing={2}>
                                <Badge colorScheme="blue">{project.status}</Badge>
                                <Text fontSize="sm">
                                  Progress: {project.progress_percentage}%
                                </Text>
                              </HStack>
                              <Progress
                                value={project.progress_percentage}
                                colorScheme="blue"
                                width="100%"
                              />
                            </VStack>
                          </CardBody>
                        </Card>
                      ))}
                    </SimpleGrid>
                  )}
                </VStack>
              </TabPanel>
            </TabPanels>
          </Tabs>
        )}
      </Box>
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

export default RoleBasedDashboard;
