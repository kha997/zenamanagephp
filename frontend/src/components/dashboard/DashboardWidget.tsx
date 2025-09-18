import React, { useState, useEffect } from 'react';
import {
  Box,
  Flex,
  Text,
  IconButton,
  Spinner,
  Alert,
  AlertIcon,
  Menu,
  MenuButton,
  MenuList,
  MenuItem,
  useToast,
  Badge,
  Tooltip
} from '@chakra-ui/react';
import {
  DragHandleIcon,
  DeleteIcon,
  SettingsIcon,
  RefreshIcon,
  ExternalLinkIcon
} from '@chakra-ui/icons';
import WidgetCard from './widgets/WidgetCard';
import WidgetChart from './widgets/WidgetChart';
import WidgetTable from './widgets/WidgetTable';
import WidgetMetric from './widgets/WidgetMetric';
import WidgetAlert from './widgets/WidgetAlert';
import { useDashboard } from '../../hooks/useDashboard';

interface DashboardWidgetProps {
  widget: {
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
  };
  onRemove: () => void;
  onConfigUpdate: (config: Record<string, any>) => void;
  isDragging?: boolean;
}

const DashboardWidget: React.FC<DashboardWidgetProps> = ({
  widget,
  onRemove,
  onConfigUpdate,
  isDragging = false
}) => {
  const { getWidgetData, loading: dataLoading, error: dataError } = useDashboard();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const toast = useToast();

  useEffect(() => {
    fetchWidgetData();
  }, [widget.id, widget.config]);

  const fetchWidgetData = async () => {
    try {
      setLoading(true);
      const widgetData = await getWidgetData(widget.id);
      setData(widgetData);
    } catch (error) {
      console.error('Error fetching widget data:', error);
      toast({
        title: 'Error loading widget data',
        description: 'Failed to load data for this widget',
        status: 'error',
        duration: 3000,
        isClosable: true,
      });
    } finally {
      setLoading(false);
    }
  };

  const handleRefresh = () => {
    fetchWidgetData();
  };

  const handleConfigUpdate = (newConfig: Record<string, any>) => {
    onConfigUpdate(newConfig);
  };

  const renderWidgetContent = () => {
    if (loading || dataLoading) {
      return (
        <Flex justify="center" align="center" h="200px">
          <Spinner size="lg" color="blue.500" />
        </Flex>
      );
    }

    if (dataError) {
      return (
        <Alert status="error" borderRadius="md">
          <AlertIcon />
          <Text fontSize="sm">Failed to load widget data</Text>
        </Alert>
      );
    }

    switch (widget.type) {
      case 'card':
        return <WidgetCard data={data} config={widget.config} />;
      case 'chart':
        return <WidgetChart data={data} config={widget.config} />;
      case 'table':
        return <WidgetTable data={data} config={widget.config} />;
      case 'metric':
        return <WidgetMetric data={data} config={widget.config} />;
      case 'alert':
        return <WidgetAlert data={data} config={widget.config} />;
      default:
        return (
          <Box p={4} textAlign="center">
            <Text color="gray.500">Unknown widget type: {widget.type}</Text>
          </Box>
        );
    }
  };

  const getCategoryColor = (category: string) => {
    const colors = {
      overview: 'blue',
      progress: 'green',
      analytics: 'purple',
      alerts: 'red',
      quality: 'orange',
      budget: 'teal',
      safety: 'red'
    };
    return colors[category as keyof typeof colors] || 'gray';
  };

  return (
    <Box
      bg="white"
      borderRadius="lg"
      border="1px"
      borderColor="gray.200"
      shadow="sm"
      overflow="hidden"
      transition="all 0.2s"
      _hover={{
        shadow: 'md',
        borderColor: 'gray.300'
      }}
      opacity={isDragging ? 0.8 : 1}
      transform={isDragging ? 'rotate(2deg)' : 'none'}
    >
      {/* Widget Header */}
      <Flex
        justify="space-between"
        align="center"
        p={3}
        bg="gray.50"
        borderBottom="1px"
        borderColor="gray.200"
      >
        <Flex align="center" gap={2}>
          <Box
            w={2}
            h={2}
            borderRadius="full"
            bg={`${getCategoryColor(widget.category)}.500`}
          />
          <Text fontSize="sm" fontWeight="semibold" color="gray.700">
            {widget.name}
          </Text>
          <Badge
            size="sm"
            colorScheme={getCategoryColor(widget.category)}
            variant="subtle"
          >
            {widget.category}
          </Badge>
        </Flex>

        <Flex align="center" gap={1}>
          <Tooltip label="Refresh data">
            <IconButton
              aria-label="Refresh"
              icon={<RefreshIcon />}
              size="xs"
              variant="ghost"
              onClick={handleRefresh}
              isLoading={loading}
            />
          </Tooltip>

          <Menu>
            <MenuButton
              as={IconButton}
              aria-label="Widget options"
              icon={<SettingsIcon />}
              size="xs"
              variant="ghost"
            />
            <MenuList>
              <MenuItem icon={<SettingsIcon />} onClick={() => onConfigUpdate({})}>
                Configure
              </MenuItem>
              <MenuItem icon={<ExternalLinkIcon />}>
                View Details
              </MenuItem>
              <MenuItem
                icon={<DeleteIcon />}
                color="red.500"
                onClick={onRemove}
              >
                Remove
              </MenuItem>
            </MenuList>
          </Menu>

          <Box
            cursor="grab"
            _active={{ cursor: 'grabbing' }}
            p={1}
            borderRadius="md"
            _hover={{ bg: 'gray.200' }}
          >
            <DragHandleIcon w={3} h={3} color="gray.500" />
          </Box>
        </Flex>
      </Flex>

      {/* Widget Content */}
      <Box p={4} minH="200px">
        {renderWidgetContent()}
      </Box>
    </Box>
  );
};

export default DashboardWidget;
