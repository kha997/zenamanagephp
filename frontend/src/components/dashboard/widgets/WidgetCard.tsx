import React from 'react';
import {
  Box,
  Flex,
  Text,
  Stat,
  StatLabel,
  StatNumber,
  StatHelpText,
  StatArrow,
  Icon,
  SimpleGrid,
  Badge,
  Tooltip
} from '@chakra-ui/react';
import {
  FiTrendingUp,
  FiTrendingDown,
  FiMinus,
  FiServer,
  FiUsers,
  FiFolder,
  FiDollarSign,
  FiClock,
  FiCheckCircle,
  FiAlertCircle,
  FiXCircle
} from 'react-icons/fi';

interface WidgetCardProps {
  data: any;
  config: Record<string, any>;
}

const WidgetCard: React.FC<WidgetCardProps> = ({ data, config }) => {
  const getIcon = (iconName: string) => {
    const icons: Record<string, any> = {
      server: FiServer,
      users: FiUsers,
      folder: FiFolder,
      dollar: FiDollarSign,
      clock: FiClock,
      check: FiCheckCircle,
      alert: FiAlertCircle,
      x: FiXCircle
    };
    return icons[iconName] || FiServer;
  };

  const getTrendIcon = (trend: string) => {
    switch (trend) {
      case 'up':
        return <StatArrow type="increase" />;
      case 'down':
        return <StatArrow type="decrease" />;
      default:
        return <Icon as={FiMinus} color="gray.400" />;
    }
  };

  const getStatusColor = (status: string) => {
    const colors = {
      on_track: 'green',
      at_risk: 'yellow',
      delayed: 'red',
      completed: 'green',
      in_progress: 'blue',
      pending: 'orange',
      sunny: 'yellow',
      cloudy: 'gray',
      rainy: 'blue',
      stormy: 'red'
    };
    return colors[status as keyof typeof colors] || 'gray';
  };

  const formatValue = (value: any, unit?: string) => {
    if (typeof value === 'number') {
      if (unit === '%') {
        return `${value.toFixed(1)}%`;
      }
      if (unit === '$') {
        return `$${value.toLocaleString()}`;
      }
      return value.toLocaleString();
    }
    return value;
  };

  const renderSingleValue = () => {
    const displayConfig = config.display || {};
    const iconName = displayConfig.icon || 'server';
    const color = displayConfig.color || 'blue';
    const title = displayConfig.title || 'Value';

    return (
      <Stat textAlign="center">
        <StatLabel fontSize="sm" color="gray.600">
          {title}
        </StatLabel>
        <Flex align="center" justify="center" gap={2} mt={2}>
          <Icon as={getIcon(iconName)} color={`${color}.500`} boxSize={6} />
          <StatNumber fontSize="2xl" color={`${color}.600`}>
            {formatValue(data.value, data.unit)}
          </StatNumber>
        </Flex>
        {data.trend && (
          <StatHelpText>
            {getTrendIcon(data.trend)}
            {data.change && `${Math.abs(data.change)}% from last period`}
          </StatHelpText>
        )}
      </Stat>
    );
  };

  const renderMultipleValues = () => {
    const items = Object.entries(data).map(([key, value]: [string, any]) => {
      const isStatus = typeof value === 'string' && ['on_track', 'at_risk', 'delayed', 'sunny', 'cloudy', 'rainy'].includes(value);
      
      return (
        <Box key={key} textAlign="center">
          <Text fontSize="xs" color="gray.500" textTransform="uppercase" letterSpacing="wide">
            {key.replace(/_/g, ' ')}
          </Text>
          {isStatus ? (
            <Badge
              colorScheme={getStatusColor(value)}
              variant="subtle"
              mt={1}
              fontSize="xs"
            >
              {value.replace(/_/g, ' ')}
            </Badge>
          ) : (
            <Text fontSize="lg" fontWeight="bold" color="gray.700" mt={1}>
              {formatValue(value)}
            </Text>
          )}
        </Box>
      );
    });

    return (
      <SimpleGrid columns={2} spacing={4}>
        {items}
      </SimpleGrid>
    );
  };

  const renderKeyValuePairs = () => {
    const pairs = Object.entries(data).map(([key, value]: [string, any]) => (
      <Flex key={key} justify="space-between" align="center" py={1}>
        <Text fontSize="sm" color="gray.600" textTransform="capitalize">
          {key.replace(/_/g, ' ')}:
        </Text>
        <Text fontSize="sm" fontWeight="semibold" color="gray.800">
          {formatValue(value)}
        </Text>
      </Flex>
    ));

    return <Box>{pairs}</Box>;
  };

  const renderCustomLayout = () => {
    const displayConfig = config.display || {};
    const layout = displayConfig.layout || 'grid';
    
    if (layout === 'list') {
      return renderKeyValuePairs();
    }
    
    if (layout === 'grid') {
      return renderMultipleValues();
    }
    
    return renderSingleValue();
  };

  if (!data) {
    return (
      <Box textAlign="center" py={8}>
        <Text color="gray.500">No data available</Text>
      </Box>
    );
  }

  // Determine layout based on data structure
  const dataKeys = Object.keys(data);
  
  if (dataKeys.length === 1 && dataKeys[0] === 'value') {
    return renderSingleValue();
  }
  
  if (dataKeys.length <= 4) {
    return renderCustomLayout();
  }
  
  return renderKeyValuePairs();
};

export default WidgetCard;
