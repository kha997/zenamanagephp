import React, { useState } from 'react';
import {
  Box,
  Table,
  Thead,
  Tbody,
  Tr,
  Th,
  Td,
  Text,
  Badge,
  Flex,
  IconButton,
  Menu,
  MenuButton,
  MenuList,
  MenuItem,
  Input,
  InputGroup,
  InputLeftElement,
  Select,
  HStack,
  Spinner
} from '@chakra-ui/react';
import {
  ChevronDownIcon,
  SearchIcon,
  ExternalLinkIcon,
  DownloadIcon
} from '@chakra-ui/icons';

interface WidgetTableProps {
  data: any;
  config: Record<string, any>;
}

interface TableColumn {
  key: string;
  label: string;
  type?: 'text' | 'number' | 'date' | 'status' | 'badge';
  format?: string;
  width?: string;
}

const WidgetTable: React.FC<WidgetTableProps> = ({ data, config }) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [sortField, setSortField] = useState<string>('');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');
  const [currentPage, setCurrentPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);

  const displayConfig = config.display || {};
  const title = displayConfig.title || 'Table';
  const columns: TableColumn[] = displayConfig.columns || [];
  const showSearch = displayConfig.show_search !== false;
  const showPagination = displayConfig.show_pagination !== false;
  const maxRows = displayConfig.max_rows || 10;

  const getStatusColor = (status: string) => {
    const colors = {
      completed: 'green',
      in_progress: 'blue',
      pending: 'orange',
      cancelled: 'red',
      approved: 'green',
      rejected: 'red',
      active: 'green',
      inactive: 'gray'
    };
    return colors[status as keyof typeof colors] || 'gray';
  };

  const formatValue = (value: any, column: TableColumn) => {
    if (value === null || value === undefined) {
      return '-';
    }

    switch (column.type) {
      case 'number':
        return typeof value === 'number' ? value.toLocaleString() : value;
      case 'date':
        return new Date(value).toLocaleDateString();
      case 'status':
        return (
          <Badge colorScheme={getStatusColor(value)} variant="subtle">
            {value.replace(/_/g, ' ')}
          </Badge>
        );
      case 'badge':
        return (
          <Badge colorScheme="blue" variant="outline">
            {value}
          </Badge>
        );
      default:
        return value;
    }
  };

  const filteredData = React.useMemo(() => {
    if (!data || !Array.isArray(data)) return [];

    let filtered = data;

    // Apply search filter
    if (searchTerm && columns.length > 0) {
      filtered = filtered.filter((row: any) =>
        columns.some((column) => {
          const value = row[column.key];
          return value && value.toString().toLowerCase().includes(searchTerm.toLowerCase());
        })
      );
    }

    // Apply sorting
    if (sortField) {
      filtered = [...filtered].sort((a: any, b: any) => {
        const aVal = a[sortField];
        const bVal = b[sortField];
        
        if (typeof aVal === 'number' && typeof bVal === 'number') {
          return sortDirection === 'asc' ? aVal - bVal : bVal - aVal;
        }
        
        const aStr = aVal?.toString().toLowerCase() || '';
        const bStr = bVal?.toString().toLowerCase() || '';
        
        if (sortDirection === 'asc') {
          return aStr.localeCompare(bStr);
        } else {
          return bStr.localeCompare(aStr);
        }
      });
    }

    return filtered;
  }, [data, searchTerm, sortField, sortDirection, columns]);

  const paginatedData = React.useMemo(() => {
    if (!showPagination) {
      return filteredData.slice(0, maxRows);
    }

    const startIndex = (currentPage - 1) * pageSize;
    const endIndex = startIndex + pageSize;
    return filteredData.slice(startIndex, endIndex);
  }, [filteredData, currentPage, pageSize, showPagination, maxRows]);

  const totalPages = Math.ceil(filteredData.length / pageSize);

  const handleSort = (field: string) => {
    if (sortField === field) {
      setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortDirection('asc');
    }
  };

  const handleRowClick = (row: any) => {
    if (displayConfig.on_row_click) {
      // Handle row click action
      console.log('Row clicked:', row);
    }
  };

  if (!data) {
    return (
      <Box textAlign="center" py={8}>
        <Text color="gray.500">No table data available</Text>
      </Box>
    );
  }

  if (!Array.isArray(data)) {
    return (
      <Box textAlign="center" py={8}>
        <Text color="red.500">Invalid table data format</Text>
      </Box>
    );
  }

  return (
    <Box>
      {/* Table Header */}
      <Flex justify="space-between" align="center" mb={4}>
        <Text fontSize="lg" fontWeight="semibold" color="gray.700">
          {title}
        </Text>
        
        <HStack spacing={2}>
          {displayConfig.show_export && (
            <IconButton
              aria-label="Export"
              icon={<DownloadIcon />}
              size="sm"
              variant="outline"
            />
          )}
          
          <Menu>
            <MenuButton
              as={IconButton}
              aria-label="Table options"
              icon={<ChevronDownIcon />}
              size="sm"
              variant="outline"
            />
            <MenuList>
              <MenuItem icon={<ExternalLinkIcon />}>
                View Full Table
              </MenuItem>
              <MenuItem icon={<DownloadIcon />}>
                Export Data
              </MenuItem>
            </MenuList>
          </Menu>
        </HStack>
      </Flex>

      {/* Search and Filters */}
      {showSearch && (
        <Flex gap={4} mb={4}>
          <InputGroup size="sm" flex={1}>
            <InputLeftElement pointerEvents="none">
              <SearchIcon color="gray.300" />
            </InputLeftElement>
            <Input
              placeholder="Search..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </InputGroup>
          
          {showPagination && (
            <Select
              size="sm"
              width="120px"
              value={pageSize}
              onChange={(e) => setPageSize(Number(e.target.value))}
            >
              <option value={5}>5 rows</option>
              <option value={10}>10 rows</option>
              <option value={20}>20 rows</option>
              <option value={50}>50 rows</option>
            </Select>
          )}
        </Flex>
      )}

      {/* Table */}
      <Box overflowX="auto">
        <Table size="sm" variant="simple">
          <Thead>
            <Tr>
              {columns.map((column) => (
                <Th
                  key={column.key}
                  cursor="pointer"
                  onClick={() => handleSort(column.key)}
                  _hover={{ bg: 'gray.50' }}
                  width={column.width}
                >
                  <Flex align="center" gap={1}>
                    {column.label}
                    {sortField === column.key && (
                      <Text fontSize="xs" color="blue.500">
                        {sortDirection === 'asc' ? '↑' : '↓'}
                      </Text>
                    )}
                  </Flex>
                </Th>
              ))}
            </Tr>
          </Thead>
          <Tbody>
            {paginatedData.map((row: any, index: number) => (
              <Tr
                key={index}
                cursor={displayConfig.on_row_click ? 'pointer' : 'default'}
                onClick={() => handleRowClick(row)}
                _hover={{ bg: 'gray.50' }}
              >
                {columns.map((column) => (
                  <Td key={column.key}>
                    {formatValue(row[column.key], column)}
                  </Td>
                ))}
              </Tr>
            ))}
          </Tbody>
        </Table>
      </Box>

      {/* Pagination */}
      {showPagination && totalPages > 1 && (
        <Flex justify="space-between" align="center" mt={4}>
          <Text fontSize="sm" color="gray.600">
            Showing {((currentPage - 1) * pageSize) + 1} to {Math.min(currentPage * pageSize, filteredData.length)} of {filteredData.length} entries
          </Text>
          
          <HStack spacing={2}>
            <IconButton
              aria-label="Previous page"
              icon={<ChevronDownIcon transform="rotate(90deg)" />}
              size="sm"
              variant="outline"
              isDisabled={currentPage === 1}
              onClick={() => setCurrentPage(currentPage - 1)}
            />
            
            <Text fontSize="sm">
              Page {currentPage} of {totalPages}
            </Text>
            
            <IconButton
              aria-label="Next page"
              icon={<ChevronDownIcon transform="rotate(-90deg)" />}
              size="sm"
              variant="outline"
              isDisabled={currentPage === totalPages}
              onClick={() => setCurrentPage(currentPage + 1)}
            />
          </HStack>
        </Flex>
      )}

      {/* Empty State */}
      {filteredData.length === 0 && (
        <Box textAlign="center" py={8}>
          <Text color="gray.500">
            {searchTerm ? 'No results found' : 'No data available'}
          </Text>
        </Box>
      )}
    </Box>
  );
};

export default WidgetTable;
