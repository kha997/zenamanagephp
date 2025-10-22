// Alpine.js Task Detail Component
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
        showConfirmModal: false, // For attachment deletion
        confirmAction: null,
        confirmMessage: '',
        showDeleteModal: false, // For comment deletion
        pendingCommentId: null,
        newComment: { content: '', type: 'general', is_internal: false },
        selectedFile: null,
        newAttachment: { name: '', description: '', category: 'document' },

        init() {
            console.log('taskDetail component initialized for task:', this.taskId);
            console.log('Initial comments count:', this.allComments.length);
            console.log('Initial visible comments count:', this.visibleComments.length);
            this.currentUserId = 'mock-user-id';
            // No API calls - use in-memory data
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
                this.allComments = this.allComments.filter(c => c.id !== commentId);
                this.showSuccess('Comment deleted successfully!');
            } catch (error) {
                console.error('Error deleting comment:', error);
            }
        },
        
        async uploadAttachment() {
            if (!this.newAttachment.name.trim() || !this.selectedFile) return;
            
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
        },
        
        async _performDeleteAttachment(attachmentId) {
            try {
                this.allAttachments = this.allAttachments.filter(a => a.id !== attachmentId);
                this.showSuccess('Attachment deleted successfully!');
            } catch (error) {
                console.error('Error deleting attachment:', error);
            }
        },
        
        async downloadAttachment(attachmentId) {
            try {
                // Create a mock download
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
        
        confirmAction() {
            if (this.confirmAction) {
                this.confirmAction();
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

// Register with Alpine.js immediately
console.log('Registering taskDetail component...');
if (window.Alpine && window.Alpine.data) {
    Alpine.data('taskDetail', taskDetail);
    console.log('taskDetail registered immediately');
} else {
    console.log('Alpine not available, waiting...');
    // Wait for Alpine to be available
    const checkAlpine = setInterval(() => {
        if (window.Alpine && window.Alpine.data) {
            Alpine.data('taskDetail', taskDetail);
            console.log('taskDetail registered after waiting');
            clearInterval(checkAlpine);
        }
    }, 10);
}