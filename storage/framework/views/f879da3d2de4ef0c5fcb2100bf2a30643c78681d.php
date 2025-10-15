


<?php
    $user = Auth::user();
    $tenant = $user->tenant ?? null;
    
    // Prepare filter options
    $statusOptions = [
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'archived', 'label' => 'Archived'],
        ['value' => 'pending_approval', 'label' => 'Pending Approval']
    ];
    
    $categoryOptions = [
        ['value' => 'requirements', 'label' => 'Requirements'],
        ['value' => 'design', 'label' => 'Design'],
        ['value' => 'contracts', 'label' => 'Contracts'],
        ['value' => 'reports', 'label' => 'Reports'],
        ['value' => 'presentations', 'label' => 'Presentations'],
        ['value' => 'other', 'label' => 'Other']
    ];
    
    $fileTypeOptions = [
        ['value' => 'pdf', 'label' => 'PDF'],
        ['value' => 'doc', 'label' => 'Word Documents'],
        ['value' => 'xls', 'label' => 'Excel Files'],
        ['value' => 'ppt', 'label' => 'PowerPoint'],
        ['value' => 'image', 'label' => 'Images'],
        ['value' => 'other', 'label' => 'Other']
    ];
    
    $projectOptions = collect($projects ?? [])->map(function($project) {
        return ['value' => $project->id ?? '', 'label' => $project->name ?? 'Unknown'];
    })->toArray();
    
    // Filter configuration
    $filters = [
        [
            'key' => 'status',
            'label' => 'Status',
            'type' => 'select',
            'options' => $statusOptions,
            'placeholder' => 'All Statuses'
        ],
        [
            'key' => 'category',
            'label' => 'Category',
            'type' => 'select',
            'options' => $categoryOptions,
            'placeholder' => 'All Categories'
        ],
        [
            'key' => 'file_type',
            'label' => 'File Type',
            'type' => 'select',
            'options' => $fileTypeOptions,
            'placeholder' => 'All File Types'
        ],
        [
            'key' => 'project_id',
            'label' => 'Project',
            'type' => 'select',
            'options' => $projectOptions,
            'placeholder' => 'All Projects'
        ],
        [
            'key' => 'upload_date',
            'label' => 'Upload Date',
            'type' => 'date-range'
        ]
    ];
    
    // Sort options
    $sortOptions = [
        ['value' => 'name', 'label' => 'Document Name'],
        ['value' => 'status', 'label' => 'Status'],
        ['value' => 'category', 'label' => 'Category'],
        ['value' => 'file_size', 'label' => 'File Size'],
        ['value' => 'uploaded_at', 'label' => 'Upload Date'],
        ['value' => 'download_count', 'label' => 'Download Count'],
        ['value' => 'version', 'label' => 'Version']
    ];
    
    // Bulk actions
    $bulkActions = [
        [
            'label' => 'Change Status',
            'icon' => 'fas fa-edit',
            'handler' => 'bulkChangeStatus()'
        ],
        [
            'label' => 'Archive',
            'icon' => 'fas fa-archive',
            'handler' => 'bulkArchive()'
        ],
        [
            'label' => 'Export',
            'icon' => 'fas fa-download',
            'handler' => 'bulkExport()'
        ],
        [
            'label' => 'Delete',
            'icon' => 'fas fa-trash',
            'handler' => 'bulkDelete()'
        ]
    ];
    
    // Breadcrumbs
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Documents', 'url' => null]
    ];
    
    // Page actions
    $actions = '
        <div class="flex items-center space-x-3">
            <button onclick="exportDocuments()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-download mr-2"></i>Export
            </button>
            <button onclick="openModal(\'upload-document-modal\')" class="btn bg-blue-600 text-white hover:bg-blue-700">
                <i class="fas fa-upload mr-2"></i>Upload Document
            </button>
        </div>
    ';
    
    // Prepare table data
    $tableData = collect($documents ?? [])->map(function($document) {
        return [
            'id' => $document->id,
            'name' => $document->name ?? $document->original_name ?? 'Unknown',
            'description' => $document->description ?? '',
            'file_type' => $document->file_type ?? $document->type ?? 'unknown',
            'file_size' => $document->file_size ?? $document->size ?? '0 MB',
            'status' => $document->status ?? 'active',
            'category' => $document->category ?? 'other',
            'project' => $document->project->name ?? 'No Project',
            'uploader' => $document->uploader->name ?? $document->uploaded_by ?? 'Unknown',
            'version' => $document->version ?? '1.0',
            'download_count' => $document->download_count ?? 0,
            'uploaded_at' => $document->uploaded_at ? $document->uploaded_at->format('M d, Y') : ($document->created_at ? $document->created_at->format('M d, Y') : '-'),
            'updated_at' => $document->updated_at->format('M d, Y')
        ];
    });
    
    // Table columns configuration
    $columns = [
        ['key' => 'name', 'label' => 'Document Name', 'sortable' => true, 'primary' => true],
        ['key' => 'file_type', 'label' => 'Type', 'sortable' => true, 'type' => 'badge'],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ['key' => 'category', 'label' => 'Category', 'sortable' => true, 'type' => 'badge'],
        ['key' => 'project', 'label' => 'Project', 'sortable' => true],
        ['key' => 'uploader', 'label' => 'Uploader', 'sortable' => true],
        ['key' => 'file_size', 'label' => 'Size', 'sortable' => true],
        ['key' => 'version', 'label' => 'Version', 'sortable' => true],
        ['key' => 'download_count', 'label' => 'Downloads', 'sortable' => true, 'type' => 'number'],
        ['key' => 'uploaded_at', 'label' => 'Uploaded', 'sortable' => true, 'type' => 'date']
    ];
?>

<?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.layout-wrapper','data' => ['title' => 'Documents','subtitle' => 'Manage and organize your documents','breadcrumbs' => $breadcrumbs,'actions' => $actions]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.layout-wrapper'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Documents','subtitle' => 'Manage and organize your documents','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($breadcrumbs),'actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($actions)]); ?>
    
    
    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.filter-bar','data' => ['search' => true,'searchPlaceholder' => 'Search documents...','filters' => $filters,'sortOptions' => $sortOptions,'viewModes' => ['table', 'grid', 'list'],'currentViewMode' => 'table','bulkActions' => $bulkActions]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.filter-bar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['search' => true,'search-placeholder' => 'Search documents...','filters' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($filters),'sort-options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($sortOptions),'view-modes' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['table', 'grid', 'list']),'current-view-mode' => 'table','bulk-actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bulkActions)]); ?>
        
        
         <?php $__env->slot('actions', null, []); ?> 
            <button onclick="refreshDocuments()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
         <?php $__env->endSlot(); ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
    
    
    <div class="mt-6">
        <?php if($tableData->count() > 0): ?>
            <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.table-standardized','data' => ['data' => $tableData,'columns' => $columns,'sortable' => true,'selectable' => true,'pagination' => true,'perPage' => 15,'search' => true,'export' => true,'bulkActions' => $bulkActions,'responsive' => true,'loading' => false,'emptyMessage' => 'No documents found','emptyDescription' => 'Upload your first document to get started','emptyActionText' => 'Upload Document','emptyActionHandler' => 'openModal(\'upload-document-modal\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.table-standardized'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tableData),'columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($columns),'sortable' => true,'selectable' => true,'pagination' => true,'per-page' => 15,'search' => true,'export' => true,'bulk-actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bulkActions),'responsive' => true,'loading' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false),'empty-message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('No documents found'),'empty-description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Upload your first document to get started'),'empty-action-text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Upload Document'),'empty-action-handler' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('openModal(\'upload-document-modal\')')]); ?>
                
                
                 <?php $__env->slot('cell-file_type', null, []); ?> 
                    <?php
                        $fileType = $row['file_type'] ?? 'unknown';
                        $fileTypeClasses = [
                            'pdf' => 'bg-red-100 text-red-800',
                            'doc' => 'bg-blue-100 text-blue-800',
                            'docx' => 'bg-blue-100 text-blue-800',
                            'xls' => 'bg-green-100 text-green-800',
                            'xlsx' => 'bg-green-100 text-green-800',
                            'ppt' => 'bg-orange-100 text-orange-800',
                            'pptx' => 'bg-orange-100 text-orange-800',
                            'jpg' => 'bg-purple-100 text-purple-800',
                            'jpeg' => 'bg-purple-100 text-purple-800',
                            'png' => 'bg-purple-100 text-purple-800',
                            'gif' => 'bg-purple-100 text-purple-800',
                            'txt' => 'bg-gray-100 text-gray-800',
                            'unknown' => 'bg-gray-100 text-gray-800'
                        ];
                        $fileTypeClass = $fileTypeClasses[$fileType] ?? $fileTypeClasses['unknown'];
                    ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($fileTypeClass); ?>">
                        <?php echo e(strtoupper($fileType)); ?>

                    </span>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('cell-status', null, []); ?> 
                    <?php
                        $status = $row['status'] ?? 'unknown';
                        $statusClasses = [
                            'active' => 'bg-green-100 text-green-800',
                            'draft' => 'bg-yellow-100 text-yellow-800',
                            'archived' => 'bg-gray-100 text-gray-800',
                            'pending_approval' => 'bg-orange-100 text-orange-800',
                            'unknown' => 'bg-gray-100 text-gray-800'
                        ];
                        $statusClass = $statusClasses[$status] ?? $statusClasses['unknown'];
                    ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($statusClass); ?>">
                        <?php echo e(ucfirst(str_replace('_', ' ', $status))); ?>

                    </span>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('cell-category', null, []); ?> 
                    <?php
                        $category = $row['category'] ?? 'other';
                        $categoryClasses = [
                            'requirements' => 'bg-blue-100 text-blue-800',
                            'design' => 'bg-purple-100 text-purple-800',
                            'contracts' => 'bg-green-100 text-green-800',
                            'reports' => 'bg-orange-100 text-orange-800',
                            'presentations' => 'bg-pink-100 text-pink-800',
                            'other' => 'bg-gray-100 text-gray-800'
                        ];
                        $categoryClass = $categoryClasses[$category] ?? $categoryClasses['other'];
                    ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($categoryClass); ?>">
                        <?php echo e(ucfirst($category)); ?>

                    </span>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('cell-file_size', null, []); ?> 
                    <span class="text-sm font-medium text-gray-900">
                        <?php echo e($row['file_size']); ?>

                    </span>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('cell-download_count', null, []); ?> 
                    <div class="flex items-center">
                        <i class="fas fa-download text-gray-400 mr-1"></i>
                        <span class="text-sm font-medium text-gray-900">
                            <?php echo e($row['download_count']); ?>

                        </span>
                    </div>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('row-actions', null, []); ?> 
                    <div class="flex items-center space-x-2">
                        <button onclick="downloadDocument('<?php echo e($row['id']); ?>')" 
                                class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            <i class="fas fa-download mr-1"></i>Download
                        </button>
                        <button onclick="viewDocument('<?php echo e($row['id']); ?>')" 
                                class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                            <i class="fas fa-eye mr-1"></i>View
                        </button>
                        <button onclick="editDocument('<?php echo e($row['id']); ?>')" 
                                class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </button>
                        <button onclick="showVersionHistory('<?php echo e($row['id']); ?>')" 
                                class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                            <i class="fas fa-history mr-1"></i>Versions
                        </button>
                        <button onclick="deleteDocument('<?php echo e($row['id']); ?>')" 
                                class="text-red-600 hover:text-red-800 text-sm font-medium">
                            <i class="fas fa-trash mr-1"></i>Delete
                        </button>
                    </div>
                 <?php $__env->endSlot(); ?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
        <?php else: ?>
            
            <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.empty-state','data' => ['icon' => 'fas fa-file-alt','title' => 'No documents found','description' => 'Upload your first document to start organizing your files.','actionText' => 'Upload Document','actionIcon' => 'fas fa-upload','actionHandler' => 'openModal(\'upload-document-modal\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'fas fa-file-alt','title' => 'No documents found','description' => 'Upload your first document to start organizing your files.','action-text' => 'Upload Document','action-icon' => 'fas fa-upload','action-handler' => 'openModal(\'upload-document-modal\')']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
        <?php endif; ?>
    </div>
    
    
    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.modal','data' => ['id' => 'upload-document-modal','title' => 'Upload Document','size' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'upload-document-modal','title' => 'Upload Document','size' => 'lg']); ?>
        
        <form id="upload-document-form" @submit.prevent="uploadDocument()" enctype="multipart/form-data">
            <div class="space-y-6">
                
                <div>
                    <label for="document-file" class="form-label">Select File *</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition-colors">
                        <div class="space-y-1 text-center">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400"></i>
                            <div class="flex text-sm text-gray-600">
                                <label for="document-file" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                    <span>Upload a file</span>
                                    <input id="document-file" 
                                           name="file" 
                                           type="file" 
                                           required
                                           class="sr-only"
                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif"
                                           @change="handleFileSelect($event)">
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500">PDF, DOC, XLS, PPT, TXT, JPG, PNG up to 10MB</p>
                        </div>
                    </div>
                    <div id="file-info" class="mt-2 hidden">
                        <div class="flex items-center p-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-file text-blue-600 mr-2"></i>
                            <span id="file-name" class="text-sm font-medium text-blue-900"></span>
                            <span id="file-size" class="text-sm text-blue-700 ml-2"></span>
                        </div>
                    </div>
                </div>
                
                
                <div>
                    <label for="document-name" class="form-label">Document Name</label>
                    <input type="text" 
                           id="document-name" 
                           name="name" 
                           class="form-input"
                           placeholder="Enter document name (optional)">
                    <p class="text-sm text-gray-500 mt-1">Leave empty to use original filename</p>
                </div>
                
                
                <div>
                    <label for="document-description" class="form-label">Description</label>
                    <textarea id="document-description" 
                              name="description" 
                              rows="3"
                              class="form-textarea"
                              placeholder="Enter document description"></textarea>
                </div>
                
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="document-category" class="form-label">Category</label>
                        <select id="document-category" name="category" class="form-select">
                            <option value="other">Other</option>
                            <option value="requirements">Requirements</option>
                            <option value="design">Design</option>
                            <option value="contracts">Contracts</option>
                            <option value="reports">Reports</option>
                            <option value="presentations">Presentations</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="document-project" class="form-label">Project</label>
                        <select id="document-project" name="project_id" class="form-select">
                            <option value="">No Project</option>
                            <?php $__currentLoopData = $projects ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($project->id); ?>"><?php echo e($project->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                </div>
                
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="document-status" class="form-label">Status</label>
                        <select id="document-status" name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="draft">Draft</option>
                            <option value="pending_approval">Pending Approval</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="document-visibility" class="form-label">Visibility</label>
                        <select id="document-visibility" name="visibility" class="form-select">
                            <option value="private">Private</option>
                            <option value="team">Team</option>
                            <option value="public">Public</option>
                        </select>
                    </div>
                </div>
            </div>
            
            
            <div class="flex items-center justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                <button type="button" 
                        onclick="closeModal('upload-document-modal')"
                        class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                    Cancel
                </button>
                <button type="submit" 
                        class="btn bg-blue-600 text-white hover:bg-blue-700">
                    <i class="fas fa-upload mr-2"></i>Upload Document
                </button>
            </div>
        </form>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
    
    
    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.modal','data' => ['id' => 'version-history-modal','title' => 'Version History','size' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'version-history-modal','title' => 'Version History','size' => 'lg']); ?>
        
        <div id="version-history-content">
            
        </div>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>

<?php $__env->startPush('scripts'); ?>
<script>
let selectedFile = null;

function refreshDocuments() {
    window.location.reload();
}

function exportDocuments() {
    // Export documents functionality
    alert('Export documents functionality would be implemented here');
}

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (file) {
        selectedFile = file;
        document.getElementById('file-name').textContent = file.name;
        document.getElementById('file-size').textContent = formatFileSize(file.size);
        document.getElementById('file-info').classList.remove('hidden');
        
        // Auto-fill document name if empty
        const nameInput = document.getElementById('document-name');
        if (!nameInput.value) {
            nameInput.value = file.name.replace(/\.[^/.]+$/, ""); // Remove extension
        }
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function uploadDocument() {
    const form = document.getElementById('upload-document-form');
    const formData = new FormData(form);
    
    // Add tenant_id
    formData.append('tenant_id', '<?php echo e($user->tenant_id); ?>');
    formData.append('user_id', '<?php echo e($user->id); ?>');
    
    // Submit via API
    fetch('/api/v1/app/documents', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Authorization': 'Bearer ' + getAuthToken()
        },
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            closeModal('upload-document-modal');
            window.location.reload();
        } else {
            alert('Error uploading document: ' + (result.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error uploading document');
    });
}

function downloadDocument(documentId) {
    fetch(`/api/v1/app/documents/${documentId}/download`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Authorization': 'Bearer ' + getAuthToken()
        }
    })
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        throw new Error('Download failed');
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'document';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    })
    .catch(error => {
        console.error('Error downloading document:', error);
        alert('Failed to download document');
    });
}

function viewDocument(documentId) {
    // Open document in new tab or modal viewer
    window.open(`/api/v1/app/documents/${documentId}/view`, '_blank');
}

function editDocument(documentId) {
    // Show edit modal with document data
    alert('Edit document functionality would be implemented here');
}

function showVersionHistory(documentId) {
    // Load version history
    fetch(`/api/v1/app/documents/${documentId}/versions`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Authorization': 'Bearer ' + getAuthToken()
        }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            displayVersionHistory(result.data);
            openModal('version-history-modal');
        } else {
            alert('Failed to load version history');
        }
    })
    .catch(error => {
        console.error('Error loading version history:', error);
        alert('Failed to load version history');
    });
}

function displayVersionHistory(versions) {
    const content = document.getElementById('version-history-content');
    content.innerHTML = `
        <div class="space-y-4">
            ${versions.map(version => `
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-file text-blue-600"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900">Version ${version.version}</h4>
                            <p class="text-sm text-gray-500">${version.created_at}</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="downloadVersion('${version.id}')" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-download"></i>
                        </button>
                        <button onclick="revertToVersion('${version.id}')" class="text-green-600 hover:text-green-800">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function downloadVersion(versionId) {
    alert('Download version: ' + versionId);
}

function revertToVersion(versionId) {
    if (confirm('Are you sure you want to revert to this version?')) {
        alert('Revert to version: ' + versionId);
    }
}

function deleteDocument(documentId) {
    if (confirm('Are you sure you want to delete this document?')) {
        fetch(`/api/v1/app/documents/${documentId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Authorization': 'Bearer ' + getAuthToken()
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                window.location.reload();
            } else {
                alert('Error deleting document: ' + (result.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting document');
        });
    }
}

function bulkChangeStatus() {
    alert('Bulk change status functionality would be implemented here');
}

function bulkArchive() {
    alert('Bulk archive functionality would be implemented here');
}

function bulkExport() {
    alert('Bulk export functionality would be implemented here');
}

function bulkDelete() {
    alert('Bulk delete functionality would be implemented here');
}

function getAuthToken() {
    // Get auth token from localStorage or session
    return localStorage.getItem('auth_token') || '';
}

// Listen for filter events
document.addEventListener('filter-search', (e) => {
    console.log('Search:', e.detail.query);
    // Implement search functionality
});

document.addEventListener('filter-apply', (e) => {
    console.log('Filters:', e.detail.filters);
    // Implement filter functionality
});

document.addEventListener('filter-sort', (e) => {
    console.log('Sort:', e.detail.sortBy, e.detail.sortDirection);
    // Implement sort functionality
});

document.addEventListener('filter-view-mode', (e) => {
    console.log('View mode:', e.detail.viewMode);
    // Implement view mode functionality
});
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/documents/index.blade.php ENDPATH**/ ?>