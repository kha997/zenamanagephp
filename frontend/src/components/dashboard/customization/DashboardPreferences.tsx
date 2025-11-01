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
  Select,
  Button,
  Switch,
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
  Slider,
  SliderTrack,
  SliderFilledTrack,
  SliderThumb,
  SliderMark,
  NumberInput,
  NumberInputField,
  NumberInputStepper,
  NumberIncrementStepper,
  NumberDecrementStepper
} from '@chakra-ui/react';
import { SettingsIcon, SaveIcon, ResetIcon, PaletteIcon, MonitorIcon, BellIcon } from '@chakra-ui/icons';
import { CustomizationOptions } from '../../types/dashboard';

interface DashboardPreferencesProps {
  isOpen: boolean;
  onClose: () => void;
  options: CustomizationOptions | null;
}

const DashboardPreferences: React.FC<DashboardPreferencesProps> = ({
  isOpen,
  onClose,
  options
}) => {
  const [preferences, setPreferences] = useState<any>({});
  const [originalPreferences, setOriginalPreferences] = useState<any>({});
  const [isSaving, setIsSaving] = useState(false);
  const [hasChanges, setHasChanges] = useState(false);

  const bgColor = useColorModeValue('white', 'gray.800');
  const borderColor = useColorModeValue('gray.200', 'gray.600');

  // Initialize preferences when options change
  useEffect(() => {
    if (options) {
      const initialPreferences = {
        theme: 'light',
        refresh_interval: 300,
        compact_mode: false,
        show_widget_borders: true,
        enable_animations: true,
        auto_refresh: true,
        grid_density: 'medium',
        sidebar_collapsed: false,
        notifications_enabled: true,
        sound_enabled: false,
        ...options.default_preferences
      };
      
      setPreferences(initialPreferences);
      setOriginalPreferences(initialPreferences);
      setHasChanges(false);
    }
  }, [options]);

  // Check for changes
  useEffect(() => {
    if (options) {
      const changed = JSON.stringify(preferences) !== JSON.stringify(originalPreferences);
      setHasChanges(changed);
    }
  }, [preferences, originalPreferences, options]);

  const handleSave = async () => {
    if (!hasChanges) return;

    setIsSaving(true);
    try {
      const response = await fetch('/api/v1/dashboard/customization/preferences', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        },
        body: JSON.stringify({ preferences })
      });

      if (!response.ok) {
        throw new Error('Failed to save preferences');
      }

      setOriginalPreferences(preferences);
      setHasChanges(false);
      onClose();
    } catch (error) {
      console.error('Failed to save preferences:', error);
    } finally {
      setIsSaving(false);
    }
  };

  const handleReset = () => {
    if (options) {
      setPreferences(originalPreferences);
      setHasChanges(false);
    }
  };

  const updatePreference = (key: string, value: any) => {
    setPreferences(prev => ({ ...prev, [key]: value }));
  };

  const getThemeDescription = (theme: string) => {
    const descriptions: { [key: string]: string } = {
      'light': 'Clean, bright interface with high contrast',
      'dark': 'Dark interface that\'s easy on the eyes',
      'auto': 'Automatically switch based on system preference'
    };
    return descriptions[theme] || 'Standard theme';
  };

  const getGridDensityDescription = (density: string) => {
    const descriptions: { [key: string]: string } = {
      'compact': 'More widgets per screen, smaller spacing',
      'medium': 'Balanced layout with standard spacing',
      'comfortable': 'Larger spacing, fewer widgets per screen'
    };
    return descriptions[density] || 'Balanced layout';
  };

  if (!options) return null;

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="2xl">
      <ModalOverlay />
      <ModalContent maxHeight="80vh">
        <ModalHeader>
          <HStack>
            <Icon as={SettingsIcon} />
            <Text>Dashboard Preferences</Text>
          </HStack>
        </ModalHeader>
        <ModalCloseButton />
        <ModalBody pb={6}>
          <VStack spacing={6} align="stretch">
            {/* Introduction */}
            <Alert status="info" borderRadius="md">
              <AlertIcon />
              <Box>
                <AlertTitle>Customize Your Experience</AlertTitle>
                <AlertDescription>
                  Adjust these settings to personalize your dashboard experience. 
                  Changes will be saved automatically.
                </AlertDescription>
              </Box>
            </Alert>

            {/* Preferences Tabs */}
            <Tabs>
              <TabList>
                <Tab>
                  <Icon as={PaletteIcon} mr={2} />
                  Appearance
                </Tab>
                <Tab>
                  <Icon as={MonitorIcon} mr={2} />
                  Layout
                </Tab>
                <Tab>
                  <Icon as={BellIcon} mr={2} />
                  Notifications
                </Tab>
                <Tab>Advanced</Tab>
              </TabList>

              <TabPanels>
                {/* Appearance Tab */}
                <TabPanel p={0} pt={4}>
                  <VStack spacing={4} align="stretch">
                    <FormControl>
                      <FormLabel>Theme</FormLabel>
                      <Select
                        value={preferences.theme || 'light'}
                        onChange={(e) => updatePreference('theme', e.target.value)}
                      >
                        <option value="light">Light Theme</option>
                        <option value="dark">Dark Theme</option>
                        <option value="auto">Auto (System)</option>
                      </Select>
                      <FormHelperText>
                        {getThemeDescription(preferences.theme)}
                      </FormHelperText>
                    </FormControl>

                    <FormControl display="flex" alignItems="center" justifyContent="space-between">
                      <Box>
                        <FormLabel mb={0}>Show Widget Borders</FormLabel>
                        <FormHelperText>Display borders around widgets</FormHelperText>
                      </Box>
                      <Switch
                        isChecked={preferences.show_widget_borders !== false}
                        onChange={(e) => updatePreference('show_widget_borders', e.target.checked)}
                      />
                    </FormControl>

                    <FormControl display="flex" alignItems="center" justifyContent="space-between">
                      <Box>
                        <FormLabel mb={0}>Enable Animations</FormLabel>
                        <FormHelperText>Enable smooth transitions and animations</FormHelperText>
                      </Box>
                      <Switch
                        isChecked={preferences.enable_animations !== false}
                        onChange={(e) => updatePreference('enable_animations', e.target.checked)}
                      />
                    </FormControl>

                    <FormControl display="flex" alignItems="center" justifyContent="space-between">
                      <Box>
                        <FormLabel mb={0}>Compact Mode</FormLabel>
                        <FormHelperText>Use compact spacing and smaller fonts</FormHelperText>
                      </Box>
                      <Switch
                        isChecked={preferences.compact_mode || false}
                        onChange={(e) => updatePreference('compact_mode', e.target.checked)}
                      />
                    </FormControl>
                  </VStack>
                </TabPanel>

                {/* Layout Tab */}
                <TabPanel p={0} pt={4}>
                  <VStack spacing={4} align="stretch">
                    <FormControl>
                      <FormLabel>Grid Density</FormLabel>
                      <SimpleGrid columns={3} spacing={2}>
                        {['compact', 'medium', 'comfortable'].map((density) => (
                          <Button
                            key={density}
                            size="sm"
                            variant={preferences.grid_density === density ? 'solid' : 'outline'}
                            colorScheme={preferences.grid_density === density ? 'blue' : 'gray'}
                            onClick={() => updatePreference('grid_density', density)}
                          >
                            {density.charAt(0).toUpperCase() + density.slice(1)}
                          </Button>
                        ))}
                      </SimpleGrid>
                      <FormHelperText>
                        {getGridDensityDescription(preferences.grid_density)}
                      </FormHelperText>
                    </FormControl>

                    <FormControl>
                      <FormLabel>Default Refresh Interval</FormLabel>
                      <Select
                        value={preferences.refresh_interval || 300}
                        onChange={(e) => updatePreference('refresh_interval', parseInt(e.target.value))}
                      >
                        <option value={30}>30 seconds</option>
                        <option value={60}>1 minute</option>
                        <option value={300}>5 minutes</option>
                        <option value={900}>15 minutes</option>
                        <option value={1800}>30 minutes</option>
                      </Select>
                      <FormHelperText>
                        Default refresh interval for new widgets
                      </FormHelperText>
                    </FormControl>

                    <FormControl display="flex" alignItems="center" justifyContent="space-between">
                      <Box>
                        <FormLabel mb={0}>Auto Refresh</FormLabel>
                        <FormHelperText>Automatically refresh dashboard data</FormHelperText>
                      </Box>
                      <Switch
                        isChecked={preferences.auto_refresh !== false}
                        onChange={(e) => updatePreference('auto_refresh', e.target.checked)}
                      />
                    </FormControl>

                    <FormControl display="flex" alignItems="center" justifyContent="space-between">
                      <Box>
                        <FormLabel mb={0}>Sidebar Collapsed</FormLabel>
                        <FormHelperText>Start with sidebar collapsed</FormHelperText>
                      </Box>
                      <Switch
                        isChecked={preferences.sidebar_collapsed || false}
                        onChange={(e) => updatePreference('sidebar_collapsed', e.target.checked)}
                      />
                    </FormControl>
                  </VStack>
                </TabPanel>

                {/* Notifications Tab */}
                <TabPanel p={0} pt={4}>
                  <VStack spacing={4} align="stretch">
                    <FormControl display="flex" alignItems="center" justifyContent="space-between">
                      <Box>
                        <FormLabel mb={0}>Enable Notifications</FormLabel>
                        <FormHelperText>Show browser notifications for alerts</FormHelperText>
                      </Box>
                      <Switch
                        isChecked={preferences.notifications_enabled !== false}
                        onChange={(e) => updatePreference('notifications_enabled', e.target.checked)}
                      />
                    </FormControl>

                    <FormControl display="flex" alignItems="center" justifyContent="space-between">
                      <Box>
                        <FormLabel mb={0}>Sound Notifications</FormLabel>
                        <FormHelperText>Play sound for notifications</FormHelperText>
                      </Box>
                      <Switch
                        isChecked={preferences.sound_enabled || false}
                        onChange={(e) => updatePreference('sound_enabled', e.target.checked)}
                      />
                    </FormControl>

                    <FormControl>
                      <FormLabel>Notification Position</FormLabel>
                      <Select
                        value={preferences.notification_position || 'top-right'}
                        onChange={(e) => updatePreference('notification_position', e.target.value)}
                      >
                        <option value="top-left">Top Left</option>
                        <option value="top-right">Top Right</option>
                        <option value="bottom-left">Bottom Left</option>
                        <option value="bottom-right">Bottom Right</option>
                        <option value="center">Center</option>
                      </Select>
                      <FormHelperText>
                        Position of notification toasts
                      </FormHelperText>
                    </FormControl>

                    <FormControl>
                      <FormLabel>Notification Duration (seconds)</FormLabel>
                      <Slider
                        value={preferences.notification_duration || 5}
                        onChange={(value) => updatePreference('notification_duration', value)}
                        min={1}
                        max={10}
                        step={1}
                      >
                        <SliderMark value={1} mt={1} ml={-2} fontSize="sm">1s</SliderMark>
                        <SliderMark value={5} mt={1} ml={-2} fontSize="sm">5s</SliderMark>
                        <SliderMark value={10} mt={1} ml={-2} fontSize="sm">10s</SliderMark>
                        <SliderTrack>
                          <SliderFilledTrack />
                        </SliderTrack>
                        <SliderThumb />
                      </Slider>
                      <FormHelperText>
                        How long notifications stay visible
                      </FormHelperText>
                    </FormControl>
                  </VStack>
                </TabPanel>

                {/* Advanced Tab */}
                <TabPanel p={0} pt={4}>
                  <VStack spacing={4} align="stretch">
                    <Alert status="warning" borderRadius="md">
                      <AlertIcon />
                      <Box>
                        <AlertTitle>Advanced Settings</AlertTitle>
                        <AlertDescription>
                          These settings affect system performance and behavior.
                        </AlertDescription>
                      </Box>
                    </Alert>

                    <FormControl>
                      <FormLabel>Cache Duration (minutes)</FormLabel>
                      <NumberInput
                        value={preferences.cache_duration || 5}
                        onChange={(_, value) => updatePreference('cache_duration', value)}
                        min={1}
                        max={60}
                      >
                        <NumberInputField />
                        <NumberInputStepper>
                          <NumberIncrementStepper />
                          <NumberDecrementStepper />
                        </NumberInputStepper>
                      </NumberInput>
                      <FormHelperText>
                        How long to cache dashboard data
                      </FormHelperText>
                    </FormControl>

                    <FormControl>
                      <FormLabel>Max Concurrent Requests</FormLabel>
                      <NumberInput
                        value={preferences.max_concurrent_requests || 5}
                        onChange={(_, value) => updatePreference('max_concurrent_requests', value)}
                        min={1}
                        max={20}
                      >
                        <NumberInputField />
                        <NumberInputStepper>
                          <NumberIncrementStepper />
                          <NumberDecrementStepper />
                        </NumberInputStepper>
                      </NumberInput>
                      <FormHelperText>
                        Maximum number of simultaneous API requests
                      </FormHelperText>
                    </FormControl>

                    <FormControl display="flex" alignItems="center" justifyContent="space-between">
                      <Box>
                        <FormLabel mb={0}>Debug Mode</FormLabel>
                        <FormHelperText>Enable debug logging and tools</FormHelperText>
                      </Box>
                      <Switch
                        isChecked={preferences.debug_mode || false}
                        onChange={(e) => updatePreference('debug_mode', e.target.checked)}
                      />
                    </FormControl>

                    <FormControl display="flex" alignItems="center" justifyContent="space-between">
                      <Box>
                        <FormLabel mb={0}>Performance Monitoring</FormLabel>
                        <FormHelperText>Track performance metrics</FormHelperText>
                      </Box>
                      <Switch
                        isChecked={preferences.performance_monitoring !== false}
                        onChange={(e) => updatePreference('performance_monitoring', e.target.checked)}
                      />
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
              onClick={handleReset}
              size="sm"
              isDisabled={!hasChanges}
            >
              Reset
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
              Save Preferences
            </Button>
          </HStack>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
};

export default DashboardPreferences;
