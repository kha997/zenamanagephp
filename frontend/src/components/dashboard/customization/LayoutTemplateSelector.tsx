import React, { useState } from 'react';
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
  SimpleGrid,
  Card,
  CardBody,
  CardHeader,
  Heading,
  Badge,
  Button,
  Icon,
  useColorModeValue,
  Image,
  Box,
  Flex,
  Spacer,
  Tooltip,
  Alert,
  AlertIcon,
  AlertTitle,
  AlertDescription,
  Divider,
  useDisclosure
} from '@chakra-ui/react';
import { ViewIcon, CheckIcon, InfoIcon, WarningIcon } from '@chakra-ui/icons';
import { LayoutTemplate } from '../../types/dashboard';

interface LayoutTemplateSelectorProps {
  isOpen: boolean;
  onClose: () => void;
  templates: LayoutTemplate[];
  onApplyTemplate: (templateId: string) => void;
}

const LayoutTemplateSelector: React.FC<LayoutTemplateSelectorProps> = ({
  isOpen,
  onClose,
  templates,
  onApplyTemplate
}) => {
  const [selectedTemplate, setSelectedTemplate] = useState<LayoutTemplate | null>(null);
  const [isApplying, setIsApplying] = useState(false);

  const { isOpen: isPreviewOpen, onOpen: onPreviewOpen, onClose: onPreviewClose } = useDisclosure();

  const bgColor = useColorModeValue('white', 'gray.800');
  const borderColor = useColorModeValue('gray.200', 'gray.600');
  const hoverBg = useColorModeValue('gray.50', 'gray.700');

  const handleApplyTemplate = async () => {
    if (selectedTemplate) {
      setIsApplying(true);
      try {
        await onApplyTemplate(selectedTemplate.id);
        onClose();
        setSelectedTemplate(null);
      } catch (error) {
        console.error('Failed to apply template:', error);
      } finally {
        setIsApplying(false);
      }
    }
  };

  const handlePreviewTemplate = (template: LayoutTemplate) => {
    setSelectedTemplate(template);
    onPreviewOpen();
  };

  const getRoleIcon = (role: string) => {
    const roleIcons: { [key: string]: string } = {
      'system_admin': 'crown',
      'project_manager': 'user-tie',
      'design_lead': 'pencil',
      'site_engineer': 'hard-hat',
      'qc_inspector': 'shield-check',
      'client_rep': 'user-check',
      'subcontractor_lead': 'users'
    };
    return roleIcons[role] || 'user';
  };

  const getRoleColor = (role: string) => {
    const roleColors: { [key: string]: string } = {
      'system_admin': 'purple',
      'project_manager': 'blue',
      'design_lead': 'green',
      'site_engineer': 'orange',
      'qc_inspector': 'red',
      'client_rep': 'teal',
      'subcontractor_lead': 'gray'
    };
    return roleColors[role] || 'gray';
  };

  const getWidgetIcon = (widgetType: string) => {
    const widgetIcons: { [key: string]: string } = {
      'chart': 'chart-bar',
      'metric': 'number',
      'table': 'table',
      'card': 'square',
      'alert': 'exclamation-triangle',
      'timeline': 'clock',
      'progress': 'progress',
      'map': 'map'
    };
    return widgetIcons[widgetType] || 'widget';
  };

  const getWidgetColor = (widgetType: string) => {
    const widgetColors: { [key: string]: string } = {
      'chart': 'blue',
      'metric': 'green',
      'table': 'purple',
      'card': 'gray',
      'alert': 'red',
      'timeline': 'orange',
      'progress': 'teal',
      'map': 'cyan'
    };
    return widgetColors[widgetType] || 'gray';
  };

  return (
    <>
      <Modal isOpen={isOpen} onClose={onClose} size="6xl">
        <ModalOverlay />
        <ModalContent maxHeight="80vh">
          <ModalHeader>
            <Flex align="center">
              <Icon as={ViewIcon} mr={2} />
              Dashboard Layout Templates
            </Flex>
          </ModalHeader>
          <ModalCloseButton />
          <ModalBody pb={6}>
            <VStack spacing={6} align="stretch">
              {/* Introduction */}
              <Alert status="info" borderRadius="md">
                <AlertIcon />
                <Box>
                  <AlertTitle>Choose a Layout Template</AlertTitle>
                  <AlertDescription>
                    Select a pre-configured dashboard layout that matches your role and workflow. 
                    You can customize the layout after applying a template.
                  </AlertDescription>
                </Box>
              </Alert>

              {/* Templates Grid */}
              <SimpleGrid columns={{ base: 1, md: 2, lg: 3 }} spacing={6}>
                {templates.map((template) => (
                  <Card
                    key={template.id}
                    bg={bgColor}
                    border="1px"
                    borderColor={borderColor}
                    cursor="pointer"
                    _hover={{ bg: hoverBg, borderColor: 'blue.300' }}
                    transition="all 0.2s"
                    position="relative"
                  >
                    <CardHeader pb={2}>
                      <Flex align="center" justify="space-between">
                        <HStack spacing={2}>
                          <Icon as={getRoleIcon(template.role)} color={`${getRoleColor(template.role)}.500`} />
                          <Heading size="sm">{template.name}</Heading>
                        </HStack>
                        <Badge colorScheme={getRoleColor(template.role)}>
                          {template.role.replace('_', ' ')}
                        </Badge>
                      </Flex>
                    </CardHeader>

                    <CardBody pt={0}>
                      <Text fontSize="sm" color="gray.600" mb={4}>
                        {template.description}
                      </Text>

                      {/* Widget Preview */}
                      <Box mb={4}>
                        <Text fontSize="xs" fontWeight="medium" mb={2} color="gray.500">
                          INCLUDED WIDGETS:
                        </Text>
                        <SimpleGrid columns={2} spacing={1}>
                          {template.widgets.slice(0, 6).map((widgetId, index) => (
                            <HStack key={index} spacing={1}>
                              <Icon as={getWidgetIcon('widget')} boxSize={3} color="gray.400" />
                              <Text fontSize="xs" color="gray.600">
                                {widgetId.replace('_', ' ')}
                              </Text>
                            </HStack>
                          ))}
                          {template.widgets.length > 6 && (
                            <Text fontSize="xs" color="gray.500">
                              +{template.widgets.length - 6} more...
                            </Text>
                          )}
                        </SimpleGrid>
                      </Box>

                      {/* Actions */}
                      <VStack spacing={2}>
                        <Button
                          size="sm"
                          colorScheme="blue"
                          leftIcon={<ViewIcon />}
                          width="full"
                          onClick={() => handlePreviewTemplate(template)}
                        >
                          Preview Layout
                        </Button>

                        <Button
                          size="sm"
                          variant="outline"
                          leftIcon={<CheckIcon />}
                          width="full"
                          onClick={() => {
                            setSelectedTemplate(template);
                            handleApplyTemplate();
                          }}
                          isLoading={isApplying && selectedTemplate?.id === template.id}
                          loadingText="Applying..."
                        >
                          Apply Template
                        </Button>
                      </VStack>
                    </CardBody>

                    {/* Recommended Badge */}
                    {template.recommended && (
                      <Badge
                        position="absolute"
                        top={2}
                        right={2}
                        colorScheme="green"
                        borderRadius="full"
                      >
                        Recommended
                      </Badge>
                    )}
                  </Card>
                ))}
              </SimpleGrid>

              {/* No Templates */}
              {templates.length === 0 && (
                <Box textAlign="center" py={8}>
                  <Icon as={WarningIcon} boxSize={12} color="gray.400" mb={4} />
                  <Text color="gray.500" fontSize="lg" mb={2}>
                    No Templates Available
                  </Text>
                  <Text color="gray.400">
                    No layout templates are available for your role. Contact your administrator.
                  </Text>
                </Box>
              )}
            </VStack>
          </ModalBody>
        </ModalContent>
      </Modal>

      {/* Template Preview Modal */}
      <Modal isOpen={isPreviewOpen} onClose={onPreviewClose} size="4xl">
        <ModalOverlay />
        <ModalContent maxHeight="80vh">
          <ModalHeader>
            <HStack>
              <Icon as={getRoleIcon(selectedTemplate?.role || '')} color={`${getRoleColor(selectedTemplate?.role || '')}.500`} />
              <Text>Preview: {selectedTemplate?.name}</Text>
            </HStack>
          </ModalHeader>
          <ModalCloseButton />
          <ModalBody pb={6}>
            {selectedTemplate && (
              <VStack spacing={6} align="stretch">
                {/* Template Info */}
                <Box p={4} bg="gray.50" borderRadius="md">
                  <Text fontSize="sm" color="gray.600" mb={2}>
                    {selectedTemplate.description}
                  </Text>
                  <HStack spacing={2}>
                    <Badge colorScheme={getRoleColor(selectedTemplate.role)}>
                      {selectedTemplate.role.replace('_', ' ')}
                    </Badge>
                    <Badge variant="outline">
                      {selectedTemplate.widgets.length} widgets
                    </Badge>
                  </HStack>
                </Box>

                <Divider />

                {/* Layout Preview */}
                <Box>
                  <Text fontWeight="medium" mb={4}>Layout Preview</Text>
                  <Box
                    p={4}
                    border="2px dashed"
                    borderColor="gray.300"
                    borderRadius="md"
                    bg="gray.50"
                    minHeight="300px"
                  >
                    <SimpleGrid columns={12} spacing={2}>
                      {selectedTemplate.widgets.map((widgetId, index) => {
                        const size = index % 4 === 0 ? 'large' : index % 3 === 0 ? 'medium' : 'small';
                        const colSpan = size === 'large' ? 6 : size === 'medium' ? 4 : 3;
                        
                        return (
                          <Box
                            key={index}
                            gridColumn={`span ${colSpan}`}
                            height="60px"
                            bg="white"
                            border="1px"
                            borderColor="gray.200"
                            borderRadius="md"
                            display="flex"
                            align="center"
                            justify="center"
                            fontSize="xs"
                            color="gray.500"
                          >
                            <HStack spacing={1}>
                              <Icon as={getWidgetIcon('widget')} boxSize={3} />
                              <Text>{widgetId.replace('_', ' ')}</Text>
                            </HStack>
                          </Box>
                        );
                      })}
                    </SimpleGrid>
                  </Box>
                </Box>

                {/* Widget List */}
                <Box>
                  <Text fontWeight="medium" mb={4}>Included Widgets</Text>
                  <SimpleGrid columns={2} spacing={2}>
                    {selectedTemplate.widgets.map((widgetId, index) => (
                      <HStack key={index} spacing={2} p={2} bg="gray.50" borderRadius="md">
                        <Icon as={getWidgetIcon('widget')} color="gray.500" />
                        <Text fontSize="sm">{widgetId.replace('_', ' ')}</Text>
                        <Badge size="sm" colorScheme={getWidgetColor('widget')}>
                          Widget
                        </Badge>
                      </HStack>
                    ))}
                  </SimpleGrid>
                </Box>
              </VStack>
            )}
          </ModalBody>

          <ModalFooter>
            <Button variant="ghost" mr={3} onClick={onPreviewClose}>
              Close Preview
            </Button>
            <Button
              colorScheme="blue"
              onClick={() => {
                if (selectedTemplate) {
                  handleApplyTemplate();
                  onPreviewClose();
                }
              }}
              isLoading={isApplying}
              loadingText="Applying..."
            >
              Apply This Template
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
};

export default LayoutTemplateSelector;
