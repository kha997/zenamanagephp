import React, { useEffect, useState } from 'react';
import {
  Box,
  Flex,
  Text,
  Badge,
  IconButton,
  Tooltip,
  Alert,
  AlertIcon,
  Spinner,
  HStack,
  VStack,
  useToast,
  useColorModeValue
} from '@chakra-ui/react';
import {
  FiWifi,
  FiWifiOff,
  FiRefreshCw,
  FiActivity,
  FiClock,
  FiMessageSquare
} from 'react-icons/fi';
import DashboardLayout from './DashboardLayout';
import { useRealTimeUpdates } from '../../hooks/useRealTimeUpdates';
import { useAuth } from '../../hooks/useAuth';

interface RealTimeDashboardProps {
  role: string;
  projectId?: string;
}

const RealTimeDashboard: React.FC<RealTimeDashboardProps> = ({ role, projectId }) => {
  const { user } = useAuth();
  const toast = useToast();
  const [lastUpdate, setLastUpdate] = useState<Date | null>(null);
  const [updateCount, setUpdateCount] = useState(0);

  const {
    isConnected,
    connectionType,
    error,
    lastEvent,
    stats,
    connect,
    disconnect,
    reconnect,
    onDashboardUpdate,
    onWidgetUpdate,
    onNewAlert,
    onMetricUpdate,
    onProjectUpdate,
    onSystemNotification
  } = useRealTimeUpdates({
    channels: ['dashboard', 'alerts', 'metrics', 'notifications'],
    projectId,
    autoReconnect: true,
    reconnectInterval: 5000,
    heartbeatInterval: 30000
  });

  const bgColor = useColorModeValue('white', 'gray.800');
  const borderColor = useColorModeValue('gray.200', 'gray.600');

  // Handle dashboard updates
  useEffect(() => {
    const unsubscribe = onDashboardUpdate((data) => {
      setLastUpdate(new Date());
      setUpdateCount(prev => prev + 1);
      
      toast({
        title: 'Dashboard Updated',
        description: 'Your dashboard has been refreshed with new data',
        status: 'info',
        duration: 3000,
        isClosable: true,
      });
    });

    return unsubscribe;
  }, [onDashboardUpdate, toast]);

  // Handle widget updates
  useEffect(() => {
    const unsubscribe = onWidgetUpdate((data) => {
      console.log('Widget update:', data);
      // Widget components will handle their own updates
    });

    return unsubscribe;
  }, [onWidgetUpdate]);

  // Handle new alerts
  useEffect(() => {
    const unsubscribe = onNewAlert((data) => {
      toast({
        title: 'New Alert',
        description: data.alert?.title || 'You have a new alert',
        status: data.alert?.type === 'error' ? 'error' : 'info',
        duration: 5000,
        isClosable: true,
      });
    });

    return unsubscribe;
  }, [onNewAlert, toast]);

  // Handle metric updates
  useEffect(() => {
    const unsubscribe = onMetricUpdate((data) => {
      console.log('Metric update:', data);
      // Metric widgets will handle their own updates
    });

    return unsubscribe;
  }, [onMetricUpdate]);

  // Handle project updates
  useEffect(() => {
    const unsubscribe = onProjectUpdate((data) => {
      toast({
        title: 'Project Update',
        description: `Project ${data.project_id} has been updated`,
        status: 'info',
        duration: 3000,
        isClosable: true,
      });
    });

    return unsubscribe;
  }, [onProjectUpdate, toast]);

  // Handle system notifications
  useEffect(() => {
    const unsubscribe = onSystemNotification((data) => {
      toast({
        title: 'System Notification',
        description: data.message,
        status: data.type === 'error' ? 'error' : 'info',
        duration: 5000,
        isClosable: true,
      });
    });

    return unsubscribe;
  }, [onSystemNotification, toast]);

  // Format connection uptime
  const formatUptime = (seconds: number) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) {
      return `${hours}h ${minutes}m ${secs}s`;
    } else if (minutes > 0) {
      return `${minutes}m ${secs}s`;
    } else {
      return `${secs}s`;
    }
  };

  // Format last event time
  const formatLastEvent = (timestamp: string) => {
    const date = new Date(timestamp);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);
    
    if (diffInSeconds < 60) {
      return `${diffInSeconds}s ago`;
    } else if (diffInSeconds < 3600) {
      return `${Math.floor(diffInSeconds / 60)}m ago`;
    } else {
      return date.toLocaleTimeString();
    }
  };

  return (
    <Box>
      {/* Real-time Status Bar */}
      <Box
        bg={bgColor}
        border="1px"
        borderColor={borderColor}
        borderRadius="md"
        p={3}
        mb={4}
        shadow="sm"
      >
        <Flex justify="space-between" align="center">
          <HStack spacing={4}>
            {/* Connection Status */}
            <HStack spacing={2}>
              {isConnected ? (
                <Tooltip label={`Connected via ${connectionType?.toUpperCase()}`}>
                  <Badge colorScheme="green" variant="solid">
                    <HStack spacing={1}>
                      <FiWifi size={12} />
                      <Text fontSize="xs">LIVE</Text>
                    </HStack>
                  </Badge>
                </Tooltip>
              ) : (
                <Tooltip label="Disconnected">
                  <Badge colorScheme="red" variant="solid">
                    <HStack spacing={1}>
                      <FiWifiOff size={12} />
                      <Text fontSize="xs">OFFLINE</Text>
                    </HStack>
                  </Badge>
                </Tooltip>
              )}
            </HStack>

            {/* Connection Type */}
            {connectionType && (
              <Badge colorScheme="blue" variant="outline">
                {connectionType.toUpperCase()}
              </Badge>
            )}

            {/* Stats */}
            <HStack spacing={3} fontSize="sm" color="gray.600">
              <HStack spacing={1}>
                <FiMessageSquare size={14} />
                <Text>{stats.messagesReceived}</Text>
              </HStack>
              
              {stats.connectionUptime > 0 && (
                <HStack spacing={1}>
                  <FiClock size={14} />
                  <Text>{formatUptime(stats.connectionUptime)}</Text>
                </HStack>
              )}
              
              {lastUpdate && (
                <HStack spacing={1}>
                  <FiActivity size={14} />
                  <Text>{formatLastEvent(lastUpdate.toISOString())}</Text>
                </HStack>
              )}
            </HStack>
          </HStack>

          {/* Actions */}
          <HStack spacing={2}>
            {error && (
              <Tooltip label={error}>
                <Alert status="error" size="sm" borderRadius="md">
                  <AlertIcon />
                </Alert>
              </Tooltip>
            )}
            
            <Tooltip label="Reconnect">
              <IconButton
                aria-label="Reconnect"
                icon={<FiRefreshCw />}
                size="sm"
                variant="outline"
                onClick={reconnect}
                isLoading={!isConnected}
              />
            </Tooltip>
          </HStack>
        </Flex>

        {/* Real-time Indicator */}
        {isConnected && (
          <Box mt={2}>
            <Flex align="center" gap={2}>
              <Box
                w={2}
                h={2}
                bg="green.500"
                borderRadius="full"
                animation="pulse 2s infinite"
              />
              <Text fontSize="xs" color="gray.600">
                Real-time updates active
              </Text>
            </Flex>
          </Box>
        )}
      </Box>

      {/* Dashboard Content */}
      <DashboardLayout role={role} projectId={projectId} />

      {/* Real-time Debug Panel (Development only) */}
      {process.env.NODE_ENV === 'development' && lastEvent && (
        <Box
          mt={4}
          p={3}
          bg="gray.50"
          borderRadius="md"
          border="1px"
          borderColor="gray.200"
        >
          <Text fontSize="sm" fontWeight="semibold" mb={2}>
            Last Event (Debug)
          </Text>
          <VStack align="start" spacing={1} fontSize="xs">
            <Text><strong>Type:</strong> {lastEvent.type}</Text>
            <Text><strong>Time:</strong> {lastEvent.timestamp}</Text>
            <Text><strong>Data:</strong> {JSON.stringify(lastEvent.data, null, 2)}</Text>
          </VStack>
        </Box>
      )}
    </Box>
  );
};

export default RealTimeDashboard;
