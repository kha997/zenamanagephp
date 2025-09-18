import React, { useState, useEffect } from 'react';
import {
  Modal,
  ModalOverlay,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  ModalCloseButton,
  VStack,
  HStack,
  Text,
  Input,
  Select,
  Button,
  Switch,
  NumberInput,
  NumberInputField,
  NumberInputStepper,
  NumberIncrementStepper,
  NumberDecrementStepper,
  FormControl,
  FormLabel,
  FormHelperText,
  SimpleGrid,
  Box,
  Divider,
  Badge,
  Icon,
  useColorModeValue,
  Tabs,
  TabList,
  TabPanels,
  Tab,
  TabPanel,
  Alert,
  AlertIcon,
  AlertTitle,
  AlertDescription,
  useDisclosure
} from '@chakra-ui/react';
import { EditIcon, SaveIcon, ResetIcon, CopyIcon, DeleteIcon } from '@chakra-ui/icons';
import { WidgetInstance } from '../../types/dashboard';

interface WidgetConfigModalProps {
  isOpen: boolean;
  onClose: () => void;
  widget: WidgetInstance | null;
  onUpdateConfig: (widgetInstanceId: string, config: any) => void;
}

const WidgetConfigModal: React.FC<WidgetConfigModalProps> = ({
  isOpen,
  onClose,
  widget,
  onUpdateConfig
}) => {
  const [config, setConfig] = useState<any>({});
  const [originalConfig, setOriginalConfig] = useState<any>({});
  const [isSaving, setIsSaving] = useState(false);
  const [hasChanges, setHasChanges] = useState(false);

  const { isOpen: isResetOpen, onOpen: onResetOpen, onClose: onResetClose } = useDisclosure();

  const bgColor = useColorModeValue('white', 'gray.800');
  const borderColor = useColorModeValue('gray.200', 'gray.600');

  // Initialize config when widget changes
  useEffect(() => {
    if (widget) {
      const initialConfig = {
        title: widget.title || '',
        size: widget.size || 'medium',
        refresh_interval: widget.config?.refresh_interval || 300,
        show_title: widget.config?.show_title !== false,
        show_borders: widget.config?.show_borders !== false,
        enable_animations: widget.config?.enable_animations !== false,
        compact_mode: widget.config?.compact_mode || false,
        auto_refresh: widget.config?.auto_refresh !== false,
        ...widget.config
      };
      
      setConfig(initialConfig);
      setOriginalConfig(initialConfig);
      setHasChanges(false);
    }
  }, [widget]);

  // Check for changes
  useEffect(() => {
    if (widget) {
      const changed = JSON.stringify(config) !== JSON.stringify(originalConfig);
      setHasChanges(changed);
    }
  }, [config, originalConfig, widget]);

  const handleSave = async () => {
    if (!widget || !hasChanges) return;

    setIsSaving(true);
    try {
      await onUpdateConfig(widget.id, config);
      setOriginalConfig(config);
      setHasChanges(false);
      onClose();
    } catch (error) {
      console.error('Failed to update widget config:', error);
    } finally {
      setIsSaving(false);
    }
  };

  const handleReset = () => {
    if (widget) {
      setConfig(originalConfig);
      setHasChanges(false);
    }
  };

  const handleResetToDefault = () => {
    if (widget) {
      const defaultConfig = {
        title: widget.title || '',
        size: 'medium',
        refresh_interval: 300,
        show_title: true,
        show_borders: true,
        enable_animations: true,
        compact_mode: false,
        auto_refresh: true
      };
      
      setConfig(defaultConfig);
      setHasChanges(true);
      onResetClose();
    }
  };

  const updateConfig = (key: string, value: any) => {
    setConfig(prev => ({ ...prev, [key]: value }));
  };

  const getSizeDescription = (size: string) => {
    const descriptions: { [key: string]: string } = {
      'small': 'Compact view, good for quick metrics',
      'medium': 'Standard size, balanced information',
      'large': 'Detailed view with more data',
      'extra-large': 'Full-width view, maximum information'
    };
    return descriptions[size] || 'Standard size';
  };

  const getRefreshIntervalDescription = (interval: number) => {
    const descriptions: { [key: number]: string } = {
      30: 'Very frequent updates (high server load)',
      60: 'Frequent updates (good for real-time data)',
      300: 'Standard updates (recommended)',
      900: 'Less frequent updates (saves resources)',
      1800: 'Infrequent updates (minimal server load)'
    };
    return descriptions[interval] || 'Standard updates';
  };

  if (!widget) return null;

  return (
    <>
      <Modal isOpen={isOpen} onClose={onClose} size="2xl">
        <ModalOverlay />
        <ModalContent maxHeight="80vh">
          <ModalHeader>
            <HStack>
              <Icon as={EditIcon} />
              <Text>Configure Widget: {widget.title}</Text>
            </HStack>
          </ModalHeader>
          <ModalCloseButton />
          <ModalBody pb={6}>
            <VStack spacing={6} align="stretch">
              {/* Widget Info */}
              <Box p={4} bg="gray.50" borderRadius="md">
                <HStack spacing={2} mb={2}>
                  <Badge colorScheme="blue">{widget.type}</Badge>
                  <Badge variant="outline">{widget.size}</Badge>
                  {widget.is_customizable && (
                    <Badge colorScheme="green">Customizable</Badge>
                  )}
                </HStack>
                <Text fontSize="sm" color="gray.600">
                  Widget ID: {widget.id}
                </Text>
              </Box>

              {/* Configuration Tabs */}
              <Tabs>
                <TabList>
                  <Tab>General</Tab>
                  <Tab>Display</Tab>
                  <Tab>Behavior</Tab>
                  <Tab>Advanced</Tab>
                </TabList>

                <TabPanels>
                  {/* General Tab */}
                  <TabPanel p={0} pt={4}>
                    <VStack spacing={4} align="stretch">
                      <FormControl>
                        <FormLabel>Widget Title</FormLabel>
                        <Input
                          value={config.title || ''}
                          onChange={(e) => updateConfig('title', e.target.value)}
                          placeholder="Enter widget title"
                        />
                        <FormHelperText>
                          This title will be displayed at the top of the widget
                        </FormHelperText>
                      </FormControl>

                      <FormControl>
                        <FormLabel>Widget Size</FormLabel>
                        <SimpleGrid columns={2} spacing={2}>
                          {['small', 'medium', 'large', 'extra-large'].map((size) => (
                            <Button
                              key={size}
                              size="sm"
                              variant={config.size === size ? 'solid' : 'outline'}
                              colorScheme={config.size === size ? 'blue' : 'gray'}
                              onClick={() => updateConfig('size', size)}
                            >
                              {size.charAt(0).toUpperCase() + size.slice(1)}
                            </Button>
                          ))}
                        </SimpleGrid>
                        <FormHelperText>
                          {getSizeDescription(config.size)}
                        </FormHelperText>
                      </FormControl>

                      <FormControl>
                        <FormLabel>Refresh Interval</FormLabel>
                        <Select
                          value={config.refresh_interval || 300}
                          onChange={(e) => updateConfig('refresh_interval', parseInt(e.target.value))}
                        >
                          <option value={30}>30 seconds</option>
                          <option value={60}>1 minute</option>
                          <option value={300}>5 minutes</option>
                          <option value={900}>15 minutes</option>
                          <option value={1800}>30 minutes</option>
                        </Select>
                        <FormHelperText>
                          {getRefreshIntervalDescription(config.refresh_interval)}
                        </FormHelperText>
                      </FormControl>
                    </VStack>
                  </TabPanel>

                  {/* Display Tab */}
                  <TabPanel p={0} pt={4}>
                    <VStack spacing={4} align="stretch">
                      <FormControl display="flex" alignItems="center" justifyContent="space-between">
                        <Box>
                          <FormLabel mb={0}>Show Title</FormLabel>
                          <FormHelperText>Display the widget title at the top</FormHelperText>
                        </Box>
                        <Switch
                          isChecked={config.show_title !== false}
                          onChange={(e) => updateConfig('show_title', e.target.checked)}
                        />
                      </FormControl>

                      <FormControl display="flex" alignItems="center" justifyContent="space-between">
                        <Box>
                          <FormLabel mb={0}>Show Borders</FormLabel>
                          <FormHelperText>Display borders around the widget</FormHelperText>
                        </Box>
                        <Switch
                          isChecked={config.show_borders !== false}
                          onChange={(e) => updateConfig('show_borders', e.target.checked)}
                        />
                      </FormControl>

                      <FormControl display="flex" alignItems="center" justifyContent="space-between">
                        <Box>
                          <FormLabel mb={0}>Enable Animations</FormLabel>
                          <FormHelperText>Enable smooth transitions and animations</FormHelperText>
                        </Box>
                        <Switch
                          isChecked={config.enable_animations !== false}
                          onChange={(e) => updateConfig('enable_animations', e.target.checked)}
                        />
                      </FormControl>

                      <FormControl display="flex" alignItems="center" justifyContent="space-between">
                        <Box>
                          <FormLabel mb={0}>Compact Mode</FormLabel>
                          <FormHelperText>Use compact spacing and smaller fonts</FormHelperText>
                        </Box>
                        <Switch
                          isChecked={config.compact_mode || false}
                          onChange={(e) => updateConfig('compact_mode', e.target.checked)}
                        />
                      </FormControl>
                    </VStack>
                  </TabPanel>

                  {/* Behavior Tab */}
                  <TabPanel p={0} pt={4}>
                    <VStack spacing={4} align="stretch">
                      <FormControl display="flex" alignItems="center" justifyContent="space-between">
                        <Box>
                          <FormLabel mb={0}>Auto Refresh</FormLabel>
                          <FormHelperText>Automatically refresh widget data</FormHelperText>
                        </Box>
                        <Switch
                          isChecked={config.auto_refresh !== false}
                          onChange={(e) => updateConfig('auto_refresh', e.target.checked)}
                        />
                      </FormControl>

                      <FormControl>
                        <FormLabel>Data Source</FormLabel>
                        <Select
                          value={config.data_source || 'default'}
                          onChange={(e) => updateConfig('data_source', e.target.value)}
                        >
                          <option value="default">Default Data Source</option>
                          <option value="realtime">Real-time Data</option>
                          <option value="cached">Cached Data</option>
                          <option value="manual">Manual Refresh Only</option>
                        </Select>
                        <FormHelperText>
                          Choose how the widget gets its data
                        </FormHelperText>
                      </FormControl>

                      <FormControl>
                        <FormLabel>Error Handling</FormLabel>
                        <Select
                          value={config.error_handling || 'show_error'}
                          onChange={(e) => updateConfig('error_handling', e.target.value)}
                        >
                          <option value="show_error">Show Error Message</option>
                          <option value="hide_widget">Hide Widget</option>
                          <option value="show_placeholder">Show Placeholder</option>
                          <option value="retry_auto">Auto Retry</option>
                        </Select>
                        <FormHelperText>
                          How to handle data loading errors
                        </FormHelperText>
                      </FormControl>
                    </VStack>
                  </TabPanel>

                  {/* Advanced Tab */}
                  <TabPanel p={0} pt={4}>
                    <VStack spacing={4} align="stretch">
                      <Alert status="info" borderRadius="md">
                        <AlertIcon />
                        <Box>
                          <AlertTitle>Advanced Settings</AlertTitle>
                          <AlertDescription>
                            These settings affect the widget's internal behavior and performance.
                          </AlertDescription>
                        </Box>
                      </Alert>

                      <FormControl>
                        <FormLabel>Cache Duration (seconds)</FormLabel>
                        <NumberInput
                          value={config.cache_duration || 300}
                          onChange={(_, value) => updateConfig('cache_duration', value)}
                          min={0}
                          max={3600}
                        >
                          <NumberInputField />
                          <NumberInputStepper>
                            <NumberIncrementStepper />
                            <NumberDecrementStepper />
                          </NumberInputStepper>
                        </NumberInput>
                        <FormHelperText>
                          How long to cache widget data (0 = no caching)
                        </FormHelperText>
                      </FormControl>

                      <FormControl>
                        <FormLabel>Max Data Points</FormLabel>
                        <NumberInput
                          value={config.max_data_points || 100}
                          onChange={(_, value) => updateConfig('max_data_points', value)}
                          min={10}
                          max={1000}
                        >
                          <NumberInputField />
                          <NumberInputStepper>
                            <NumberIncrementStepper />
                            <NumberDecrementStepper />
                          </NumberInputStepper>
                        </NumberInput>
                        <FormHelperText>
                          Maximum number of data points to display
                        </FormHelperText>
                      </FormControl>

                      <FormControl>
                        <FormLabel>Custom CSS Class</FormLabel>
                        <Input
                          value={config.css_class || ''}
                          onChange={(e) => updateConfig('css_class', e.target.value)}
                          placeholder="custom-widget-class"
                        />
                        <FormHelperText>
                          Add custom CSS class for styling
                        </FormHelperText>
                      </FormControl>
                    </VStack>
                  </TabPanel>
                </TabPanels>
              </Tabs>

              {/* Changes Indicator */}
              {hasChanges && (
                <Alert status="warning" borderRadius="md">
                  <AlertIcon />
                  <AlertTitle>Unsaved Changes</AlertTitle>
                  <AlertDescription>
                    You have unsaved changes. Click Save to apply them.
                  </AlertDescription>
                </Alert>
              )}
            </VStack>
          </ModalBody>

          <ModalFooter>
            <HStack spacing={2}>
              <Button
                variant="ghost"
                leftIcon={<ResetIcon />}
                onClick={onResetOpen}
                size="sm"
              >
                Reset to Default
              </Button>
              
              <Spacer />
              
              <Button variant="ghost" mr={3} onClick={onClose}>
                Cancel
              </Button>
              
              <Button
                colorScheme="blue"
                leftIcon={<SaveIcon />}
                onClick={handleSave}
                isLoading={isSaving}
                loadingText="Saving..."
                isDisabled={!hasChanges}
              >
                Save Changes
              </Button>
            </HStack>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Reset Confirmation Modal */}
      <Modal isOpen={isResetOpen} onClose={onResetClose}>
        <ModalOverlay />
        <ModalContent>
          <ModalHeader>Reset to Default</ModalHeader>
          <ModalCloseButton />
          <ModalBody>
            <Text>
              Are you sure you want to reset this widget to its default configuration? 
              This will discard all your custom settings.
            </Text>
          </ModalBody>
          <ModalFooter>
            <Button variant="ghost" mr={3} onClick={onResetClose}>
              Cancel
            </Button>
            <Button colorScheme="red" onClick={handleResetToDefault}>
              Reset to Default
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
};

export default WidgetConfigModal;
