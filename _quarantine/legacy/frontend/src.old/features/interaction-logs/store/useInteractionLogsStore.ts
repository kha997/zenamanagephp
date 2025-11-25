/**
 * Zustand store để quản lý state cho Interaction Logs module
 * Bao gồm filters, selection, UI state và các actions
 */
import { create } from 'zustand';
import { devtools, persist } from 'zustand/middleware';
import { immer } from 'zustand/middleware/immer';
import { 
  InteractionLog, 
  InteractionLogFilters, 
  PaginationState,
  LoadingState,
  InteractionLogType,
  VisibilityType 
} from '../types/interactionLog';

// Interface cho store state
interface InteractionLogsState {
  // Filters state
  filters: InteractionLogFilters;
  
  // Selection state
  selectedLogs: number[];
  
  // UI state
  isFilterPanelOpen: boolean;
  viewMode: 'list' | 'grid' | 'timeline';
  
  // Loading states
  loadingStates: {
    list: LoadingState;
    create: LoadingState;
    update: LoadingState;
    delete: LoadingState;
    approve: LoadingState;
  };
  
  // Pagination
  pagination: PaginationState;
  
  // Quick filters
  quickFilters: {
    showOnlyClientVisible: boolean;
    showOnlyPendingApproval: boolean;
    showOnlyMyLogs: boolean;
  };
}

// Interface cho store actions
interface InteractionLogsActions {
  // Filter actions
  setFilters: (filters: Partial<InteractionLogFilters>) => void;
  resetFilters: () => void;
  setDateRange: (startDate: string | null, endDate: string | null) => void;
  setType: (type: InteractionLogType | null) => void;
  setVisibility: (visibility: VisibilityType | null) => void;
  setTagPath: (tagPath: string) => void;
  setSearchQuery: (query: string) => void;
  
  // Selection actions
  selectLog: (id: number) => void;
  deselectLog: (id: number) => void;
  selectAllLogs: (logIds: number[]) => void;
  clearSelection: () => void;
  toggleLogSelection: (id: number) => void;
  
  // UI actions
  toggleFilterPanel: () => void;
  setViewMode: (mode: 'list' | 'grid' | 'timeline') => void;
  
  // Loading actions
  setLoadingState: (operation: keyof InteractionLogsState['loadingStates'], state: LoadingState) => void;
  
  // Pagination actions
  setPagination: (pagination: Partial<PaginationState>) => void;
  setPage: (page: number) => void;
  setPerPage: (perPage: number) => void;
  
  // Quick filter actions
  toggleQuickFilter: (filter: keyof InteractionLogsState['quickFilters']) => void;
  setQuickFilters: (filters: Partial<InteractionLogsState['quickFilters']>) => void;
  
  // Utility actions
  getSelectedLogsCount: () => number;
  isLogSelected: (id: number) => boolean;
  hasActiveFilters: () => boolean;
}

// Default state values
const defaultFilters: InteractionLogFilters = {
  project_id: null,
  type: null,
  visibility: null,
  tag_path: '',
  search: '',
  start_date: null,
  end_date: null,
  created_by: null,
  client_approved: null,
  page: 1,
  per_page: 20,
};

const defaultPagination: PaginationState = {
  page: 1,
  perPage: 20,
  total: 0,
  totalPages: 0,
};

const defaultLoadingStates = {
  list: { isLoading: false, error: null } as LoadingState,
  create: { isLoading: false, error: null } as LoadingState,
  update: { isLoading: false, error: null } as LoadingState,
  delete: { isLoading: false, error: null } as LoadingState,
  approve: { isLoading: false, error: null } as LoadingState,
};

// Tạo store với Zustand
export const useInteractionLogsStore = create<InteractionLogsState & InteractionLogsActions>()()
  (devtools(
    persist(
      immer((set, get) => ({
        // Initial state
        filters: defaultFilters,
        selectedLogs: [],
        isFilterPanelOpen: false,
        viewMode: 'list',
        loadingStates: defaultLoadingStates,
        pagination: defaultPagination,
        quickFilters: {
          showOnlyClientVisible: false,
          showOnlyPendingApproval: false,
          showOnlyMyLogs: false,
        },

        // Filter actions
        setFilters: (newFilters) => {
          set((state) => {
            Object.assign(state.filters, newFilters);
            // Reset pagination khi filter thay đổi
            state.pagination.page = 1;
          });
        },

        resetFilters: () => {
          set((state) => {
            state.filters = { ...defaultFilters };
            state.pagination = { ...defaultPagination };
            state.quickFilters = {
              showOnlyClientVisible: false,
              showOnlyPendingApproval: false,
              showOnlyMyLogs: false,
            };
          });
        },

        setDateRange: (startDate, endDate) => {
          set((state) => {
            state.filters.start_date = startDate;
            state.filters.end_date = endDate;
            state.pagination.page = 1;
          });
        },

        setType: (type) => {
          set((state) => {
            state.filters.type = type;
            state.pagination.page = 1;
          });
        },

        setVisibility: (visibility) => {
          set((state) => {
            state.filters.visibility = visibility;
            state.pagination.page = 1;
          });
        },

        setTagPath: (tagPath) => {
          set((state) => {
            state.filters.tag_path = tagPath;
            state.pagination.page = 1;
          });
        },

        setSearchQuery: (query) => {
          set((state) => {
            state.filters.search = query;
            state.pagination.page = 1;
          });
        },

        // Selection actions
        selectLog: (id) => {
          set((state) => {
            if (!state.selectedLogs.includes(id)) {
              state.selectedLogs.push(id);
            }
          });
        },

        deselectLog: (id) => {
          set((state) => {
            const index = state.selectedLogs.indexOf(id);
            if (index > -1) {
              state.selectedLogs.splice(index, 1);
            }
          });
        },

        selectAllLogs: (logIds) => {
          set((state) => {
            state.selectedLogs = [...logIds];
          });
        },

        clearSelection: () => {
          set((state) => {
            state.selectedLogs = [];
          });
        },

        toggleLogSelection: (id) => {
          const { selectedLogs } = get();
          if (selectedLogs.includes(id)) {
            get().deselectLog(id);
          } else {
            get().selectLog(id);
          }
        },

        // UI actions
        toggleFilterPanel: () => {
          set((state) => {
            state.isFilterPanelOpen = !state.isFilterPanelOpen;
          });
        },

        setViewMode: (mode) => {
          set((state) => {
            state.viewMode = mode;
          });
        },

        // Loading actions
        setLoadingState: (operation, loadingState) => {
          set((state) => {
            state.loadingStates[operation] = loadingState;
          });
        },

        // Pagination actions
        setPagination: (newPagination) => {
          set((state) => {
            Object.assign(state.pagination, newPagination);
          });
        },

        setPage: (page) => {
          set((state) => {
            state.pagination.page = page;
          });
        },

        setPerPage: (perPage) => {
          set((state) => {
            state.pagination.perPage = perPage;
            state.pagination.page = 1; // Reset về trang đầu
          });
        },

        // Quick filter actions
        toggleQuickFilter: (filter) => {
          set((state) => {
            state.quickFilters[filter] = !state.quickFilters[filter];
            
            // Cập nhật filters chính dựa trên quick filters
            if (filter === 'showOnlyClientVisible') {
              state.filters.visibility = state.quickFilters.showOnlyClientVisible ? 'client' : null;
            }
            
            if (filter === 'showOnlyPendingApproval') {
              state.filters.client_approved = state.quickFilters.showOnlyPendingApproval ? false : null;
            }
            
            state.pagination.page = 1;
          });
        },

        setQuickFilters: (newQuickFilters) => {
          set((state) => {
            Object.assign(state.quickFilters, newQuickFilters);
            state.pagination.page = 1;
          });
        },

        // Utility actions
        getSelectedLogsCount: () => {
          return get().selectedLogs.length;
        },

        isLogSelected: (id) => {
          return get().selectedLogs.includes(id);
        },

        hasActiveFilters: () => {
          const { filters, quickFilters } = get();
          
          // Kiểm tra main filters
          const hasMainFilters = (
            filters.type !== null ||
            filters.visibility !== null ||
            filters.tag_path !== '' ||
            filters.search !== '' ||
            filters.start_date !== null ||
            filters.end_date !== null ||
            filters.created_by !== null ||
            filters.client_approved !== null
          );
          
          // Kiểm tra quick filters
          const hasQuickFilters = (
            quickFilters.showOnlyClientVisible ||
            quickFilters.showOnlyPendingApproval ||
            quickFilters.showOnlyMyLogs
          );
          
          return hasMainFilters || hasQuickFilters;
        },
      })),
      {
        name: 'interaction-logs-store',
        // Chỉ persist một số state cần thiết
        partialize: (state) => ({
          filters: state.filters,
          viewMode: state.viewMode,
          quickFilters: state.quickFilters,
          pagination: {
            perPage: state.pagination.perPage, // Chỉ lưu perPage
          },
        }),
      }
    ),
    {
      name: 'interaction-logs-store',
    }
  ));

// Selector hooks để tối ưu re-renders
export const useInteractionLogsFilters = () => 
  useInteractionLogsStore((state) => state.filters);

export const useInteractionLogsSelection = () => 
  useInteractionLogsStore((state) => ({
    selectedLogs: state.selectedLogs,
    selectLog: state.selectLog,
    deselectLog: state.deselectLog,
    clearSelection: state.clearSelection,
    toggleLogSelection: state.toggleLogSelection,
    getSelectedLogsCount: state.getSelectedLogsCount,
    isLogSelected: state.isLogSelected,
  }));

export const useInteractionLogsUI = () => 
  useInteractionLogsStore((state) => ({
    isFilterPanelOpen: state.isFilterPanelOpen,
    viewMode: state.viewMode,
    toggleFilterPanel: state.toggleFilterPanel,
    setViewMode: state.setViewMode,
  }));

export const useInteractionLogsLoading = () => 
  useInteractionLogsStore((state) => ({
    loadingStates: state.loadingStates,
    setLoadingState: state.setLoadingState,
  }));

export const useInteractionLogsPagination = () => 
  useInteractionLogsStore((state) => ({
    pagination: state.pagination,
    setPagination: state.setPagination,
    setPage: state.setPage,
    setPerPage: state.setPerPage,
  }));