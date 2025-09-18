import React from 'react';
import {
  Box,
  Text,
  Flex,
  Stat,
  StatLabel,
  StatNumber,
  StatHelpText,
  StatArrow,
  Progress,
  CircularProgress,
  CircularProgressLabel,
  Badge,
  Tooltip,
  Icon
} from '@chakra-ui/react';
import {
  FiTrendingUp,
  FiTrendingDown,
  FiMinus,
  FiTarget,
  FiDollarSign,
  FiClock,
  FiUsers,
  FiCheckCircle
} from 'react-icons/fi';

interface WidgetMetricProps {
  data: any;
  config: Record<string, any>;
}

const WidgetMetric: React.FC<WidgetMetricProps> = ({ data, config }) => {
  const displayConfig = config.display || {};
  const format = displayConfig.format || 'number';
  const unit = data.unit || '';
  const thresholds = displayConfig.thresholds || {};
  const showTrend = displayConfig.show_trend !== false;
  const showProgress = displayConfig.show_progress !== false;

  const getIcon = (iconName: string) => {
    const icons: Record<string, any> = {
      target: FiTarget,
      dollar: FiDollarSign,
      clock: FiClock,
      users: FiUsers,
      check: FiCheckCircle
    };
    return icons[iconName] || FiTarget;
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

  const getStatusColor = (value: number) => {
    if (thresholds.critical && value <= thresholds.critical) {
      return 'red';
    }
    if (thresholds.warning && value <= thresholds.warning) {
      return 'orange';
    }
    return 'green';
  };

  const formatValue = (value: number, format: string, unit: string) => {
    switch (format) {
      case 'percentage':
        return `${value.toFixed(1)}%`;
      case 'currency':
        return `$${value.toLocaleString()}`;
      case 'decimal':
        return value.toFixed(2);
      case 'integer':
        return value.toLocaleString();
      default:
        return unit ? `${value} ${unit}` : value.toString();
    }
  };

  const getProgressValue = (value: number, max: number = 100) => {
    return Math.min((value / max) * 100, 100);
  };

  const renderCircularProgress = () => {
    const progressValue = getProgressValue(data.value, data.max || 100);
    const colorScheme = getStatusColor(data.value);

    return (
      <Flex direction="column" align="center" gap={4}>
        <CircularProgress
          value={progressValue}
          size="120px"
          color={`${colorScheme}.500`}
          trackColor="gray.200"
        >
          <CircularProgressLabel>
            <Text fontSize="lg" fontWeight="bold">
              {formatValue(data.value, format, unit)}
            </Text>
          </CircularProgressLabel>
        </CircularProgress>
        
        <Box textAlign="center">
          <Text fontSize="sm" color="gray.600" mb={1}>
            {displayConfig.title || 'Metric'}
          </Text>
          {data.target && (
            <Text fontSize="xs" color="gray.500">
              Target: {formatValue(data.target, format, unit)}
            </Text>
          )}
        </Box>
      </Flex>
    );
  };

  const renderLinearProgress = () => {
    const progressValue = getProgressValue(data.value, data.max || 100);
    const colorScheme = getStatusColor(data.value);

    return (
      <Box>
        <Flex justify="space-between" align="center" mb={2}>
          <Text fontSize="sm" color="gray.600">
            {displayConfig.title || 'Progress'}
          </Text>
          <Text fontSize="sm" fontWeight="semibold" color={`${colorScheme}.600`}>
            {formatValue(data.value, format, unit)}
          </Text>
        </Flex>
        
        <Progress
          value={progressValue}
          colorScheme={colorScheme}
          size="lg"
          borderRadius="full"
        />
        
        {data.target && (
          <Flex justify="space-between" mt={2}>
            <Text fontSize="xs" color="gray.500">
              Current: {formatValue(data.value, format, unit)}
            </Text>
            <Text fontSize="xs" color="gray.500">
              Target: {formatValue(data.target, format, unit)}
            </Text>
          </Flex>
        )}
      </Box>
    );
  };

  const renderStatCard = () => {
    const colorScheme = getStatusColor(data.value);
    const iconName = displayConfig.icon || 'target';

    return (
      <Stat textAlign="center">
        <Flex align="center" justify="center" gap={2} mb={2}>
          <Icon as={getIcon(iconName)} color={`${colorScheme}.500`} boxSize={5} />
          <StatLabel fontSize="sm" color="gray.600">
            {displayConfig.title || 'Metric'}
          </StatLabel>
        </Flex>
        
        <StatNumber fontSize="3xl" color={`${colorScheme}.600`} mb={2}>
          {formatValue(data.value, format, unit)}
        </StatNumber>
        
        {showTrend && data.trend && (
          <StatHelpText>
            {getTrendIcon(data.trend)}
            {data.change && `${Math.abs(data.change)}% from last period`}
          </StatHelpText>
        )}
        
        {data.target && (
          <Box mt={2}>
            <Badge colorScheme={colorScheme} variant="subtle">
              Target: {formatValue(data.target, format, unit)}
            </Badge>
          </Box>
        )}
      </Stat>
    );
  };

  const renderGauge = () => {
    const progressValue = getProgressValue(data.value, data.max || 100);
    const colorScheme = getStatusColor(data.value);

    return (
      <Box>
        <Flex justify="center" mb={4}>
          <Box position="relative">
            <CircularProgress
              value={progressValue}
              size="100px"
              color={`${colorScheme}.500`}
              trackColor="gray.200"
              thickness="8px"
            />
            <Box
              position="absolute"
              top="50%"
              left="50%"
              transform="translate(-50%, -50%)"
              textAlign="center"
            >
              <Text fontSize="lg" fontWeight="bold" color={`${colorScheme}.600`}>
                {formatValue(data.value, format, unit)}
              </Text>
            </Box>
          </Box>
        </Flex>
        
        <Box textAlign="center">
          <Text fontSize="sm" color="gray.600" mb={1}>
            {displayConfig.title || 'Metric'}
          </Text>
          {data.target && (
            <Text fontSize="xs" color="gray.500">
              Target: {formatValue(data.target, format, unit)}
            </Text>
          )}
        </Box>
      </Box>
    );
  };

  const renderComparison = () => {
    const colorScheme = getStatusColor(data.value);
    const variance = data.target ? ((data.value - data.target) / data.target) * 100 : 0;

    return (
      <Box>
        <Flex justify="space-between" align="center" mb={4}>
          <Text fontSize="sm" color="gray.600">
            {displayConfig.title || 'Metric'}
          </Text>
          <Badge colorScheme={colorScheme} variant="subtle">
            {variance > 0 ? '+' : ''}{variance.toFixed(1)}%
          </Badge>
        </Flex>
        
        <Flex justify="space-between" align="center" mb={2}>
          <Box>
            <Text fontSize="2xl" fontWeight="bold" color={`${colorScheme}.600`}>
              {formatValue(data.value, format, unit)}
            </Text>
            <Text fontSize="xs" color="gray.500">Current</Text>
          </Box>
          
          {data.target && (
            <Box textAlign="right">
              <Text fontSize="lg" color="gray.600">
                {formatValue(data.target, format, unit)}
              </Text>
              <Text fontSize="xs" color="gray.500">Target</Text>
            </Box>
          )}
        </Flex>
        
        {showProgress && data.max && (
          <Progress
            value={getProgressValue(data.value, data.max)}
            colorScheme={colorScheme}
            size="sm"
            borderRadius="full"
          />
        )}
      </Box>
    );
  };

  if (!data || data.value === undefined) {
    return (
      <Box textAlign="center" py={8}>
        <Text color="gray.500">No metric data available</Text>
      </Box>
    );
  }

  const layout = displayConfig.layout || 'stat';

  switch (layout) {
    case 'circular':
      return renderCircularProgress();
    case 'linear':
      return renderLinearProgress();
    case 'gauge':
      return renderGauge();
    case 'comparison':
      return renderComparison();
    default:
      return renderStatCard();
  }
};

export default WidgetMetric;
