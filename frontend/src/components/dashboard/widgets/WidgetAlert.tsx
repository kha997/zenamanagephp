import React from 'react';
import {
  Box,
  Text,
  Flex,
  Alert,
  AlertIcon,
  AlertTitle,
  AlertDescription,
  Badge,
  Button,
  VStack,
  HStack,
  Icon,
  Divider,
  Link
} from '@chakra-ui/react';
import {
  FiAlertTriangle,
  FiInfo,
  FiCheckCircle,
  FiXCircle,
  FiClock,
  FiExternalLink
} from 'react-icons/fi';

interface WidgetAlertProps {
  data: any;
  config: Record<string, any);
}

interface AlertItem {
  id: string;
  type: 'info' | 'warning' | 'error' | 'success';
  title: string;
  message: string;
  category?: string;
  timestamp?: string;
  isRead?: boolean;
  actionUrl?: string;
  actionText?: string;
}

const WidgetAlert: React.FC<WidgetAlertProps> = ({ data, config }) => {
  const displayConfig = config.display || {};
  const title = displayConfig.title || 'Alerts';
  const maxItems = displayConfig.max_items || 5;
  const showTimestamp = displayConfig.show_timestamp !== false;
  const showCategory = displayConfig.show_category !== false;

  const getAlertIcon = (type: string) => {
    const icons = {
      info: FiInfo,
      warning: FiAlertTriangle,
      error: FiXCircle,
      success: FiCheckCircle
    };
    return icons[type as keyof typeof icons] || FiInfo;
  };

  const getAlertStatus = (type: string) => {
    const statuses = {
      info: 'info',
      warning: 'warning',
      error: 'error',
      success: 'success'
    };
    return statuses[type as keyof typeof statuses] || 'info';
  };

  const getCategoryColor = (category: string) => {
    const colors = {
      task: 'blue',
      budget: 'green',
      quality: 'orange',
      safety: 'red',
      schedule: 'purple',
      system: 'gray'
    };
    return colors[category as keyof typeof colors] || 'gray';
  };

  const formatTimestamp = (timestamp: string) => {
    const date = new Date(timestamp);
    const now = new Date();
    const diffInHours = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60));
    
    if (diffInHours < 1) {
      return 'Just now';
    } else if (diffInHours < 24) {
      return `${diffInHours}h ago`;
    } else {
      const diffInDays = Math.floor(diffInHours / 24);
      return `${diffInDays}d ago`;
    }
  };

  const renderSingleAlert = (alert: AlertItem) => {
    return (
      <Alert
        status={getAlertStatus(alert.type)}
        borderRadius="md"
        alignItems="flex-start"
        p={4}
      >
        <AlertIcon mt={1} />
        <Box flex={1}>
          <Flex justify="space-between" align="start" mb={2}>
            <AlertTitle fontSize="sm" fontWeight="semibold">
              {alert.title}
            </AlertTitle>
            {showTimestamp && alert.timestamp && (
              <Text fontSize="xs" color="gray.500">
                {formatTimestamp(alert.timestamp)}
              </Text>
            )}
          </Flex>
          
          <AlertDescription fontSize="sm" color="gray.600">
            {alert.message}
          </AlertDescription>
          
          <Flex justify="space-between" align="center" mt={3}>
            <HStack spacing={2}>
              {showCategory && alert.category && (
                <Badge
                  size="sm"
                  colorScheme={getCategoryColor(alert.category)}
                  variant="subtle"
                >
                  {alert.category}
                </Badge>
              )}
              
              {!alert.isRead && (
                <Badge size="sm" colorScheme="blue" variant="solid">
                  New
                </Badge>
              )}
            </HStack>
            
            {alert.actionUrl && (
              <Button
                size="xs"
                variant="outline"
                rightIcon={<Icon as={FiExternalLink} />}
                as={Link}
                href={alert.actionUrl}
                isExternal
              >
                {alert.actionText || 'View'}
              </Button>
            )}
          </Flex>
        </Box>
      </Alert>
    );
  };

  const renderAlertList = () => {
    if (!Array.isArray(data)) {
      return (
        <Alert status="error" borderRadius="md">
          <AlertIcon />
          <AlertDescription>Invalid alert data format</AlertDescription>
        </Alert>
      );
    }

    const alerts = data.slice(0, maxItems);

    if (alerts.length === 0) {
      return (
        <Box textAlign="center" py={8}>
          <Icon as={FiCheckCircle} boxSize={8} color="green.500" mb={2} />
          <Text color="gray.500">No alerts</Text>
          <Text fontSize="sm" color="gray.400">All systems running smoothly</Text>
        </Box>
      );
    }

    return (
      <VStack spacing={3} align="stretch">
        {alerts.map((alert: AlertItem, index: number) => (
          <Box key={alert.id || index}>
            {renderSingleAlert(alert)}
            {index < alerts.length - 1 && <Divider />}
          </Box>
        ))}
      </VStack>
    );
  };

  const renderAlertSummary = () => {
    if (!data || typeof data !== 'object') {
      return (
        <Alert status="error" borderRadius="md">
          <AlertIcon />
          <AlertDescription>Invalid alert summary data</AlertDescription>
        </Alert>
      );
    }

    const { total, unread, byType, byCategory } = data;

    return (
      <VStack spacing={4} align="stretch">
        {/* Summary Stats */}
        <Flex justify="space-between" align="center">
          <Text fontSize="lg" fontWeight="semibold" color="gray.700">
            {title}
          </Text>
          <HStack spacing={2}>
            <Badge colorScheme="blue" variant="solid">
              {total || 0} Total
            </Badge>
            {unread > 0 && (
              <Badge colorScheme="red" variant="solid">
                {unread} Unread
              </Badge>
            )}
          </HStack>
        </Flex>

        {/* Alert Types */}
        {byType && (
          <Box>
            <Text fontSize="sm" fontWeight="semibold" color="gray.600" mb={2}>
              By Type
            </Text>
            <HStack spacing={2} flexWrap="wrap">
              {Object.entries(byType).map(([type, count]: [string, any]) => (
                <Badge
                  key={type}
                  colorScheme={getAlertStatus(type)}
                  variant="subtle"
                >
                  {type}: {count}
                </Badge>
              ))}
            </HStack>
          </Box>
        )}

        {/* Alert Categories */}
        {byCategory && (
          <Box>
            <Text fontSize="sm" fontWeight="semibold" color="gray.600" mb={2}>
              By Category
            </Text>
            <HStack spacing={2} flexWrap="wrap">
              {Object.entries(byCategory).map(([category, count]: [string, any]) => (
                <Badge
                  key={category}
                  colorScheme={getCategoryColor(category)}
                  variant="outline"
                >
                  {category}: {count}
                </Badge>
              ))}
            </HStack>
          </Box>
        )}

        {/* Action Buttons */}
        <Flex justify="space-between" pt={2}>
          <Button size="sm" variant="outline">
            View All Alerts
          </Button>
          {unread > 0 && (
            <Button size="sm" colorScheme="blue">
              Mark All Read
            </Button>
          )}
        </Flex>
      </VStack>
    );
  };

  if (!data) {
    return (
      <Box textAlign="center" py={8}>
        <Text color="gray.500">No alert data available</Text>
      </Box>
    );
  }

  const layout = displayConfig.layout || 'list';

  switch (layout) {
    case 'summary':
      return renderAlertSummary();
    case 'list':
    default:
      return renderAlertList();
  }
};

export default WidgetAlert;
