import apiClient from '../lib/api'

export interface Comment {
  id: string
  content: string
  user_id: string
  user: {
    id: string
    name: string
    email: string
    avatar?: string
  }
  project_id?: string
  task_id?: string
  parent_id?: string
  created_at: string
  updated_at: string
  mentions?: string[]
}

export interface ChatMessage {
  id: string
  content: string
  user_id: string
  user: {
    id: string
    name: string
    email: string
    avatar?: string
  }
  room_id: string
  created_at: string
  updated_at: string
  type: 'text' | 'file' | 'image' | 'system'
  metadata?: {
    file_id?: string
    file_name?: string
    file_size?: number
    file_type?: string
  }
}

export interface ChatRoom {
  id: string
  name: string
  description?: string
  type: 'project' | 'task' | 'general'
  project_id?: string
  task_id?: string
  created_by: string
  created_at: string
  updated_at: string
  members: {
    id: string
    name: string
    email: string
    avatar?: string
    role: 'admin' | 'member'
    joined_at: string
  }[]
  last_message?: ChatMessage
  unread_count: number
}

export interface Notification {
  id: string
  type: 'mention' | 'comment' | 'message' | 'task_assigned' | 'project_updated'
  title: string
  message: string
  user_id: string
  project_id?: string
  task_id?: string
  room_id?: string
  read: boolean
  created_at: string
  data?: any
}

export const collaborationService = {
  // Comments
  async getComments(projectId?: string, taskId?: string): Promise<Comment[]> {
    const params: any = {}
    if (projectId) params.project_id = projectId
    if (taskId) params.task_id = taskId

    const response = await apiClient.get<Comment[]>('/comments', params)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get comments')
  },

  async createComment(data: {
    content: string
    project_id?: string
    task_id?: string
    parent_id?: string
    mentions?: string[]
  }): Promise<Comment> {
    const response = await apiClient.post<Comment>('/comments', data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to create comment')
  },

  async updateComment(id: string, data: { content: string; mentions?: string[] }): Promise<Comment> {
    const response = await apiClient.put<Comment>(`/comments/${id}`, data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to update comment')
  },

  async deleteComment(id: string): Promise<void> {
    const response = await apiClient.delete(`/comments/${id}`)
    if (response.status !== 'success') {
      throw new Error(response.message || 'Failed to delete comment')
    }
  },

  // Chat Rooms
  async getChatRooms(): Promise<ChatRoom[]> {
    const response = await apiClient.get<ChatRoom[]>('/chat/rooms')
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get chat rooms')
  },

  async createChatRoom(data: {
    name: string
    description?: string
    type: 'project' | 'task' | 'general'
    project_id?: string
    task_id?: string
    member_ids?: string[]
  }): Promise<ChatRoom> {
    const response = await apiClient.post<ChatRoom>('/chat/rooms', data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to create chat room')
  },

  async updateChatRoom(id: string, data: {
    name?: string
    description?: string
    member_ids?: string[]
  }): Promise<ChatRoom> {
    const response = await apiClient.put<ChatRoom>(`/chat/rooms/${id}`, data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to update chat room')
  },

  async deleteChatRoom(id: string): Promise<void> {
    const response = await apiClient.delete(`/chat/rooms/${id}`)
    if (response.status !== 'success') {
      throw new Error(response.message || 'Failed to delete chat room')
    }
  },

  // Chat Messages
  async getMessages(roomId: string, page = 1, perPage = 50): Promise<{
    data: ChatMessage[]
    pagination: any
  }> {
    const response = await apiClient.get<{
      data: ChatMessage[]
      pagination: any
    }>(`/chat/rooms/${roomId}/messages`, {
      page,
      per_page: perPage
    })
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get messages')
  },

  async sendMessage(roomId: string, data: {
    content: string
    type?: 'text' | 'file' | 'image' | 'system'
    metadata?: any
  }): Promise<ChatMessage> {
    const response = await apiClient.post<ChatMessage>(`/chat/rooms/${roomId}/messages`, data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to send message')
  },

  async updateMessage(messageId: string, data: { content: string }): Promise<ChatMessage> {
    const response = await apiClient.put<ChatMessage>(`/chat/messages/${messageId}`, data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to update message')
  },

  async deleteMessage(messageId: string): Promise<void> {
    const response = await apiClient.delete(`/chat/messages/${messageId}`)
    if (response.status !== 'success') {
      throw new Error(response.message || 'Failed to delete message')
    }
  },

  // Notifications
  async getNotifications(): Promise<Notification[]> {
    const response = await apiClient.get<Notification[]>('/notifications')
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get notifications')
  },

  async markNotificationAsRead(id: string): Promise<void> {
    const response = await apiClient.put(`/notifications/${id}/read`)
    if (response.status !== 'success') {
      throw new Error(response.message || 'Failed to mark notification as read')
    }
  },

  async markAllNotificationsAsRead(): Promise<void> {
    const response = await apiClient.put('/notifications/read-all')
    if (response.status !== 'success') {
      throw new Error(response.message || 'Failed to mark all notifications as read')
    }
  },

  // Utility functions
  extractMentions(content: string): string[] {
    const mentionRegex = /@(\w+)/g
    const mentions: string[] = []
    let match
    while ((match = mentionRegex.exec(content)) !== null) {
      mentions.push(match[1])
    }
    return mentions
  },

  formatMentionContent(content: string, users: { id: string; name: string; email: string }[]): string {
    return content.replace(/@(\w+)/g, (match, username) => {
      const user = users.find(u => u.name.toLowerCase() === username.toLowerCase())
      return user ? `@${user.name}` : match
    })
  },

  getNotificationIcon(type: string): string {
    switch (type) {
      case 'mention':
        return '💬'
      case 'comment':
        return '💭'
      case 'message':
        return '📨'
      case 'task_assigned':
        return '📋'
      case 'project_updated':
        return '📁'
      default:
        return '🔔'
    }
  }
}
