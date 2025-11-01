/**
 * Real-time Updates for Tasks and Comments
 * Handles WebSocket events for real-time collaboration
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Initialize Echo for real-time updates with fallback
const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY || process.env.MIX_PUSHER_APP_KEY;
const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || process.env.MIX_PUSHER_APP_CLUSTER;

if (pusherKey && pusherCluster) {
    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: pusherCluster,
        forceTLS: true,
        auth: {
            headers: {
                Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        }
    });
} else {
    console.warn('Pusher credentials not found. Real-time features will be disabled.');
    // Create a robust mock Echo object to prevent errors
    window.Echo = {
        private: () => ({
            listen: () => {},
            leave: () => {},
            unsubscribe: () => {}
        }),
        leave: () => {},
        unsubscribeAll: () => {},
        connector: {
            pusher: {
                connection: {
                    bind: () => {},
                    unbind: () => {}
                }
            }
        }
    };
}

// Constants
const COMMENT_CREATED_EVENT = '.comment.created';
const COMMENT_UPDATED_EVENT = '.comment.updated';
const COMMENT_DELETED_EVENT = '.comment.deleted';
const TASK_STATUS_UPDATED_EVENT = '.task.status_updated';

class TaskRealtimeManager {
    constructor() {
        this.channels = new Map();
        this.eventHandlers = new Map();
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        
        this.init();
    }

    init() {
        this.setupConnectionHandlers();
        this.setupDefaultEventHandlers();
    }

    /**
     * Subscribe to task-specific events
     */
    subscribeToTask(taskId, callbacks = {}) {
        const channelName = `task.${taskId}`;
        
        if (this.channels.has(channelName)) {
            return this.channels.get(channelName);
        }

        const channel = window.Echo.private(channelName);
        
        // Comment events
        channel.listen(COMMENT_CREATED_EVENT, (data) => {
            this.handleCommentCreated(data, callbacks.onCommentCreated);
        });

        channel.listen(COMMENT_UPDATED_EVENT, (data) => {
            this.handleCommentUpdated(data, callbacks.onCommentUpdated);
        });

        channel.listen(COMMENT_DELETED_EVENT, (data) => {
            this.handleCommentDeleted(data, callbacks.onCommentDeleted);
        });

        // Task events
        channel.listen(TASK_STATUS_UPDATED_EVENT, (data) => {
            this.handleTaskStatusUpdated(data, callbacks.onTaskStatusUpdated);
        });

        this.channels.set(channelName, channel);
        return channel;
    }

    /**
     * Subscribe to project-wide events
     */
    subscribeToProject(projectId, callbacks = {}) {
        const channelName = `project.${projectId}`;
        
        if (this.channels.has(channelName)) {
            return this.channels.get(channelName);
        }

        const channel = window.Echo.private(channelName);
        
        // Listen to all task events within the project
        channel.listen('.comment.created', (data) => {
            this.handleCommentCreated(data, callbacks.onCommentCreated);
        });

        channel.listen('.comment.updated', (data) => {
            this.handleCommentUpdated(data, callbacks.onCommentUpdated);
        });

        channel.listen('.comment.deleted', (data) => {
            this.handleCommentDeleted(data, callbacks.onCommentDeleted);
        });

        channel.listen('.task.status_updated', (data) => {
            this.handleTaskStatusUpdated(data, callbacks.onTaskStatusUpdated);
        });

        this.channels.set(channelName, channel);
        return channel;
    }

    /**
     * Subscribe to tenant-wide events
     */
    subscribeToTenant(tenantId, callbacks = {}) {
        const channelName = `tenant.${tenantId}`;
        
        if (this.channels.has(channelName)) {
            return this.channels.get(channelName);
        }

        const channel = window.Echo.private(channelName);
        
        // Listen to all events within the tenant
        channel.listen('.comment.created', (data) => {
            this.handleCommentCreated(data, callbacks.onCommentCreated);
        });

        channel.listen('.comment.updated', (data) => {
            this.handleCommentUpdated(data, callbacks.onCommentUpdated);
        });

        channel.listen('.comment.deleted', (data) => {
            this.handleCommentDeleted(data, callbacks.onCommentDeleted);
        });

        channel.listen('.task.status_updated', (data) => {
            this.handleTaskStatusUpdated(data, callbacks.onTaskStatusUpdated);
        });

        this.channels.set(channelName, channel);
        return channel;
    }

    /**
     * Handle comment created event
     */
    handleCommentCreated(data, callback) {
        console.log('Real-time: Comment created', data);
        
        // Update UI
        this.updateCommentUI('created', data);
        
        // Show notification
        this.showNotification('New comment', `${data.user.name} added a comment`, 'info');
        
        // Call custom callback
        if (callback && typeof callback === 'function') {
            callback(data);
        }
    }

    /**
     * Handle comment updated event
     */
    handleCommentUpdated(data, callback) {
        console.log('Real-time: Comment updated', data);
        
        // Update UI
        this.updateCommentUI('updated', data);
        
        // Show notification
        this.showNotification('Comment updated', `${data.user.name} updated a comment`, 'info');
        
        // Call custom callback
        if (callback && typeof callback === 'function') {
            callback(data);
        }
    }

    /**
     * Handle comment deleted event
     */
    handleCommentDeleted(data, callback) {
        console.log('Real-time: Comment deleted', data);
        
        // Update UI
        this.updateCommentUI('deleted', data);
        
        // Show notification
        this.showNotification('Comment deleted', `${data.user.name} deleted a comment`, 'warning');
        
        // Call custom callback
        if (callback && typeof callback === 'function') {
            callback(data);
        }
    }

    /**
     * Handle task status updated event
     */
    handleTaskStatusUpdated(data, callback) {
        console.log('Real-time: Task status updated', data);
        
        // Update UI
        this.updateTaskUI('status_updated', data);
        
        // Show notification
        this.showNotification('Task updated', `${data.user.name} moved task to ${data.new_status}`, 'success');
        
        // Call custom callback
        if (callback && typeof callback === 'function') {
            callback(data);
        }
    }

    /**
     * Update comment UI based on event type
     */
    updateCommentUI(action, data) {
        const commentElement = document.querySelector(`[data-comment-id="${data.id}"]`);
        
        switch (action) {
            case 'created':
                this.addCommentToUI(data);
                break;
            case 'updated':
                if (commentElement) {
                    this.updateCommentInUI(commentElement, data);
                }
                break;
            case 'deleted':
                if (commentElement) {
                    commentElement.remove();
                }
                break;
        }
    }

    /**
     * Update task UI based on event type
     */
    updateTaskUI(action, data) {
        const taskElement = document.querySelector(`[data-task-id="${data.id}"]`);
        
        switch (action) {
            case 'status_updated':
                if (taskElement) {
                    this.updateTaskStatusInUI(taskElement, data);
                }
                break;
        }
    }

    /**
     * Add new comment to UI
     */
    addCommentToUI(data) {
        const commentsContainer = document.querySelector('.comments-container');
        if (!commentsContainer) return;

        const commentHTML = this.generateCommentHTML(data);
        commentsContainer.insertAdjacentHTML('beforeend', commentHTML);
        
        // Scroll to new comment
        const newComment = commentsContainer.querySelector(`[data-comment-id="${data.id}"]`);
        if (newComment) {
            newComment.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    /**
     * Update existing comment in UI
     */
    updateCommentInUI(element, data) {
        const contentElement = element.querySelector('.comment-content');
        if (contentElement) {
            contentElement.textContent = data.content;
        }
        
        const timestampElement = element.querySelector('.comment-timestamp');
        if (timestampElement) {
            timestampElement.textContent = new Date(data.updated_at).toLocaleString();
        }
    }

    /**
     * Update task status in UI
     */
    updateTaskStatusInUI(element, data) {
        const statusElement = element.querySelector('.task-status');
        if (statusElement) {
            statusElement.textContent = data.new_status;
            statusElement.className = `task-status status-${data.new_status}`;
        }
        
        const progressElement = element.querySelector('.task-progress');
        if (progressElement) {
            progressElement.style.width = `${data.progress_percent}%`;
        }
    }

    /**
     * Generate HTML for a comment
     */
    generateCommentHTML(data) {
        return `
            <div class="comment-item" data-comment-id="${data.id}">
                <div class="comment-header">
                    <div class="comment-user">
                        <img src="${data.user.avatar || '/default-avatar.png'}" alt="${data.user.name}" class="w-8 h-8 rounded-full">
                        <span class="comment-user-name">${data.user.name}</span>
                    </div>
                    <span class="comment-timestamp">${new Date(data.created_at).toLocaleString()}</span>
                </div>
                <div class="comment-content">${data.content}</div>
                <div class="comment-actions">
                    <button class="comment-action-btn" onclick="editComment('${data.id}')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="comment-action-btn" onclick="deleteComment('${data.id}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Show notification
     */
    showNotification(title, message, type = 'info') {
        // Use existing notification system or create a simple one
        if (window.showNotification) {
            window.showNotification(title, message, type);
        } else {
            // Fallback to browser notification
            if (Notification.permission === 'granted') {
                new Notification(title, { body: message, icon: '/favicon.ico' });
            }
        }
    }

    /**
     * Setup connection handlers
     */
    setupConnectionHandlers() {
        window.Echo.connector.pusher.connection.bind('connected', () => {
            this.isConnected = true;
            this.reconnectAttempts = 0;
            console.log('Real-time connection established');
        });

        window.Echo.connector.pusher.connection.bind('disconnected', () => {
            this.isConnected = false;
            console.log('Real-time connection lost');
        });

        window.Echo.connector.pusher.connection.bind('error', (error) => {
            console.error('Real-time connection error:', error);
            this.handleReconnection();
        });
    }

    /**
     * Handle reconnection logic
     */
    handleReconnection() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            const delay = Math.pow(2, this.reconnectAttempts) * 1000; // Exponential backoff
            
            setTimeout(() => {
                console.log(`Attempting to reconnect (${this.reconnectAttempts}/${this.maxReconnectAttempts})`);
                window.Echo.connector.pusher.connect();
            }, delay);
        } else {
            console.error('Max reconnection attempts reached');
        }
    }

    /**
     * Setup default event handlers
     */
    setupDefaultEventHandlers() {
        // Default handlers can be overridden by specific subscriptions
        this.eventHandlers.set('comment.created', this.handleCommentCreated.bind(this));
        this.eventHandlers.set('comment.updated', this.handleCommentUpdated.bind(this));
        this.eventHandlers.set('comment.deleted', this.handleCommentDeleted.bind(this));
        this.eventHandlers.set('task.status_updated', this.handleTaskStatusUpdated.bind(this));
    }

    /**
     * Unsubscribe from a channel
     */
    unsubscribe(channelName) {
        if (this.channels.has(channelName)) {
            const channel = this.channels.get(channelName);
            window.Echo.leave(channelName);
            this.channels.delete(channelName);
        }
    }

    /**
     * Unsubscribe from all channels
     */
    unsubscribeAll() {
        this.channels.forEach((channel, channelName) => {
            window.Echo.leave(channelName);
        });
        this.channels.clear();
    }

    /**
     * Get connection status
     */
    getConnectionStatus() {
        return {
            isConnected: this.isConnected,
            reconnectAttempts: this.reconnectAttempts,
            activeChannels: Array.from(this.channels.keys())
        };
    }
}

// Create global instance
window.TaskRealtimeManager = new TaskRealtimeManager();

// Export for module usage
export default TaskRealtimeManager;
