<script>
function documentsPage() {
    return {
        loading: true,
        error: null,
        documents: [],
        filteredDocuments: [],
        searchQuery: '',
        showFilters: false,
        viewMode: 'grid', // 'grid' or 'list'
        showUploadModal: false,
        dragover: false,
        selectedFiles: [],
        uploading: false,
        uploadProgress: 0,
        filters: {
            fileType: '',
            status: '',
            project: '',
            dateRange: ''
        },
        activeFilters: [],
        activeFiltersCount: 0,
        searchTimeout: null,

        async init() {
            console.log('🚀 Documents page init started');
            await this.loadDocuments();
        },

        async loadDocuments() {
            try {
                this.loading = true;
                this.error = null;
                console.log('📊 Loading documents data...');
                
                // Get auth token
                const token = localStorage.getItem('auth_token') || 'eyJ1c2VyX2lkIjoyOTE0LCJlbWFpbCI6InN1cGVyYWRtaW5AemVuYS5jb20iLCJyb2xlIjoic3VwZXJfYWRtaW4iLCJleHBpcmVzIjoxNzU4NjE2OTIwfQ==';
                
                // Fetch real data from API (mock for now)
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                // Mock data
                this.documents = [
                    {
                        id: 1,
                        name: 'Project Requirements.pdf',
                        description: 'Detailed project requirements document',
                        fileType: 'pdf',
                        mimeType: 'application/pdf',
                        size: 2048576, // 2MB
                        status: 'approved',
                        uploadedAt: '2024-01-15',
                        uploadedBy: 'John Doe',
                        project: 'Website Redesign'
                    },
                    {
                        id: 2,
                        name: 'Design Mockups.pptx',
                        description: 'UI/UX design mockups presentation',
                        fileType: 'ppt',
                        mimeType: 'application/vnd.ms-powerpoint',
                        size: 5242880, // 5MB
                        status: 'review',
                        uploadedAt: '2024-01-14',
                        uploadedBy: 'Jane Smith',
                        project: 'Website Redesign'
                    },
                    {
                        id: 3,
                        name: 'Budget Analysis.xlsx',
                        description: 'Project budget breakdown and analysis',
                        fileType: 'xls',
                        mimeType: 'application/vnd.ms-excel',
                        size: 1048576, // 1MB
                        status: 'draft',
                        uploadedAt: '2024-01-13',
                        uploadedBy: 'Mike Johnson',
                        project: 'Mobile App'
                    },
                    {
                        id: 4,
                        name: 'Team Photo.jpg',
                        description: 'Team photo for marketing materials',
                        fileType: 'image',
                        mimeType: 'image/jpeg',
                        size: 3145728, // 3MB
                        status: 'approved',
                        uploadedAt: '2024-01-12',
                        uploadedBy: 'Sarah Wilson',
                        project: 'Marketing Campaign'
                    },
                    {
                        id: 5,
                        name: 'API Documentation.docx',
                        description: 'Complete API documentation',
                        fileType: 'doc',
                        mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        size: 1572864, // 1.5MB
                        status: 'review',
                        uploadedAt: '2024-01-11',
                        uploadedBy: 'Tom Brown',
                        project: 'Mobile App'
                    },
                    {
                        id: 6,
                        name: 'Demo Video.mp4',
                        description: 'Product demonstration video',
                        fileType: 'video',
                        mimeType: 'video/mp4',
                        size: 52428800, // 50MB
                        status: 'approved',
                        uploadedAt: '2024-01-10',
                        uploadedBy: 'Lisa Davis',
                        project: 'Marketing Campaign'
                    }
                ];
                
                this.applyFilters();
                this.loading = false;
                console.log('✅ Documents data loaded successfully');
                
            } catch (error) {
                console.error('❌ Error loading documents data:', error);
                this.error = error.message;
                this.loading = false;
            }
        },

        debounceSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.applyFilters();
            }, 300);
        },

        applyFilters() {
            let filtered = [...this.documents];
            
            // Apply search filter
            if (this.searchQuery) {
                filtered = filtered.filter(doc => 
                    doc.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    doc.description.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    doc.uploadedBy.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    doc.project.toLowerCase().includes(this.searchQuery.toLowerCase())
                );
            }
            
            // Apply file type filter
            if (this.filters.fileType) {
                filtered = filtered.filter(doc => doc.fileType === this.filters.fileType);
            }
            
            // Apply status filter
            if (this.filters.status) {
                filtered = filtered.filter(doc => doc.status === this.filters.status);
            }
            
            // Apply project filter
            if (this.filters.project) {
                filtered = filtered.filter(doc => doc.project.toLowerCase().includes(this.filters.project.toLowerCase()));
            }
            
            // Apply date range filter
            if (this.filters.dateRange) {
                const now = new Date();
                const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                
                filtered = filtered.filter(doc => {
                    const docDate = new Date(doc.uploadedAt);
                    
                    switch (this.filters.dateRange) {
                        case 'today':
                            return docDate >= today;
                        case 'week':
                            const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                            return docDate >= weekAgo;
                        case 'month':
                            const monthAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
                            return docDate >= monthAgo;
                        case 'year':
                            const yearAgo = new Date(today.getTime() - 365 * 24 * 60 * 60 * 1000);
                            return docDate >= yearAgo;
                        default:
                            return true;
                    }
                });
            }
            
            this.filteredDocuments = filtered;
            this.updateActiveFilters();
        },

        updateActiveFilters() {
            this.activeFilters = [];
            
            if (this.filters.fileType) {
                this.activeFilters.push({
                    key: 'fileType',
                    label: 'File Type',
                    value: this.filters.fileType
                });
            }
            
            if (this.filters.status) {
                this.activeFilters.push({
                    key: 'status',
                    label: 'Status',
                    value: this.filters.status
                });
            }
            
            if (this.filters.project) {
                this.activeFilters.push({
                    key: 'project',
                    label: 'Project',
                    value: this.filters.project
                });
            }
            
            if (this.filters.dateRange) {
                this.activeFilters.push({
                    key: 'dateRange',
                    label: 'Date Range',
                    value: this.filters.dateRange
                });
            }
            
            this.activeFiltersCount = this.activeFilters.length;
        },

        removeFilter(filterKey) {
            this.filters[filterKey] = '';
            this.applyFilters();
        },

        clearAllFilters() {
            this.filters = {
                fileType: '',
                status: '',
                project: '',
                dateRange: ''
            };
            this.searchQuery = '';
            this.applyFilters();
        },

        toggleView() {
            this.viewMode = this.viewMode === 'grid' ? 'list' : 'grid';
        },

        getFileIcon(mimeType) {
            if (mimeType.includes('pdf')) return 'fas fa-file-pdf file-pdf';
            if (mimeType.includes('word') || mimeType.includes('document')) return 'fas fa-file-word file-doc';
            if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'fas fa-file-excel file-xls';
            if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'fas fa-file-powerpoint file-ppt';
            if (mimeType.includes('image')) return 'fas fa-file-image file-image';
            if (mimeType.includes('video')) return 'fas fa-file-video file-video';
            if (mimeType.includes('audio')) return 'fas fa-file-audio file-audio';
            if (mimeType.includes('zip') || mimeType.includes('rar')) return 'fas fa-file-archive file-archive';
            return 'fas fa-file file-other';
        },

        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        viewDocument(documentId) {
            console.log('Viewing document:', documentId);
            window.location.href = `/app/documents/${documentId}`;
        },

        downloadDocument(documentId) {
            console.log('Downloading document:', documentId);
            // Implement download functionality
        },

        shareDocument(documentId) {
            console.log('Sharing document:', documentId);
            // Implement share functionality
        }
    }
}
</script>
