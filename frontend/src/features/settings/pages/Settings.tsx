import React from 'react';
import { Link } from 'react-router-dom';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';

/**
 * Trang cài đặt hệ thống (Admin)
 * Chỉ dành cho admin để cấu hình hệ thống
 */
export function Settings() {
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-white shadow">
        <div className="px-4 py-6 sm:px-6">
          <h1 className="text-2xl font-bold text-gray-900">Cài đặt hệ thống</h1>
          <p className="mt-1 text-sm text-gray-600">
            Quản lý cấu hình và cài đặt toàn hệ thống
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* System Settings */}
        <Card className="p-6">
          <h3 className="text-lg font-medium text-gray-900 mb-4">Cài đặt hệ thống</h3>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium text-gray-900">Bảo trì hệ thống</h4>
                <p className="text-sm text-gray-500">Bật/tắt chế độ bảo trì</p>
              </div>
              <Link to="/settings/general">
                <Button variant="outline" size="sm">
                  Open
                </Button>
              </Link>
            </div>
            
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium text-gray-900">Backup tự động</h4>
                <p className="text-sm text-gray-500">Lên lịch backup dữ liệu</p>
              </div>
              <Link to="/settings/security">
                <Button variant="outline" size="sm">
                  Open
                </Button>
              </Link>
            </div>
            
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium text-gray-900">Logs hệ thống</h4>
                <p className="text-sm text-gray-500">Xem và quản lý logs</p>
              </div>
              <Button variant="outline" size="sm">
                Xem logs
              </Button>
            </div>
          </div>
        </Card>

        {/* User Management */}
        <Card className="p-6">
          <h3 className="text-lg font-medium text-gray-900 mb-4">Quản lý người dùng</h3>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium text-gray-900">Roles & Permissions</h4>
                <p className="text-sm text-gray-500">Quản lý quyền hạn</p>
              </div>
              <Button variant="outline" size="sm">
                Quản lý
              </Button>
            </div>
            
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium text-gray-900">Danh sách người dùng</h4>
                <p className="text-sm text-gray-500">Xem tất cả người dùng</p>
              </div>
              <Button variant="outline" size="sm">
                Xem danh sách
              </Button>
            </div>
            
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium text-gray-900">Thêm người dùng</h4>
                <p className="text-sm text-gray-500">Tạo tài khoản mới</p>
              </div>
              <Button variant="outline" size="sm">
                Thêm mới
              </Button>
            </div>
          </div>
        </Card>

        {/* Notification Settings */}
        <Card className="p-6">
          <h3 className="text-lg font-medium text-gray-900 mb-4">Cài đặt thông báo</h3>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium text-gray-900">Email templates</h4>
                <p className="text-sm text-gray-500">Quản lý mẫu email</p>
              </div>
              <Button variant="outline" size="sm">
                Cấu hình
              </Button>
            </div>
            
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium text-gray-900">Webhook settings</h4>
                <p className="text-sm text-gray-500">Cấu hình webhook</p>
              </div>
              <Link to="/settings/notifications">
                <Button variant="outline" size="sm">
                  Open
                </Button>
              </Link>
            </div>
          </div>
        </Card>

        {/* Database Settings */}
        <Card className="p-6">
          <h3 className="text-lg font-medium text-gray-900 mb-4">Cài đặt cơ sở dữ liệu</h3>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium text-gray-900">Database status</h4>
                <p className="text-sm text-gray-500">Kiểm tra trạng thái DB</p>
              </div>
              <Button variant="outline" size="sm">
                Kiểm tra
              </Button>
            </div>
            
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium text-gray-900">Migrations</h4>
                <p className="text-sm text-gray-500">Chạy migrations</p>
              </div>
              <Button variant="outline" size="sm">
                Chạy
              </Button>
            </div>
          </div>
        </Card>
      </div>
    </div>
  );
}
