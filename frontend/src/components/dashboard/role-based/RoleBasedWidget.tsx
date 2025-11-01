import React, { useState, useEffect } from 'react';
import {
  Box,
  VStack,
  HStack,
  Text,
  Badge,
  Button,
  IconButton,
  useToast,
  Spinner,
  Alert,
  AlertIcon,
  AlertTitle,
  AlertDescription,
  Flex,
  Spacer,
  Tooltip,
  useColorModeValue,
  Card,
  CardBody,
  CardHeader,
  Heading,
  Divider,
  Stat,
  StatLabel,
  StatNumber,
  StatHelpText,
  StatArrow,
  Progress,
  SimpleGrid,
  Table,
  Thead,
  Tbody,
  Tr,
  Th,
  Td,
  TableContainer,
  Wrap,
  WrapItem,
  Avatar,
  Link,
  useDisclosure
} from '@chakra-ui/react';
import {
  ViewIcon,
  EditIcon,
  DownloadIcon,
  RefreshIcon,
  WarningIcon,
  CheckCircleIcon,
  InfoIcon,
  TimeIcon,
  CalendarIcon,
  UserIcon,
  ProjectIcon,
  SettingsIcon,
  ExternalLinkIcon
} from '@chakra-ui/icons';
import { DashboardWidget, WidgetInstance } from '../../types/dashboard';

interface RoleBasedWidgetProps {
  widget: DashboardWidget;
  widgetInstance: WidgetInstance;
  data: any;
  permissions: Record<string, boolean>;
  userRole: string;
  projectId?: string;
  onRefresh?: () => void;
  onConfigure?: () => void;
}

const RoleBasedWidget: React.FC<RoleBasedWidgetProps> = ({
  widget,
  widgetInstance,
  data,
  permissions,
  userRole,
  projectId,
  onRefresh,
  onConfigure
}) => {
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [lastUpdated, setLastUpdated] = useState<Date>(new Date());

  const toast = useToast();
  const bgColor = useColorModeValue('white', 'gray.800');
  const borderColor = useColorModeValue('gray.200', 'gray.600');

  useEffect(() => {
    setLastUpdated(new Date());
  }, [data]);

  const handleRefresh = async () => {
    if (!onRefresh) return;
    
    setIsLoading(true);
    try {
      await onRefresh();
      setLastUpdated(new Date());
      toast({
        title: 'Success',
        description: 'Widget data refreshed',
        status: 'success',
        duration: 2000,
        isClosable: true
      });
    } catch (err) {
      toast({
        title: 'Error',
        description: 'Failed to refresh widget data',
        status: 'error',
        duration: 3000,
        isClosable: true
      });
    } finally {
      setIsLoading(false);
    }
  };

  const getRoleSpecificData = () => {
    // Role-specific data processing
    switch (userRole) {
      case 'project_manager':
        return processProjectManagerData(data);
      case 'site_engineer':
        return processSiteEngineerData(data);
      case 'qc_inspector':
        return processQCInspectorData(data);
      case 'client_rep':
        return processClientRepData(data);
      default:
        return data;
    }
  };

  const processProjectManagerData = (rawData: any) => {
    // Add PM-specific insights
    return {
      ...rawData,
      insights: {
        budget_variance: rawData.budget_variance || 0,
        schedule_adherence: rawData.schedule_adherence || 0,
        team_productivity: rawData.team_productivity || 0
      }
    };
  };

  const processSiteEngineerData = (rawData: any) => {
    // Add site-specific insights
    return {
      ...rawData,
      insights: {
        daily_progress: rawData.daily_progress || 0,
        safety_score: rawData.safety_score || 0,
        weather_impact: rawData.weather_impact || 0
      }
    };
  };

  const processQCInspectorData = (rawData: any) => {
    // Add QC-specific insights
    return {
      ...rawData,
      insights: {
        quality_score: rawData.quality_score || 0,
        defect_rate: rawData.defect_rate || 0,
        inspection_completion: rawData.inspection_completion || 0
      }
    };
  };

  const processClientRepData = (rawData: any) => {
    // Add client-specific insights
    return {
      ...rawData,
      insights: {
        project_progress: rawData.project_progress || 0,
        budget_status: rawData.budget_status || 0,
        quality_summary: rawData.quality_summary || 0
      }
    };
  };

  const renderWidgetContent = () => {
    const processedData = getRoleSpecificData();

    switch (widget.type) {
      case 'metric':
        return <MetricWidget data={processedData} widget={widget} />;
      case 'chart':
        return <ChartWidget data={processedData} widget={widget} />;
      case 'table':
        return <TableWidget data={processedData} widget={widget} />;
      case 'card':
        return <CardWidget data={processedData} widget={widget} />;
      case 'alert':
        return <AlertWidget data={processedData} widget={widget} />;
      case 'timeline':
        return <TimelineWidget data={processedData} widget={widget} />;
      case 'progress':
        return <ProgressWidget data={processedData} widget={widget} />;
      default:
        return <DefaultWidget data={processedData} widget={widget} />;
    }
  };

  if (error) {
    return (
      <Card bg={bgColor} border="1px" borderColor="red.300">
        <CardBody>
          <Alert status="error" borderRadius="md">
            <AlertIcon />
            <AlertTitle>Widget Error</AlertTitle>
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        </CardBody>
      </Card>
    );
  }

  return (
    <Card bg={bgColor} border="1px" borderColor={borderColor} position="relative">
      <CardHeader pb={2}>
        <Flex justify="space-between" align="center">
          <VStack align="start" spacing={1}>
            <HStack>
              <Heading size="sm">{widgetInstance.title}</Heading>
              <Badge size="sm" colorScheme="blue">{widget.type}</Badge>
              {widgetInstance.is_customizable && (
                <Badge size="sm" colorScheme="green">Customizable</Badge>
              )}
            </HStack>
            <Text fontSize="xs" color="gray.500">
              Last updated: {lastUpdated.toLocaleTimeString()}
            </Text>
          </VStack>

          <HStack spacing={1}>
            {permissions.can_configure && (
              <Tooltip label="Configure Widget">
                <IconButton
                  icon={<SettingsIcon />}
                  size="xs"
                  variant="ghost"
                  onClick={onConfigure}
                />
              </Tooltip>
            )}
            
            <Tooltip label="Refresh Data">
              <IconButton
                icon={<RefreshIcon />}
                size="xs"
                variant="ghost"
                onClick={handleRefresh}
                isLoading={isLoading}
              />
            </Tooltip>

            <Tooltip label="View Details">
              <IconButton
                icon={<ExternalLinkIcon />}
                size="xs"
                variant="ghost"
                onClick={() => {
                  // Navigate to detailed view
                  toast({
                    title: 'Feature Coming Soon',
                    description: 'Detailed view will be available soon',
                    status: 'info',
                    duration: 3000,
                    isClosable: true
                  });
                }}
              />
            </Tooltip>
          </HStack>
        </Flex>
      </CardHeader>

      <CardBody pt={0}>
        {isLoading ? (
          <Box textAlign="center" py={8}>
            <Spinner size="lg" />
            <Text mt={2} fontSize="sm">Loading widget data...</Text>
          </Box>
        ) : (
          renderWidgetContent()
        )}
      </CardBody>

      {/* Role-specific footer */}
      <Box px={4} pb={2}>
        <Divider mb={2} />
        <Flex justify="space-between" align="center">
          <Text fontSize="xs" color="gray.500">
            {widget.category} • {userRole.replace('_', ' ')}
          </Text>
          {projectId && (
            <Badge size="sm" variant="outline">
              Project: {projectId.slice(-8)}
            </Badge>
          )}
        </Flex>
      </Box>
    </Card>
  );
};

// Widget Type Components
const MetricWidget: React.FC<{ data: any; widget: DashboardWidget }> = ({ data, widget }) => {
  const value = data.value || data.total || 0;
  const target = data.target || 0;
  const trend = data.trend || 'stable';
  const unit = data.unit || '';

  return (
    <VStack spacing={3}>
      <Stat textAlign="center">
        <StatLabel>{widget.name}</StatLabel>
        <StatNumber fontSize="2xl">{value}{unit}</StatNumber>
        {target > 0 && (
          <StatHelpText>
            Target: {target}{unit}
            {trend !== 'stable' && (
              <StatArrow type={trend === 'up' ? 'increase' : 'decrease'} />
            )}
          </StatHelpText>
        )}
      </Stat>
      
      {data.insights && (
        <SimpleGrid columns={2} spacing={2} width="100%">
          {Object.entries(data.insights).map(([key, value]) => (
            <Box key={key} textAlign="center">
              <Text fontSize="xs" color="gray.500">{key.replace('_', ' ')}</Text>
              <Text fontSize="sm" fontWeight="medium">{value}</Text>
            </Box>
          ))}
        </SimpleGrid>
      )}
    </VStack>
  );
};

const ChartWidget: React.FC<{ data: any; widget: DashboardWidget }> = ({ data, widget }) => {
  return (
    <Box height="200px" bg="gray.50" borderRadius="md" display="flex" align="center" justify="center">
      <VStack spacing={2}>
        <Text fontSize="sm" color="gray.500">Chart Visualization</Text>
        <Text fontSize="xs" color="gray.400">
          {data.dataPoints?.length || 0} data points
        </Text>
      </VStack>
    </Box>
  );
};

const TableWidget: React.FC<{ data: any; widget: DashboardWidget }> = ({ data, widget }) => {
  const rows = data.rows || [];
  const columns = data.columns || [];

  return (
    <TableContainer>
      <Table size="sm">
        <Thead>
          <Tr>
            {columns.map((column: string) => (
              <Th key={column}>{column}</Th>
            ))}
          </Tr>
        </Thead>
        <Tbody>
          {rows.slice(0, 5).map((row: any, index: number) => (
            <Tr key={index}>
              {columns.map((column: string) => (
                <Td key={column}>{row[column]}</Td>
              ))}
            </Tr>
          ))}
        </Tbody>
      </Table>
      {rows.length > 5 && (
        <Text fontSize="xs" color="gray.500" textAlign="center" mt={2}>
          Showing 5 of {rows.length} rows
        </Text>
      )}
    </TableContainer>
  );
};

const CardWidget: React.FC<{ data: any; widget: DashboardWidget }> = ({ data, widget }) => {
  return (
    <VStack spacing={3} align="stretch">
      <Text fontSize="sm" fontWeight="medium">{widget.name}</Text>
      <Text fontSize="sm" color="gray.600">{data.description || 'No description available'}</Text>
      
      {data.items && (
        <VStack spacing={2} align="stretch">
          {data.items.slice(0, 3).map((item: any, index: number) => (
            <HStack key={index} justify="space-between">
              <Text fontSize="sm">{item.label}</Text>
              <Badge size="sm" colorScheme="blue">{item.value}</Badge>
            </HStack>
          ))}
        </VStack>
      )}
    </VStack>
  );
};

const AlertWidget: React.FC<{ data: any; widget: DashboardWidget }> = ({ data, widget }) => {
  const alerts = data.alerts || [];

  return (
    <VStack spacing={3} align="stretch">
      <HStack justify="space-between">
        <Text fontSize="sm" fontWeight="medium">Recent Alerts</Text>
        <Badge colorScheme="red">{alerts.length}</Badge>
      </HStack>
      
      {alerts.length === 0 ? (
        <Text fontSize="sm" color="gray.500" textAlign="center">No alerts</Text>
      ) : (
        <VStack spacing={2} align="stretch">
          {alerts.slice(0, 3).map((alert: any, index: number) => (
            <HStack key={index} spacing={2}>
              <Badge size="sm" colorScheme={alert.severity === 'high' ? 'red' : 'yellow'}>
                {alert.severity}
              </Badge>
              <Text fontSize="sm" flex={1}>{alert.message}</Text>
            </HStack>
          ))}
        </VStack>
      )}
    </VStack>
  );
};

const TimelineWidget: React.FC<{ data: any; widget: DashboardWidget }> = ({ data, widget }) => {
  const events = data.events || [];

  return (
    <VStack spacing={3} align="stretch">
      <Text fontSize="sm" fontWeight="medium">Timeline</Text>
      
      {events.length === 0 ? (
        <Text fontSize="sm" color="gray.500" textAlign="center">No events</Text>
      ) : (
        <VStack spacing={2} align="stretch">
          {events.slice(0, 4).map((event: any, index: number) => (
            <HStack key={index} spacing={3}>
              <Box width="8px" height="8px" bg="blue.500" borderRadius="full" />
              <VStack align="start" spacing={0}>
                <Text fontSize="sm" fontWeight="medium">{event.title}</Text>
                <Text fontSize="xs" color="gray.500">{event.date}</Text>
              </VStack>
            </HStack>
          ))}
        </VStack>
      )}
    </VStack>
  );
};

const ProgressWidget: React.FC<{ data: any; widget: DashboardWidget }> = ({ data, widget }) => {
  const progress = data.progress || 0;
  const target = data.target || 100;

  return (
    <VStack spacing={3}>
      <HStack justify="space-between" width="100%">
        <Text fontSize="sm" fontWeight="medium">{widget.name}</Text>
        <Text fontSize="sm">{progress}%</Text>
      </HStack>
      
      <Progress value={progress} colorScheme="blue" width="100%" />
      
      {data.milestones && (
        <VStack spacing={1} align="stretch" width="100%">
          {data.milestones.map((milestone: any, index: number) => (
            <HStack key={index} justify="space-between">
              <Text fontSize="xs">{milestone.name}</Text>
              <Badge size="sm" colorScheme={milestone.completed ? 'green' : 'gray'}>
                {milestone.completed ? 'Done' : 'Pending'}
              </Badge>
            </HStack>
          ))}
        </VStack>
      )}
    </VStack>
  );
};

const DefaultWidget: React.FC<{ data: any; widget: DashboardWidget }> = ({ data, widget }) => {
  return (
    <Box height="150px" bg="gray.50" borderRadius="md" display="flex" align="center" justify="center">
      <VStack spacing={2}>
        <Text fontSize="sm" color="gray.500">Widget Preview</Text>
        <Text fontSize="xs" color="gray.400">
          {Object.keys(data).length} data fields
        </Text>
      </VStack>
    </Box>
  );
};

export default RoleBasedWidget;
