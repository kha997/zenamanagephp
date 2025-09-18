import React, { useState } from 'react';
import {
  Modal,
  ModalOverlay,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalCloseButton,
  Box,
  Text,
  Flex,
  Input,
  InputGroup,
  InputLeftElement,
  SimpleGrid,
  Badge,
  Button,
  Icon,
  Tooltip,
  Divider,
  VStack,
  HStack
} from '@chakra-ui/react';
import {
  SearchIcon,
  FiGrid,
  FiBarChart3,
  FiTable,
  FiTarget,
  FiAlertTriangle,
  FiInfo
} from '@chakra-ui/icons';

interface WidgetSelectorProps {
  isOpen: boolean;
  onClose: () => void;
  onSelectWidget: (widgetId: string) => void;
  availableWidgets: any[];
  role: string;
}

interface Widget {
  id: string;
  name: string;
  type: string;
  category: string;
  description: string;
  display_config: Record<string, any>;
}

const WidgetSelector: React.FC<WidgetSelectorProps> = ({
  isOpen,
  onClose,
  onSelectWidget,
  availableWidgets,
  role
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('all');

  const getWidgetIcon = (type: string) => {
    const icons = {
      card: FiGrid,
      chart: FiBarChart3,
      table: FiTable,
      metric: FiTarget,
      alert: FiAlertTriangle
    };
    return icons[type as keyof typeof icons] || FiInfo;
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

  const getTypeColor = (type: string) => {
    const colors = {
      card: 'blue',
      chart: 'green',
      table: 'purple',
      metric: 'orange',
      alert: 'red'
    };
    return colors[type as keyof typeof colors] || 'gray';
  };

  const filteredWidgets = availableWidgets.filter((widget: Widget) => {
    const matchesSearch = widget.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         widget.description.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesCategory = selectedCategory === 'all' || widget.category === selectedCategory;
    return matchesSearch && matchesCategory;
  });

  const categories = ['all', ...new Set(availableWidgets.map((w: Widget) => w.category))];
  const types = [...new Set(availableWidgets.map((w: Widget) => w.type))];

  const handleSelectWidget = (widgetId: string) => {
    onSelectWidget(widgetId);
    onClose();
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="4xl">
      <ModalOverlay />
      <ModalContent maxH="80vh">
        <ModalHeader>
          <VStack align="start" spacing={2}>
            <Text fontSize="xl" fontWeight="bold">
              Add Widget to Dashboard
            </Text>
            <Text fontSize="sm" color="gray.600">
              Choose from available widgets for {role.replace('_', ' ')} role
            </Text>
          </VStack>
        </ModalHeader>
        <ModalCloseButton />
        
        <ModalBody pb={6}>
          {/* Search and Filters */}
          <VStack spacing={4} mb={6}>
            <InputGroup>
              <InputLeftElement pointerEvents="none">
                <SearchIcon color="gray.300" />
              </InputLeftElement>
              <Input
                placeholder="Search widgets..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </InputGroup>
            
            <Flex gap={2} flexWrap="wrap" justify="center">
              {categories.map((category) => (
                <Button
                  key={category}
                  size="sm"
                  variant={selectedCategory === category ? 'solid' : 'outline'}
                  colorScheme={category === 'all' ? 'gray' : getCategoryColor(category)}
                  onClick={() => setSelectedCategory(category)}
                  textTransform="capitalize"
                >
                  {category}
                </Button>
              ))}
            </Flex>
          </VStack>

          {/* Widget Types Legend */}
          <Box mb={4} p={3} bg="gray.50" borderRadius="md">
            <Text fontSize="sm" fontWeight="semibold" mb={2} color="gray.700">
              Widget Types:
            </Text>
            <HStack spacing={4} flexWrap="wrap">
              {types.map((type) => (
                <HStack key={type} spacing={1}>
                  <Icon as={getWidgetIcon(type)} color={`${getTypeColor(type)}.500`} />
                  <Text fontSize="xs" color="gray.600" textTransform="capitalize">
                    {type}
                  </Text>
                </HStack>
              ))}
            </HStack>
          </Box>

          {/* Widgets Grid */}
          {filteredWidgets.length === 0 ? (
            <Box textAlign="center" py={8}>
              <Text color="gray.500">No widgets found matching your criteria</Text>
            </Box>
          ) : (
            <SimpleGrid columns={{ base: 1, md: 2, lg: 3 }} spacing={4}>
              {filteredWidgets.map((widget: Widget) => (
                <Box
                  key={widget.id}
                  border="1px"
                  borderColor="gray.200"
                  borderRadius="md"
                  p={4}
                  cursor="pointer"
                  transition="all 0.2s"
                  _hover={{
                    borderColor: 'blue.300',
                    shadow: 'md',
                    transform: 'translateY(-2px)'
                  }}
                  onClick={() => handleSelectWidget(widget.id)}
                >
                  <VStack align="start" spacing={3}>
                    {/* Widget Header */}
                    <Flex justify="space-between" align="start" w="full">
                      <HStack spacing={2}>
                        <Icon
                          as={getWidgetIcon(widget.type)}
                          color={`${getTypeColor(widget.type)}.500`}
                          boxSize={5}
                        />
                        <Text fontSize="sm" fontWeight="semibold" color="gray.700">
                          {widget.name}
                        </Text>
                      </HStack>
                      
                      <Badge
                        size="sm"
                        colorScheme={getCategoryColor(widget.category)}
                        variant="subtle"
                        textTransform="capitalize"
                      >
                        {widget.category}
                      </Badge>
                    </Flex>

                    {/* Widget Description */}
                    <Text fontSize="xs" color="gray.600" lineHeight="short">
                      {widget.description}
                    </Text>

                    {/* Widget Type Badge */}
                    <Badge
                      size="sm"
                      colorScheme={getTypeColor(widget.type)}
                      variant="outline"
                      textTransform="capitalize"
                    >
                      {widget.type}
                    </Badge>

                    {/* Add Button */}
                    <Button
                      size="sm"
                      colorScheme="blue"
                      variant="outline"
                      w="full"
                      onClick={(e) => {
                        e.stopPropagation();
                        handleSelectWidget(widget.id);
                      }}
                    >
                      Add to Dashboard
                    </Button>
                  </VStack>
                </Box>
              ))}
            </SimpleGrid>
          )}

          {/* Footer Info */}
          <Divider my={4} />
          <Box textAlign="center">
            <Text fontSize="xs" color="gray.500">
              Widgets are automatically configured based on your role and permissions.
              You can customize them after adding to your dashboard.
            </Text>
          </Box>
        </ModalBody>
      </ModalContent>
    </Modal>
  );
};

export default WidgetSelector;
