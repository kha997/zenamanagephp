import React from 'react';
import {
  Box,
  Text,
  Select,
  Flex,
  Spinner,
  Alert,
  AlertIcon
} from '@chakra-ui/react';
import {
  LineChart,
  Line,
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip as RechartsTooltip,
  Legend,
  ResponsiveContainer
} from 'recharts';

interface WidgetChartProps {
  data: any;
  config: Record<string, any>;
}

const WidgetChart: React.FC<WidgetChartProps> = ({ data, config }) => {
  const displayConfig = config.display || {};
  const chartType = displayConfig.chart_type || 'line';
  const title = displayConfig.title || 'Chart';
  const xAxis = displayConfig.x_axis || 'x';
  const yAxis = displayConfig.y_axis || 'y';

  const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8'];

  const formatTooltipValue = (value: any, name: string) => {
    if (typeof value === 'number') {
      if (name.toLowerCase().includes('percent') || name.toLowerCase().includes('%')) {
        return `${value.toFixed(1)}%`;
      }
      if (name.toLowerCase().includes('budget') || name.toLowerCase().includes('cost')) {
        return `$${value.toLocaleString()}`;
      }
      return value.toLocaleString();
    }
    return value;
  };

  const renderLineChart = () => {
    if (!data.datasets || !data.labels) {
      return <Alert status="error"><AlertIcon />Invalid chart data</Alert>;
    }

    const chartData = data.labels.map((label: string, index: number) => {
      const dataPoint: any = { [xAxis]: label };
      data.datasets.forEach((dataset: any, datasetIndex: number) => {
        dataPoint[dataset.label] = dataset.data[index];
      });
      return dataPoint;
    });

    return (
      <ResponsiveContainer width="100%" height={300}>
        <LineChart data={chartData}>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis dataKey={xAxis} />
          <YAxis />
          <RechartsTooltip formatter={formatTooltipValue} />
          <Legend />
          {data.datasets.map((dataset: any, index: number) => (
            <Line
              key={dataset.label}
              type="monotone"
              dataKey={dataset.label}
              stroke={dataset.color || COLORS[index % COLORS.length]}
              strokeWidth={2}
              dot={{ r: 4 }}
              activeDot={{ r: 6 }}
            />
          ))}
        </LineChart>
      </ResponsiveContainer>
    );
  };

  const renderBarChart = () => {
    if (!data.datasets || !data.labels) {
      return <Alert status="error"><AlertIcon />Invalid chart data</Alert>;
    }

    const chartData = data.labels.map((label: string, index: number) => {
      const dataPoint: any = { [xAxis]: label };
      data.datasets.forEach((dataset: any, datasetIndex: number) => {
        dataPoint[dataset.label] = dataset.data[index];
      });
      return dataPoint;
    });

    return (
      <ResponsiveContainer width="100%" height={300}>
        <BarChart data={chartData}>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis dataKey={xAxis} />
          <YAxis />
          <RechartsTooltip formatter={formatTooltipValue} />
          <Legend />
          {data.datasets.map((dataset: any, index: number) => (
            <Bar
              key={dataset.label}
              dataKey={dataset.label}
              fill={dataset.color || COLORS[index % COLORS.length]}
            />
          ))}
        </BarChart>
      </ResponsiveContainer>
    );
  };

  const renderPieChart = () => {
    if (!data.datasets || !data.datasets[0]) {
      return <Alert status="error"><AlertIcon />Invalid pie chart data</Alert>;
    }

    const pieData = data.datasets[0].data.map((value: number, index: number) => ({
      name: data.labels[index],
      value: value,
      color: COLORS[index % COLORS.length]
    }));

    return (
      <ResponsiveContainer width="100%" height={300}>
        <PieChart>
          <Pie
            data={pieData}
            cx="50%"
            cy="50%"
            labelLine={false}
            label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
            outerRadius={80}
            fill="#8884d8"
            dataKey="value"
          >
            {pieData.map((entry, index) => (
              <Cell key={`cell-${index}`} fill={entry.color} />
            ))}
          </Pie>
          <RechartsTooltip formatter={formatTooltipValue} />
        </PieChart>
      </ResponsiveContainer>
    );
  };

  const renderChart = () => {
    switch (chartType) {
      case 'line':
        return renderLineChart();
      case 'bar':
        return renderBarChart();
      case 'pie':
        return renderPieChart();
      default:
        return renderLineChart();
    }
  };

  const renderSimpleData = () => {
    // Handle simple data format
    if (Array.isArray(data)) {
      return (
        <ResponsiveContainer width="100%" height={300}>
          <LineChart data={data}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey={xAxis} />
            <YAxis />
            <RechartsTooltip formatter={formatTooltipValue} />
            <Legend />
            <Line
              type="monotone"
              dataKey={yAxis}
              stroke="#8884d8"
              strokeWidth={2}
              dot={{ r: 4 }}
              activeDot={{ r: 6 }}
            />
          </LineChart>
        </ResponsiveContainer>
      );
    }

    return <Alert status="error"><AlertIcon />Invalid chart data format</Alert>;
  };

  if (!data) {
    return (
      <Box textAlign="center" py={8}>
        <Text color="gray.500">No chart data available</Text>
      </Box>
    );
  }

  return (
    <Box>
      <Flex justify="space-between" align="center" mb={4}>
        <Text fontSize="lg" fontWeight="semibold" color="gray.700">
          {title}
        </Text>
        {displayConfig.show_controls && (
          <Select size="sm" width="120px" defaultValue={chartType}>
            <option value="line">Line</option>
            <option value="bar">Bar</option>
            <option value="pie">Pie</option>
          </Select>
        )}
      </Flex>
      
      <Box height="300px">
        {data.datasets ? renderChart() : renderSimpleData()}
      </Box>
      
      {displayConfig.show_summary && data.summary && (
        <Box mt={4} p={3} bg="gray.50" borderRadius="md">
          <Text fontSize="sm" color="gray.600">
            {data.summary}
          </Text>
        </Box>
      )}
    </Box>
  );
};

export default WidgetChart;
