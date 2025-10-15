


<div class="mobile-cards" x-data="mobileCards()">
    <!-- Card Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <template x-for="card in cards" :key="card.id">
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200 cursor-pointer"
                 @click="handleCardClick(card)"
                 @touchstart="handleTouchStart"
                 @touchend="handleTouchEnd">
                
                <!-- Card Header -->
                <div class="p-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <!-- Card Icon -->
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                                 :class="getCardIconClass(card.type)">
                                <i :class="card.icon" class="text-white text-lg"></i>
                            </div>
                            
                            <!-- Card Title -->
                            <div class="flex-1 min-w-0">
                                <h3 class="text-sm font-semibold text-gray-900 truncate" x-text="card.title"></h3>
                                <p class="text-xs text-gray-500 truncate" x-text="card.subtitle"></p>
                            </div>
                        </div>
                        
                        <!-- Card Status -->
                        <div class="flex items-center space-x-2">
                            <template x-if="card.status">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                      :class="getStatusClass(card.status)"
                                      x-text="card.status"></span>
                            </template>
                            
                            <!-- Card Menu -->
                            <div class="relative">
                                <button @click.stop="toggleCardMenu(card.id)"
                                        class="p-1 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100">
                                    <i class="fas fa-ellipsis-v text-sm"></i>
                                </button>
                                
                                <!-- Card Menu Dropdown -->
                                <div x-show="cardMenuOpen === card.id" 
                                     x-transition
                                     @click.away="cardMenuOpen = null"
                                     class="absolute right-0 top-8 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                    <div class="py-1">
                                        <template x-for="action in cardActions" :key="action.key">
                                            <button @click="handleCardAction(action, card)"
                                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i :class="action.icon" class="mr-3 text-gray-400"></i>
                                                <span x-text="action.label"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Card Content -->
                <div class="p-4">
                    <!-- Card Description -->
                    <p class="text-sm text-gray-600 mb-4" x-text="card.description"></p>
                    
                    <!-- Card Metrics -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <template x-for="metric in card.metrics" :key="metric.key">
                            <div class="text-center">
                                <div class="text-lg font-semibold text-gray-900" x-text="metric.value"></div>
                                <div class="text-xs text-gray-500" x-text="metric.label"></div>
                            </div>
                        </template>
                    </div>
                    
                    <!-- Card Progress -->
                    <template x-if="card.progress !== undefined">
                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-medium text-gray-700">Progress</span>
                                <span class="text-xs text-gray-500" x-text="card.progress + '%'"></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                     :style="`width: ${card.progress}%`"></div>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Card Tags -->
                    <template x-if="card.tags && card.tags.length > 0">
                        <div class="flex flex-wrap gap-1 mb-4">
                            <template x-for="tag in card.tags.slice(0, 3)" :key="tag">
                                <span class="inline-flex px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 rounded-full"
                                      x-text="tag"></span>
                            </template>
                            <template x-if="card.tags.length > 3">
                                <span class="inline-flex px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 rounded-full"
                                      x-text="`+${card.tags.length - 3} more`"></span>
                            </template>
                        </div>
                    </template>
                    
                    <!-- Card Footer -->
                    <div class="flex items-center justify-between text-xs text-gray-500">
                        <div class="flex items-center space-x-2">
                            <template x-if="card.assigned_to">
                                <div class="flex items-center space-x-1">
                                    <div class="w-4 h-4 bg-blue-600 rounded-full flex items-center justify-center">
                                        <span class="text-white text-xs font-medium" 
                                              x-text="getInitials(card.assigned_to)"></span>
                                    </div>
                                    <span x-text="card.assigned_to"></span>
                                </div>
                            </template>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <template x-if="card.due_date">
                                <div class="flex items-center space-x-1">
                                    <i class="fas fa-calendar text-xs"></i>
                                    <span x-text="formatDate(card.due_date)"></span>
                                </div>
                            </template>
                            
                            <template x-if="card.priority">
                                <div class="flex items-center space-x-1">
                                    <i class="fas fa-flag text-xs" 
                                       :class="getPriorityClass(card.priority)"></i>
                                    <span x-text="card.priority"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
    
    <!-- Empty State -->
    <div x-show="cards.length === 0" class="text-center py-12">
        <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No cards found</h3>
        <p class="text-gray-500 mb-4">Create your first card to get started</p>
        <button @click="createCard()" 
                class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            Create Card
        </button>
    </div>
    
    <!-- Load More Button -->
    <div x-show="hasMoreCards" class="text-center mt-6">
        <button @click="loadMoreCards()" 
                :disabled="loading"
                class="px-6 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-50">
            <span x-show="!loading">Load More</span>
            <span x-show="loading">Loading...</span>
        </button>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('mobileCards', () => ({
            // State
            cards: [],
            cardMenuOpen: null,
            loading: false,
            hasMoreCards: true,
            currentPage: 1,
            touchStartTime: 0,
            cardActions: [
                { key: 'view', label: 'View Details', icon: 'fas fa-eye', action: 'view' },
                { key: 'edit', label: 'Edit', icon: 'fas fa-edit', action: 'edit' },
                { key: 'duplicate', label: 'Duplicate', icon: 'fas fa-copy', action: 'duplicate' },
                { key: 'archive', label: 'Archive', icon: 'fas fa-archive', action: 'archive' },
                { key: 'delete', label: 'Delete', icon: 'fas fa-trash', action: 'delete' }
            ],
            
            // Initialize
            init() {
                this.loadCards();
                this.setupInfiniteScroll();
            },
            
            // Load Cards
            loadCards() {
                this.loading = true;
                
                // Mock data - this would be replaced with actual API calls
                const mockCards = [
                    {
                        id: 1,
                        title: 'Website Redesign',
                        subtitle: 'Project',
                        description: 'Complete redesign of company website with modern UI/UX',
                        type: 'project',
                        icon: 'fas fa-project-diagram',
                        status: 'Active',
                        progress: 75,
                        assigned_to: 'John Doe',
                        due_date: '2024-06-30',
                        priority: 'High',
                        tags: ['UI/UX', 'Frontend', 'Design'],
                        metrics: [
                            { key: 'tasks', value: '12', label: 'Tasks' },
                            { key: 'team', value: '5', label: 'Team' }
                        ]
                    },
                    {
                        id: 2,
                        title: 'Mobile App Development',
                        subtitle: 'Project',
                        description: 'iOS and Android mobile application development',
                        type: 'project',
                        icon: 'fas fa-mobile-alt',
                        status: 'Planning',
                        progress: 25,
                        assigned_to: 'Jane Smith',
                        due_date: '2024-08-31',
                        priority: 'Medium',
                        tags: ['Mobile', 'iOS', 'Android'],
                        metrics: [
                            { key: 'tasks', value: '8', label: 'Tasks' },
                            { key: 'team', value: '3', label: 'Team' }
                        ]
                    },
                    {
                        id: 3,
                        title: 'Database Migration',
                        subtitle: 'Task',
                        description: 'Migrate to new database system',
                        type: 'task',
                        icon: 'fas fa-database',
                        status: 'Completed',
                        progress: 100,
                        assigned_to: 'Mike Johnson',
                        due_date: '2024-03-15',
                        priority: 'Low',
                        tags: ['Database', 'Migration'],
                        metrics: [
                            { key: 'hours', value: '40', label: 'Hours' },
                            { key: 'files', value: '15', label: 'Files' }
                        ]
                    }
                ];
                
                // Simulate API delay
                setTimeout(() => {
                    this.cards = [...this.cards, ...mockCards];
                    this.loading = false;
                    this.hasMoreCards = this.cards.length < 20; // Mock limit
                }, 1000);
            },
            
            // Load More Cards
            loadMoreCards() {
                this.currentPage++;
                this.loadCards();
            },
            
            // Handle Card Click
            handleCardClick(card) {
                console.log('Card clicked:', card);
                // Navigate to card details or open modal
                window.location.href = `/app/cards/${card.id}`;
            },
            
            // Handle Card Action
            handleCardAction(action, card) {
                this.cardMenuOpen = null;
                
                switch (action.action) {
                    case 'view':
                        this.viewCard(card);
                        break;
                    case 'edit':
                        this.editCard(card);
                        break;
                    case 'duplicate':
                        this.duplicateCard(card);
                        break;
                    case 'archive':
                        this.archiveCard(card);
                        break;
                    case 'delete':
                        this.deleteCard(card);
                        break;
                }
            },
            
            // Toggle Card Menu
            toggleCardMenu(cardId) {
                this.cardMenuOpen = this.cardMenuOpen === cardId ? null : cardId;
            },
            
            // Card Actions
            viewCard(card) {
                console.log('Viewing card:', card);
                window.location.href = `/app/cards/${card.id}`;
            },
            
            editCard(card) {
                console.log('Editing card:', card);
                window.location.href = `/app/cards/${card.id}/edit`;
            },
            
            duplicateCard(card) {
                console.log('Duplicating card:', card);
                // Duplicate card logic
            },
            
            archiveCard(card) {
                if (confirm('Are you sure you want to archive this card?')) {
                    console.log('Archiving card:', card);
                    this.cards = this.cards.filter(c => c.id !== card.id);
                }
            },
            
            deleteCard(card) {
                if (confirm('Are you sure you want to delete this card?')) {
                    console.log('Deleting card:', card);
                    this.cards = this.cards.filter(c => c.id !== card.id);
                }
            },
            
            createCard() {
                console.log('Creating new card');
                window.location.href = '/app/cards/create';
            },
            
            // Touch Handlers
            handleTouchStart(e) {
                this.touchStartTime = Date.now();
            },
            
            handleTouchEnd(e) {
                const touchDuration = Date.now() - this.touchStartTime;
                if (touchDuration > 500) { // Long press
                    // Show context menu or selection mode
                    console.log('Long press detected');
                }
            },
            
            // Setup Infinite Scroll
            setupInfiniteScroll() {
                window.addEventListener('scroll', () => {
                    if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 1000) {
                        if (this.hasMoreCards && !this.loading) {
                            this.loadMoreCards();
                        }
                    }
                });
            },
            
            // Utility Functions
            getCardIconClass(type) {
                const classes = {
                    'project': 'bg-blue-600',
                    'task': 'bg-green-600',
                    'document': 'bg-purple-600',
                    'user': 'bg-orange-600'
                };
                return classes[type] || 'bg-gray-600';
            },
            
            getStatusClass(status) {
                const classes = {
                    'Active': 'bg-green-100 text-green-800',
                    'Planning': 'bg-yellow-100 text-yellow-800',
                    'Completed': 'bg-blue-100 text-blue-800',
                    'Cancelled': 'bg-red-100 text-red-800'
                };
                return classes[status] || 'bg-gray-100 text-gray-800';
            },
            
            getPriorityClass(priority) {
                const classes = {
                    'High': 'text-red-500',
                    'Medium': 'text-yellow-500',
                    'Low': 'text-green-500'
                };
                return classes[priority] || 'text-gray-500';
            },
            
            formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString();
            },
            
            getInitials(name) {
                return name.split(' ').map(n => n[0]).join('').toUpperCase();
            }
        }));
    });
</script>

<style>
    /* Mobile card styles */
    .mobile-cards {
        width: 100%;
    }
    
    /* Card hover effects */
    .mobile-cards .bg-white:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    
    /* Card grid responsive */
    @media (max-width: 640px) {
        .mobile-cards .grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (min-width: 641px) and (max-width: 1024px) {
        .mobile-cards .grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (min-width: 1025px) {
        .mobile-cards .grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    
    @media (min-width: 1280px) {
        .mobile-cards .grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }
    
    /* Card animations */
    .mobile-cards .bg-white {
        transition: all 0.2s ease-in-out;
    }
    
    /* Progress bar animation */
    .mobile-cards .bg-blue-600 {
        transition: width 0.3s ease-in-out;
    }
    
    /* Badge animations */
    .mobile-cards .inline-flex {
        transition: all 0.2s ease-in-out;
    }
    
    /* Touch feedback */
    .mobile-cards .bg-white:active {
        transform: scale(0.98);
    }
</style>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/mobile/mobile-cards.blade.php ENDPATH**/ ?>