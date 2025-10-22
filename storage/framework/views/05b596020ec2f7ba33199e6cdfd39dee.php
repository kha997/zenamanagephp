<?php $__env->startSection('title', 'Task Details'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8" data-testid="task-detail">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800"><?php echo e($task->name); ?></h1>
                <p class="text-gray-600 mt-2"><?php echo e($task->project->name ?? 'No Project'); ?></p>
            </div>
            <div class="flex space-x-3">
                <a href="<?php echo e(route('app.tasks.edit', $task->id)); ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                    <i class="fas fa-edit mr-2"></i> Edit Task
                </a>
                <a href="<?php echo e(route('app.tasks.index')); ?>" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Tasks
                </a>
            </div>
        </div>

        <!-- Task Details Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" x-data="taskDetail('<?php echo e((string) $task->id); ?>')">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Description -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Description</h2>
                    <p class="text-gray-600 leading-relaxed"><?php echo e($task->description ?? 'No description provided.'); ?></p>
                </div>

                <!-- Comments Section -->
                <div class="bg-white rounded-lg shadow-md p-6" data-testid="comments-section">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">Comments</h2>
                        <span class="text-sm text-gray-500" x-text="`${allComments.length} comments`"></span>
                    </div>

                    <!-- Comment Form -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Add Comment</label>
                                <textarea x-model="newComment.content" 
                                          data-testid="comment-content"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                          rows="3"
                                          placeholder="Write your comment here..."></textarea>
                            </div>
                            
                            <div class="flex items-center space-x-4">
                                <select x-model="newComment.type" 
                                        data-testid="comment-type"
                                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="general">General</option>
                                    <option value="internal">Internal</option>
                                </select>
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           x-model="newComment.is_internal"
                                           id="is_internal" 
                                           class="mr-2">
                                    <label for="is_internal" class="text-sm text-gray-700">Internal Only</label>
                                </div>
                                </div>
                                
                            <div class="flex justify-end">
                                <button @click="submitComment()" 
                                        data-testid="submit-comment"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    Add Comment
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Comments List -->
                    <div class="space-y-4" data-testid="comments-container">
                        <template x-for="comment in visibleComments" :key="comment.id">
                            <div class="border border-gray-200 rounded-lg p-4" data-testid="comment-item">
                                    <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                            <span class="text-white text-sm font-medium" x-text="comment.user?.name?.charAt(0) || 'U'"></span>
                                        </div>
                                        <div class="flex-1">
                                        <div class="flex items-center space-x-2 mb-2">
                                                <h4 class="text-sm font-medium text-gray-900" x-text="comment.user?.name || 'Unknown User'"></h4>
                                                <span x-show="comment.is_internal" class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Internal</span>
                                            <span class="text-xs text-gray-500" x-text="formatDate(comment.created_at)"></span>
                                        </div>
                                        <p class="text-sm text-gray-700 mb-3" x-text="comment.content"></p>
                                        
                                        <!-- Comment Actions -->
                                        <div class="flex items-center space-x-2 text-xs">
                                            <button @click="replyingTo = comment.id" 
                                                    data-testid="reply-button"
                                                    class="text-blue-600 hover:text-blue-800">Reply</button>
                                            <button x-show="comment.user?.id === currentUserId" 
                                                    @click="editComment(comment.id)"
                                                    data-testid="edit-button"
                                                    class="text-gray-600 hover:text-gray-800">Edit</button>
                                            <button x-show="comment.user?.id === currentUserId" 
                                                    @click="deleteComment(comment.id)"
                                                    data-testid="delete-button"
                                                    class="text-red-600 hover:text-red-800">Delete</button>
                                        </div>
                                        
                                        <!-- Reply Form -->
                                        <div x-show="replyingTo === comment.id" class="mt-3 p-3 bg-gray-50 rounded-lg">
                                            <div class="space-y-2">
                                                <textarea x-model="replyContent" 
                                                          data-testid="reply-content"
                                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                          rows="2"
                                                          placeholder="Write your reply..."></textarea>
                                                <div class="flex items-center space-x-2">
                                                    <input type="checkbox" 
                                                           x-model="replyIsInternal" 
                                                           id="reply_internal" 
                                                           class="mr-1">
                                                    <label for="reply_internal" class="text-xs text-gray-700">Internal Reply</label>
                                                </div>
                                                <div class="flex space-x-2">
                                                    <button @click="submitReply()" 
                                                            data-testid="submit-reply"
                                                            class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">Reply</button>
                                                    <button @click="cancelReply()" 
                                                            class="px-3 py-1 bg-gray-600 text-white text-xs rounded hover:bg-gray-700">Cancel</button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Edit Form -->
                                        <div x-show="editingComment === comment.id" class="mt-3 p-3 bg-gray-50 rounded-lg">
                                            <div class="space-y-2">
                                                <textarea x-model="editContent" 
                                                          data-testid="edit-comment-content"
                                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                          rows="3"></textarea>
                                                <div class="flex space-x-2">
                                                    <button @click="saveEdit()" 
                                                            data-testid="save-edit"
                                                            class="px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700">Save</button>
                                                    <button @click="cancelEdit()" 
                                                            class="px-3 py-1 bg-gray-600 text-white text-xs rounded hover:bg-gray-700">Cancel</button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Replies -->
                                        <template x-for="reply in comment.replies" :key="reply.id">
                                            <div class="mt-3 ml-8 p-3 bg-gray-50 rounded-lg" data-testid="comment-reply">
                                                <div class="flex items-start space-x-2">
                                                    <div class="w-6 h-6 bg-gray-400 rounded-full flex items-center justify-center">
                                                        <span class="text-white text-xs font-medium" x-text="reply.user?.name?.charAt(0) || 'R'"></span>
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="flex items-center space-x-2">
                                                            <h5 class="text-xs font-medium text-gray-900" x-text="reply.user?.name || 'Unknown User'"></h5>
                                                            <span x-show="reply.is_internal" class="text-xs bg-yellow-100 text-yellow-800 px-1 py-0.5 rounded">Internal</span>
                                                        </div>
                                                        <p class="text-xs text-gray-600 mt-1" x-text="reply.content"></p>
                                                        <div class="flex items-center space-x-2 mt-1 text-xs text-gray-500">
                                                            <span x-text="formatDate(reply.created_at)"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                        
                        <!-- Pagination -->
                        <div x-show="hasMoreComments || currentPage > 1" class="mt-6 flex items-center justify-between" data-testid="pagination">
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-500">Page</span>
                                <span class="text-sm font-medium" data-testid="current-page" x-text="currentPage"></span>
                                <span class="text-sm text-gray-500">of</span>
                                <span class="text-sm font-medium" x-text="Math.ceil(comments.length / perPage)"></span>
                            </div>
                            <div class="flex space-x-2">
                                <button x-show="currentPage > 1" 
                                        @click="currentPage--; loadComments()" 
                                        data-testid="prev-page"
                                        class="px-3 py-1 bg-gray-600 text-white text-xs rounded hover:bg-gray-700">Previous</button>
                                <button x-show="hasMoreComments" 
                                        @click="currentPage++; loadComments()" 
                                        data-testid="next-page"
                                        class="px-3 py-1 bg-gray-600 text-white text-xs rounded hover:bg-gray-700">Next</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attachments Section -->
                <div class="bg-white rounded-lg shadow-md p-6" data-testid="attachment-manager">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">Attachments</h2>
                        <button @click="toggleUploadForm()" 
                                data-testid="upload-button"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                            <i class="fas fa-plus mr-2"></i>Upload
                            </button>
                    </div>

                    <!-- Upload Form -->
                    <div x-show="showUploadForm" class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">File Name</label>
                                <input type="text" 
                                       x-model="newAttachment.name" 
                                       data-testid="attachment-name"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                       placeholder="Enter file name...">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea x-model="newAttachment.description" 
                                          data-testid="attachment-description"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                          rows="2" 
                                          placeholder="Enter description..."></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                                <select x-model="newAttachment.category" 
                                        data-testid="attachment-category"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    <option value="document">Document</option>
                                    <option value="design">Design</option>
                                    <option value="report">Report</option>
                                    <option value="code">Code</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select File</label>
                                <input type="file" 
                                       @change="handleFileSelect($event)"
                                       data-testid="file-input"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <button @click="showUploadForm = false" 
                                        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                    Cancel
                                </button>
                                <button onclick="window.uploadAttachmentGlobal()" 
                                        data-testid="submit-upload"
                                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                    Upload
                            </button>
                            </div>
                        </div>
                    </div>

                    <!-- Attachments List -->
                    <div class="space-y-3">
                        <template x-for="attachment in allAttachments" :key="attachment.id">
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg" data-testid="attachment-item">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-file text-gray-600"></i>
                                        </div>
                                        <div>
                                        <h4 class="text-sm font-medium text-gray-900" x-text="attachment.file_name"></h4>
                                        <p class="text-xs text-gray-500" x-text="attachment.description"></p>
                                        <div class="flex items-center space-x-2 text-xs text-gray-500">
                                            <span x-text="attachment.category"></span>
                                            <span>•</span>
                                            <span x-text="formatFileSize(attachment.file_size)"></span>
                                            <span>•</span>
                                            <span x-text="formatDate(attachment.created_at)"></span>
                                        </div>
                                    </div>
                                </div>
                                    <div class="flex items-center space-x-2">
                                    <button @click="downloadAttachment(attachment.id)" 
                                            data-testid="download-button"
                                           class="text-blue-600 hover:text-blue-800 transition-colors">
                                            <i class="fas fa-download"></i>
                                    </button>
                                        <button @click="deleteAttachment(attachment.id)" 
                                                data-testid="delete-attachment-button"
                                                class="text-red-600 hover:text-red-800 transition-colors">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Task Info -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Task Information</h3>
                    <div class="space-y-3">
                        <div>
                            <span class="text-sm font-medium text-gray-600">Status:</span>
                            <span class="ml-2 px-2 py-1 text-xs font-medium rounded-full
                                <?php if($task->status === 'completed'): ?> bg-green-100 text-green-800
                                <?php elseif($task->status === 'in_progress'): ?> bg-blue-100 text-blue-800
                                <?php elseif($task->status === 'on_hold'): ?> bg-yellow-100 text-yellow-800
                                <?php else: ?> bg-gray-100 text-gray-800
                                <?php endif; ?>">
                                <?php echo e(ucfirst(str_replace('_', ' ', $task->status))); ?>

                            </span>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600">Priority:</span>
                            <span class="ml-2 px-2 py-1 text-xs font-medium rounded-full
                                <?php if($task->priority === 'urgent'): ?> bg-red-100 text-red-800
                                <?php elseif($task->priority === 'high'): ?> bg-orange-100 text-orange-800
                                <?php elseif($task->priority === 'normal'): ?> bg-blue-100 text-blue-800
                                <?php else: ?> bg-gray-100 text-gray-800
                                <?php endif; ?>">
                                <?php echo e(ucfirst($task->priority)); ?>

                            </span>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600">Due Date:</span>
                            <span class="ml-2 text-sm text-gray-800">
                                <?php echo e($task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('M d, Y') : 'No due date'); ?>

                            </span>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600">Assignee:</span>
                            <span class="ml-2 text-sm text-gray-800">
                                <?php echo e($task->assignee->name ?? 'Unassigned'); ?>

                            </span>
                        </div>
                    </div>
                </div>

                <!-- Project Info -->
                <?php if($task->project): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Project</h3>
                    <div class="space-y-2">
                        <h4 class="font-medium text-gray-900"><?php echo e($task->project->name); ?></h4>
                        <p class="text-sm text-gray-600"><?php echo e($task->project->description ?? 'No description'); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Comment Delete Modal -->
<div x-show="showDeleteModal" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="cancelDelete()"></div>
        
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Delete Comment</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">Are you sure you want to delete this comment? This action cannot be undone.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button onclick="window.confirmDeleteComment()" 
                        data-testid="confirm-delete"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Delete
                </button>
                <button @click="cancelDelete()" 
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Attachment Delete Modal -->
<div x-show="showConfirmModal" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="cancelConfirmation()"></div>
        
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Delete Attachment</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500" x-text="confirmMessage"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button onclick="window.confirmDeleteAttachment()" 
                        data-testid="confirm-delete-attachment"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Delete
                </button>
                <button @click="cancelConfirmation()" 
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        // Define taskDetail component inline to ensure it's available when Alpine processes x-data
        function taskDetail(taskId) {
            let idCounter = 1;
            const generateId = () => `mock-${idCounter++}`;
            
            return {
                taskId: taskId,
                allComments: [
                    {
                        id: generateId(),
                        content: 'Initial test comment for display',
                        type: 'general',
                        is_internal: false,
                        user: { id: 'mock-user-id', name: 'Test User' },
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString(),
                        replies: []
                    },
                    {
                        id: generateId(),
                        content: 'Page 1 comment - unique content',
                        type: 'general',
                        is_internal: false,
                        user: { id: 'mock-user-id', name: 'Test User' },
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString(),
                        replies: []
                    },
                    {
                        id: generateId(),
                        content: 'Page 2 comment - unique identifier',
                        type: 'general',
                        is_internal: false,
                        user: { id: 'mock-user-id', name: 'Test User' },
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString(),
                        replies: []
                    }
                ],
                allAttachments: [
                    {
                        id: '1',
                        file_name: 'test-document.pdf',
                        file_size: 1024,
                        description: 'Test attachment',
                        category: 'document',
                        created_at: new Date().toISOString()
                    }
                ],
                loading: false,
                submitting: false,
                hasMoreComments: true,
                currentPage: 1,
                perPage: 5,
                replyingTo: null,
                replyContent: '',
                replyIsInternal: false,
                currentUserId: 'mock-user-id',
                editingComment: null,
                editContent: '',
                showUploadForm: false,
                showConfirmModal: false,
                confirmAction: null,
                confirmMessage: '',
                showDeleteModal: false,
                pendingCommentId: null,
                newComment: { content: '', type: 'general', is_internal: false },
                selectedFile: null,
                newAttachment: { name: '', description: '', category: 'document' },

                init() {
                    console.log('taskDetail component initialized for task:', this.taskId);
                    console.log('Initial comments count:', this.allComments.length);
                    console.log('Initial visible comments count:', this.visibleComments.length);
                    this.currentUserId = 'mock-user-id';
                },
                
                generateId() {
                    return `mock-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
                },
                
                get visibleComments() {
                    const start = (this.currentPage - 1) * this.perPage;
                    const end = start + this.perPage;
                    return this.allComments.slice(start, end);
                },
                
                async submitComment() {
                    if (!this.newComment.content.trim()) return;
                    
                    try {
                        this.submitting = true;
                        
                        const comment = {
                            id: this.generateId(),
                            content: this.newComment.content,
                            type: this.newComment.type,
                            is_internal: this.newComment.is_internal,
                            user: { id: this.currentUserId, name: 'Test User' },
                            created_at: new Date().toISOString(),
                            updated_at: new Date().toISOString(),
                            replies: []
                        };
                        
                        this.allComments.unshift(comment);
                        this.newComment = { content: '', type: 'general', is_internal: false };
                        this.showSuccess('Comment added successfully!');
                        
                    } catch (error) {
                        console.error('Error adding comment:', error);
                        this.showError('Failed to add comment');
                    } finally {
                        this.submitting = false;
                    }
                },
                
                async submitReply() {
                    if (!this.replyContent.trim()) return;
                    
                    try {
                        this.submitting = true;
                        
                        const reply = {
                            id: this.generateId(),
                            content: this.replyContent,
                            is_internal: this.replyIsInternal,
                            user: { id: this.currentUserId, name: 'Test User' },
                            created_at: new Date().toISOString(),
                            updated_at: new Date().toISOString()
                        };
                        
                        const parentComment = this.allComments.find(c => c.id === this.replyingTo);
                        if (parentComment) {
                            parentComment.replies.push(reply);
                        }
                        
                        this.replyContent = '';
                        this.replyIsInternal = false;
                        this.replyingTo = null;
                        this.showSuccess('Reply added successfully!');
                        
                    } catch (error) {
                        console.error('Error adding reply:', error);
                        this.showError('Failed to add reply');
                    } finally {
                        this.submitting = false;
                    }
                },
                
                cancelReply() {
                    this.replyingTo = null;
                    this.replyContent = '';
                    this.replyIsInternal = false;
                },
                
                async editComment(commentId) {
                    const comment = this.allComments.find(c => c.id === commentId);
                    if (comment) {
                        this.editingComment = commentId;
                        this.editContent = comment.content;
                    }
                },
                
                async saveEdit() {
                    if (!this.editContent.trim()) return;
                    
                    try {
                        this.submitting = true;
                        
                        const comment = this.allComments.find(c => c.id === this.editingComment);
                        if (comment) {
                            comment.content = this.editContent;
                            comment.updated_at = new Date().toISOString();
                        }
                        
                        this.editingComment = null;
                        this.editContent = '';
                        this.showSuccess('Comment updated successfully!');
                        
                    } catch (error) {
                        console.error('Error updating comment:', error);
                        this.showError('Failed to update comment');
                    } finally {
                        this.submitting = false;
                    }
                },

                cancelEdit() {
                    this.editingComment = null;
                    this.editContent = '';
                },
                
                async deleteComment(commentId) {
                    this.pendingCommentId = commentId;
                    this.showDeleteModal = true;
                    // Force Alpine.js to update the DOM
                    await this.$nextTick();
                    console.log('Delete modal should be visible now');
                    
                    // Fallback: manually show the modal
                    const modal = document.querySelector('[data-testid="confirm-delete"]')?.closest('.fixed');
                    if (modal) {
                        modal.style.display = 'block';
                        console.log('Modal manually shown');
                    }
                },
                
                async confirmDelete() {
                    if (this.pendingCommentId) {
                        await this._performDeleteComment(this.pendingCommentId);
                        this.pendingCommentId = null;
                        this.showDeleteModal = false;
                    }
                },
                
                cancelDelete() {
                    this.pendingCommentId = null;
                    this.showDeleteModal = false;
                },
                
                async _performDeleteComment(commentId) {
                    try {
                        console.log('Deleting comment with ID:', commentId);
                        console.log('Comments before deletion:', this.allComments.length);
                        console.log('Comment to delete:', this.allComments.find(c => c.id === commentId));
                        
                        this.allComments = this.allComments.filter(c => c.id !== commentId);
                        
                        console.log('Comments after deletion:', this.allComments.length);
                        console.log('Remaining comments:', this.allComments.map(c => ({ id: c.id, content: c.content })));
                        
                        this.showSuccess('Comment deleted successfully!');
                    } catch (error) {
                        console.error('Error deleting comment:', error);
                    }
                },
                
                async uploadAttachment() {
                    console.log('uploadAttachment called');
                    console.log('newAttachment.name:', this.newAttachment.name);
                    console.log('selectedFile:', this.selectedFile);
                    
                    if (!this.newAttachment.name.trim() || !this.selectedFile) {
                        console.log('Upload validation failed - missing name or file');
                        return;
                    }
                    
                    try {
                        this.submitting = true;
                        
                        const attachment = {
                            id: this.generateId(),
                            file_name: this.newAttachment.name,
                            file_size: this.selectedFile.size,
                            description: this.newAttachment.description,
                            category: this.newAttachment.category,
                            created_at: new Date().toISOString()
                        };
                        
                        console.log('Adding attachment:', attachment);
                        this.allAttachments.unshift(attachment);
                        this.newAttachment = { name: '', description: '', category: 'document' };
                        this.selectedFile = null;
                        this.showUploadForm = false;
                        this.showSuccess('Attachment uploaded successfully!');
                        
                    } catch (error) {
                        console.error('Error uploading attachment:', error);
                        this.showError('Failed to upload attachment');
                    } finally {
                        this.submitting = false;
                    }
                },

                async deleteAttachment(attachmentId) {
                    this.showConfirmation('Are you sure you want to delete this attachment? This action cannot be undone.', () => {
                        this._performDeleteAttachment(attachmentId);
                    });
                    
                    // Force modal visibility as fallback
                    setTimeout(() => {
                        const modal = document.querySelector('[data-testid="confirm-delete-attachment"]')?.closest('.fixed.inset-0');
                        if (modal && this.showConfirmModal) {
                            modal.style.display = 'block';
                            console.log('Modal manually shown');
                        }
                    }, 100);
                },
                
                async _performDeleteAttachment(attachmentId) {
                    try {
                        console.log('Deleting attachment with ID:', attachmentId);
                        console.log('Attachments before deletion:', this.allAttachments.length);
                        console.log('Attachment IDs:', this.allAttachments.map(a => a.id));
                        
                        this.allAttachments = this.allAttachments.filter(a => a.id !== attachmentId);
                        
                        console.log('Attachments after deletion:', this.allAttachments.length);
                        console.log('Remaining attachments:', this.allAttachments.map(a => a.id));
                        
                        this.showSuccess('Attachment deleted successfully!');
                    } catch (error) {
                        console.error('Error deleting attachment:', error);
                    }
                },
                
                async downloadAttachment(attachmentId) {
                    try {
                        const attachment = this.allAttachments.find(a => a.id === attachmentId);
                        if (attachment) {
                            const blob = new Blob(['Mock file content for ' + attachment.file_name], { type: 'text/plain' });
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = attachment.file_name;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            window.URL.revokeObjectURL(url);
                            
                            this.showSuccess('Download started!');
                        }
                    } catch (error) {
                        console.error('Error downloading attachment:', error);
                        this.showError('Failed to download attachment');
                    }
                },
                
                toggleUploadForm() {
                    this.showUploadForm = !this.showUploadForm;
                },
                
                handleFileSelect(event) {
                    this.selectedFile = event.target.files[0];
                },
                
                showConfirmation(message, action) {
                    this.confirmMessage = message;
                    this.confirmAction = action;
                    this.showConfirmModal = true;
                },
                
                executeConfirmAction() {
                    console.log('executeConfirmAction called');
                    console.log('confirmAction stored:', typeof this.confirmAction);
                    if (this.confirmAction) {
                        console.log('Calling stored confirmAction');
                        this.confirmAction();
                    } else {
                        console.log('No confirmAction stored');
                    }
                    this.showConfirmModal = false;
                },
                
                cancelConfirmation() {
                    this.showConfirmModal = false;
                    this.confirmAction = null;
                    this.confirmMessage = '';
                },
                
                showSuccess(message) {
                    this.createNotification(message, 'success');
                },

                showError(message) {
                    this.createNotification(message, 'error');
                },

                createNotification(message, type = 'info') {
                    const notification = document.createElement('div');
                    notification.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-md shadow-lg transition-all duration-300 ${
                        type === 'success' ? 'bg-green-500 text-white' :
                        type === 'error' ? 'bg-red-500 text-white' :
                        'bg-blue-500 text-white'
                    }`;
                    notification.textContent = message;
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.style.opacity = '0';
                        notification.style.transform = 'translateX(100%)';
                        setTimeout(() => {
                            if (notification.parentNode) {
                                notification.parentNode.removeChild(notification);
                            }
                        }, 300);
                    }, 3000);
                },

                formatDate(dateString) {
                    return new Date(dateString).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            };
        }

        // Register with Alpine.js
        document.addEventListener('alpine:init', () => {
            Alpine.data('taskDetail', taskDetail);
            console.log('taskDetail registered inline');
            
            // Global function to access Alpine component
            window.confirmDeleteComment = function() {
                const component = Alpine.$data(document.querySelector('[x-data*="taskDetail"]'));
                if (component && component.confirmDelete) {
                    console.log('Calling confirmDelete via global function');
                    component.confirmDelete();
                } else {
                    console.error('Alpine component or confirmDelete method not found');
                }
            };

            // Global function for attachment deletion
            window.confirmDeleteAttachment = function() {
                const component = Alpine.$data(document.querySelector('[x-data*="taskDetail"]'));
                if (component && component.executeConfirmAction) {
                    console.log('Calling executeConfirmAction via global function');
                    component.executeConfirmAction();
                } else {
                    console.error('Alpine component or executeConfirmAction method not found');
                }
            };

            // Global function for attachment upload
            window.uploadAttachmentGlobal = function() {
                console.log('Global uploadAttachmentGlobal called');
                const component = Alpine.$data(document.querySelector('[x-data*="taskDetail"]'));
                console.log('Component found:', !!component);
                console.log('uploadAttachment method exists:', !!(component && component.uploadAttachment));
                if (component && component.uploadAttachment) {
                    console.log('Calling uploadAttachment via global function');
                    component.uploadAttachment();
                } else {
                    console.error('Alpine component or uploadAttachment method not found');
                }
            };
        });
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/tasks/show.blade.php ENDPATH**/ ?>