import React, { useState, useMemo } from 'react';
import {
  Modal,
  ModalOverlay,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalCloseButton,
  VStack,
  HStack,
  Text,
  Input,
  InputGroup,
  InputLeftElement,
  Box,
  SimpleGrid,
  Card,
  CardBody,
  CardHeader,
  Heading,
  Badge,
  Button,
  Icon,
  useColorModeValue,
  Tabs,
  TabList,
  TabPanels,
  Tab,
  TabPanel,
  Flex,
  Spacer,
  Tooltip,
  Divider,
  Image,
  useDisclosure
} from '@chakra-ui/react';
import { SearchIcon, AddIcon, InfoIcon } from '@chakra-ui/icons';
import { DashboardWidget } from '../../types/dashboard';

interface WidgetSelectorProps {
  isOpen: boolean;
  onClose: () => void;
  availableWidgets: DashboardWidget[];
  onAddWidget: (widgetId: string, config: any) => void;
}

const WidgetSelector: React.FC<WidgetSelectorProps> = ({
  isOpen,
  onClose,
  availableWidgets,
  onAddWidget
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('all');
  const [selectedWidget, setSelectedWidget] = useState<DashboardWidget | null>(null);
  const [widgetConfig, setWidgetConfig] = useState<any>({});

  const { isOpen: isConfigOpen, onOpen: onConfigOpen, onClose: onConfigClose } = useDisclosure();

  const bgColor = useColorModeValue('white', 'gray.800');
  const borderColor = useColorModeValue('gray.200', 'gray.600');
  const hoverBg = useColorModeValue('gray.50', 'gray.700');

  // Widget categories
  const categories = useMemo(() => [
    { id: 'all', name: 'All Widgets', icon: 'grid', color: 'gray' },
    { id: 'overview', name: 'Overview', icon: 'chart-bar', color: 'blue' },
    { id: 'tasks', name: 'Tasks', icon: 'check-circle', color: 'green' },
    { id: 'communication', name: 'Communication', icon: 'chat', color: 'purple' },
    { id: 'quality', name: 'Quality', icon: 'shield-check', color: 'orange' },
    { id: 'financial', name: 'Financial', icon: 'currency-dollar', color: 'teal' },
    { id: 'safety', name: 'Safety', icon: 'exclamation-triangle', color: 'red' },
    { id: 'system', name: 'System', icon: 'cog', color: 'gray' }
  ], []);

  // Filter widgets based on search and category
  const filteredWidgets = useMemo(() => {
    return availableWidgets.filter(widget => {
      const matchesSearch = widget.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                           widget.description.toLowerCase().includes(searchTerm.toLowerCase());
      const matchesCategory = selectedCategory === 'all' || widget.category === selectedCategory;
      return matchesSearch && matchesCategory;
    });
  }, [availableWidgets, searchTerm, selectedCategory]);

  // Group widgets by category
  const widgetsByCategory = useMemo(() => {
    const grouped: { [key: string]: DashboardWidget[] } = {};
    filteredWidgets.forEach(widget => {
      if (!grouped[widget.category]) {
        grouped[widget.category] = [];
      }
      grouped[widget.category].push(widget);
    });
    return grouped;
  }, [filteredWidgets]);

  const handleWidgetSelect = (widget: DashboardWidget) => {
    setSelectedWidget(widget);
    setWidgetConfig({
      title: widget.name,
      size: widget.default_size || 'medium',
      refresh_interval: 300,
      show_title: true,
      show_borders: true
    });
    onConfigOpen();
  };

  const handleAddWidget = () => {
    if (selectedWidget) {
      onAddWidget(selectedWidget.id, widgetConfig);
      onConfigClose();
      onClose();
      setSelectedWidget(null);
      setWidgetConfig({});
    }
  };

  const getCategoryIcon = (categoryId: string) => {
    const category = categories.find(cat => cat.id === categoryId);
    return category?.icon || 'widget';
  };

  const getCategoryColor = (categoryId: string) => {
    const category = categories.find(cat => cat.id === categoryId);
    return category?.color || 'gray';
  };

  return (
    <>
      <Modal isOpen={isOpen} onClose={onClose} size="6xl">
        <ModalOverlay />
        <ModalContent maxHeight="80vh">
          <ModalHeader>
            <Flex align="center">
              <Icon as={AddIcon} mr={2} />
              Add Widget to Dashboard
            </Flex>
          </ModalHeader>
          <ModalCloseButton />
          <ModalBody pb={6}>
            <VStack spacing={4} align="stretch">
              {/* Search and Filter */}
              <Box>
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
              </Box>

              {/* Category Tabs */}
              <Tabs value={selectedCategory} onChange={(index) => {
                const category = categories[index];
                setSelectedCategory(category?.id || 'all');
              }}>
                <TabList>
                  {categories.map((category) => (
                    <Tab key={category.id} fontSize="sm">
                      <HStack spacing={1}>
                        <Icon as={getCategoryIcon(category.id)} />
                        <Text>{category.name}</Text>
                        <Badge size="sm" colorScheme={category.color}>
                          {category.id === 'all' 
                            ? availableWidgets.length 
                            : widgetsByCategory[category.id]?.length || 0}
                        </Badge>
                      </HStack>
                    </Tab>
                  ))}
                </TabList>

                <TabPanels>
                  {categories.map((category) => (
                    <TabPanel key={category.id} p={0} pt={4}>
                      <SimpleGrid columns={{ base: 1, md: 2, lg: 3 }} spacing={4}>
                        {(category.id === 'all' ? filteredWidgets : widgetsByCategory[category.id] || []).map((widget) => (
                          <Card
                            key={widget.id}
                            bg={bgColor}
                            border="1px"
                            borderColor={borderColor}
                            cursor="pointer"
                            _hover={{ bg: hoverBg, borderColor: 'blue.300' }}
                            transition="all 0.2s"
                            onClick={() => handleWidgetSelect(widget)}
                          >
                            <CardHeader pb={2}>
                              <Flex align="center" justify="space-between">
                                <HStack spacing={2}>
                                  <Icon as={getCategoryIcon(widget.category)} color={`${getCategoryColor(widget.category)}.500`} />
                                  <Heading size="sm">{widget.name}</Heading>
                                </HStack>
                                <Badge size="sm" colorScheme={getCategoryColor(widget.category)}>
                                  {widget.type}
                                </Badge>
                              </Flex>
                            </CardHeader>

                            <CardBody pt={0}>
                              <Text fontSize="sm" color="gray.600" mb={3}>
                                {widget.description}
                              </Text>

                              <HStack spacing={2} mb={3}>
                                <Badge size="sm" variant="outline">
                                  {widget.default_size}
                                </Badge>
                                {widget.is_customizable && (
                                  <Badge size="sm" colorScheme="green">
                                    Customizable
                                  </Badge>
                                )}
                              </HStack>

                              <Button
                                size="sm"
                                colorScheme="blue"
                                leftIcon={<AddIcon />}
                                width="full"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  handleWidgetSelect(widget);
                                }}
                              >
                                Add Widget
                              </Button>
                            </CardBody>
                          </Card>
                        ))}
                      </SimpleGrid>

                      {category.id !== 'all' && (!widgetsByCategory[category.id] || widgetsByCategory[category.id].length === 0) && (
                        <Box textAlign="center" py={8}>
                          <Text color="gray.500">No widgets found in this category</Text>
                        </Box>
                      )}
                    </TabPanel>
                  ))}
                </TabPanels>
              </Tabs>
            </VStack>
          </ModalBody>
        </ModalContent>
      </Modal>

      {/* Widget Configuration Modal */}
      <Modal isOpen={isConfigOpen} onClose={onConfigClose}>
        <ModalOverlay />
        <ModalContent>
          <ModalHeader>
            <HStack>
              <Icon as={getCategoryIcon(selectedWidget?.category || '')} color={`${getCategoryColor(selectedWidget?.category || '')}.500`} />
              <Text>Configure {selectedWidget?.name}</Text>
            </HStack>
          </ModalHeader>
          <ModalCloseButton />
          <ModalBody pb={6}>
            <VStack spacing={4} align="stretch">
              {/* Widget Info */}
              <Box p={4} bg="gray.50" borderRadius="md">
                <Text fontSize="sm" color="gray.600" mb={2}>
                  {selectedWidget?.description}
                </Text>
                <HStack spacing={2}>
                  <Badge colorScheme={getCategoryColor(selectedWidget?.category || '')}>
                    {selectedWidget?.category}
                  </Badge>
                  <Badge variant="outline">
                    {selectedWidget?.type}
                  </Badge>
                </HStack>
              </Box>

              <Divider />

              {/* Configuration Options */}
              <VStack spacing={4} align="stretch">
                <Box>
                  <Text fontWeight="medium" mb={2}>Widget Title</Text>
                  <Input
                    value={widgetConfig.title || ''}
                    onChange={(e) => setWidgetConfig({ ...widgetConfig, title: e.target.value })}
                    placeholder="Enter widget title"
                  />
                </Box>

                <Box>
                  <Text fontWeight="medium" mb={2}>Widget Size</Text>
                  <SimpleGrid columns={2} spacing={2}>
                    {['small', 'medium', 'large', 'extra-large'].map((size) => (
                      <Button
                        key={size}
                        size="sm"
                        variant={widgetConfig.size === size ? 'solid' : 'outline'}
                        colorScheme={widgetConfig.size === size ? 'blue' : 'gray'}
                        onClick={() => setWidgetConfig({ ...widgetConfig, size })}
                      >
                        {size.charAt(0).toUpperCase() + size.slice(1)}
                      </Button>
                    ))}
                  </SimpleGrid>
                </Box>

                <Box>
                  <Text fontWeight="medium" mb={2}>Refresh Interval</Text>
                  <SimpleGrid columns={3} spacing={2}>
                    {[
                      { value: 30, label: '30s' },
                      { value: 60, label: '1m' },
                      { value: 300, label: '5m' },
                      { value: 900, label: '15m' },
                      { value: 1800, label: '30m' }
                    ].map((interval) => (
                      <Button
                        key={interval.value}
                        size="sm"
                        variant={widgetConfig.refresh_interval === interval.value ? 'solid' : 'outline'}
                        colorScheme={widgetConfig.refresh_interval === interval.value ? 'blue' : 'gray'}
                        onClick={() => setWidgetConfig({ ...widgetConfig, refresh_interval: interval.value })}
                      >
                        {interval.label}
                      </Button>
                    ))}
                  </SimpleGrid>
                </Box>

                <Box>
                  <HStack justify="space-between">
                    <Text fontWeight="medium">Show Title</Text>
                    <Button
                      size="sm"
                      variant={widgetConfig.show_title ? 'solid' : 'outline'}
                      colorScheme={widgetConfig.show_title ? 'blue' : 'gray'}
                      onClick={() => setWidgetConfig({ ...widgetConfig, show_title: !widgetConfig.show_title })}
                    >
                      {widgetConfig.show_title ? 'Yes' : 'No'}
                    </Button>
                  </HStack>
                </Box>

                <Box>
                  <HStack justify="space-between">
                    <Text fontWeight="medium">Show Borders</Text>
                    <Button
                      size="sm"
                      variant={widgetConfig.show_borders ? 'solid' : 'outline'}
                      colorScheme={widgetConfig.show_borders ? 'blue' : 'gray'}
                      onClick={() => setWidgetConfig({ ...widgetConfig, show_borders: !widgetConfig.show_borders })}
                    >
                      {widgetConfig.show_borders ? 'Yes' : 'No'}
                    </Button>
                  </HStack>
                </Box>
              </VStack>
            </VStack>
          </ModalBody>

          <ModalFooter>
            <Button variant="ghost" mr={3} onClick={onConfigClose}>
              Cancel
            </Button>
            <Button colorScheme="blue" onClick={handleAddWidget}>
              Add Widget
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
};

export default WidgetSelector;
