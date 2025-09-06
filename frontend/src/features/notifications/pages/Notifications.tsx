import React, { useState, useEffect } from 'react';
import { useNotificationStore } from '@/store/notifications';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/Tabs';
import { Bell, Check, Trash2, Settings, AlertCircle, Info, CheckCircle } from 'lucide-react';

export const Notifications: React.FC = () => {
  const { 
    notifications, 
    unreadCount, 
    isLoading, 
    fetchNotifications, 
    markAsRead, 
    markAllAsRead, 
    deleteNotification 
  } = useNotificationStore();
  const [filter, setFilter] = useState<'all' | 'unread' | 'read'>('all');

  useEffect(() => {
    fetchNotifications();
  }, [fetchNotifications]);

  const filteredNotifications = notifications.filter(notification => {
    switch (filter) {
      case 'unread': return !notification.read_at;
      case 'read': return notification.read_at;
      default: return true;
    }
  });

  const getPriorityIcon = (priority: string) => {
    switch (priority) {
      case 'critical': return <AlertCircle className="w-5 h-5 text-red-500" />;
      case 'normal': return <Info className="w-5 h-5 text-blue-500" />;
      case 'low': return <CheckCircle className="w-5 h-5 text-green-500" />;
      default: return <Bell className="w-5 h-5 text-gray-500" />;
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'critical': return 'bg-red-100 text-red-800 border-red-200';
      case 'normal': return 'bg-blue-100 text-blue-800 border-blue-200';
      case 'low': return 'bg-green-100 text-green-800 border-green-200';
      default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  const handleMarkAsRead = (notificationId: number) => {
    markAsRead(notificationId);
  };

  const handleDelete = (notificationId: number) => {
    deleteNotification(notificationId);
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Thông báo</h1>
          <p className="text-gray-600">
            Bạn có {unreadCount} thông báo chưa đọc
          </p>
        </div>
        <div className="flex space-x-2">
          {unreadCount > 0 && (
            <Button variant="outline" onClick={markAllAsRead}>
              <Check className="w-4 h-4 mr-2" />
              Đánh dấu tất cả đã đọc
            </Button>
          )}
          <Button variant="outline">
            <Settings className="w-4 h-4 mr-2" />
            Cài đặt
          </Button>
        </div>
      </div>

      {/* Filter Tabs */}
      <Tabs value={filter} onValueChange={(value) => setFilter(value as any)}>
        <TabsList>
          <TabsTrigger value="all">
            Tất cả ({notifications.length})
          </TabsTrigger>
          <TabsTrigger value="unread">
            Chưa đọc ({unreadCount})
          </TabsTrigger>
          <TabsTrigger value="read">
            Đã đọc ({notifications.length - unreadCount})
          </TabsTrigger>
        </TabsList>

        <TabsContent value={filter} className="space-y-4">
          {filteredNotifications.length > 0 ? (
            <div className="space-y-3">
              {filteredNotifications.map((notification) => (
                <Card 
                  key={notification.id} 
                  className={`p-4 transition-all hover:shadow-md ${
                    !notification.read_at ? 'border-l-4 border-l-blue-500 bg-blue-50/30' : ''
                  }`}
                >
                  <div className="flex items-start space-x-4">
                    <div className="flex-shrink-0 mt-1">
                      {getPriorityIcon(notification.priority)}
                    </div>
                    
                    <div className="flex-1 min-w-0">
                      <div className="flex items-start justify-between">
                        <div className="flex-1">
                          <div className="flex items-center space-x-2 mb-1">
                            <h3 className={`text-sm font-medium ${
                              !notification.read_at ? 'text-gray-900' : 'text-gray-700'
                            }`}>
                              {notification.title}
                            </h3>
                            <Badge 
                              size="sm" 
                              className={getPriorityColor(notification.priority)}
                            >
                              {notification.priority}
                            </Badge>
                            {!notification.read_at && (
                              <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                            )}
                          </div>
                          
                          <p className={`text-sm ${
                            !notification.read_at ? 'text-gray-700' : 'text-gray-600'
                          }`}>
                            {notification.body}
                          </p>
                          
                          <div className="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                            <span>
                              {new Date(notification.created_at).toLocaleString('vi-VN')}
                            </span>
                            {notification.channel && (
                              <Badge variant="outline" size="sm">
                                {notification.channel}
                              </Badge>
                            )}
                          </div>
                        </div>
                        
                        <div className="flex items-center space-x-2 ml-4">
                          {!notification.read_at && (
                            <Button
                              size="sm"
                              variant="ghost"
                              onClick={() => handleMarkAsRead(notification.id)}
                              className="text-blue-600 hover:text-blue-700"
                            >
                              <Check className="w-4 h-4" />
                            </Button>
                          )}
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => handleDelete(notification.id)}
                            className="text-red-600 hover:text-red-700"
                          >
                            <Trash2 className="w-4 h-4" />
                          </Button>
                        </div>
                      </div>
                      
                      {notification.link_url && (
                        <div className="mt-3">
                          <Button 
                            size="sm" 
                            variant="outline"
                            onClick={() => window.open(notification.link_url, '_blank')}
                          >
                            Xem chi tiết
                          </Button>
                        </div>
                      )}
                    </div>
                  </div>
                </Card>
              ))}
            </div>
          ) : (
            <div className="text-center py-12">
              <div className="text-gray-400 mb-4">
                <Bell className="w-12 h-12 mx-auto" />
              </div>
              <h3 className="text-lg font-medium text-gray-900 mb-2">
                {filter === 'unread' ? 'Không có thông báo chưa đọc' : 
                 filter === 'read' ? 'Không có thông báo đã đọc' : 
                 'Không có thông báo'}
              </h3>
              <p className="text-gray-600">
                {filter === 'all' 
                  ? 'Bạn sẽ nhận được thông báo về các hoạt động quan trọng ở đây.'
                  : 'Thử chuyển sang tab khác để xem thông báo.'}
              </p>
            </div>
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
};