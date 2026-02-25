import React from 'react';
import { Link } from 'react-router-dom';
import { Button } from '@/components/Button';
import { Home, ArrowLeft } from 'lucide-react';

const NotFoundPage: React.FC = () => {
  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
      <div className="max-w-md w-full text-center">
        {/* 404 Illustration */}
        <div className="mb-8">
          <div className="text-9xl font-bold text-blue-600 mb-4">404</div>
          <div className="w-24 h-1 bg-blue-600 mx-auto rounded-full"></div>
        </div>

        {/* Error Message */}
        <h1 className="text-3xl font-bold text-gray-900 mb-4">
          Trang không tồn tại
        </h1>
        <p className="text-gray-600 mb-8 leading-relaxed">
          Xin lỗi, trang bạn đang tìm kiếm không tồn tại hoặc đã bị di chuyển. 
          Vui lòng kiểm tra lại đường dẫn hoặc quay về trang chủ.
        </p>

        {/* Action Buttons */}
        <div className="flex flex-col sm:flex-row gap-4 justify-center">
          <Button onClick={() => window.history.back()} variant="outline">
            <ArrowLeft className="w-4 h-4 mr-2" />
            Quay lại
          </Button>
          <Button asChild>
            <Link to="/dashboard">
              <Home className="w-4 h-4 mr-2" />
              Về trang chủ
            </Link>
          </Button>
        </div>

        {/* Help Links */}
        <div className="mt-12 pt-8 border-t border-gray-200">
          <p className="text-sm text-gray-500 mb-4">Cần hỗ trợ?</p>
          <div className="flex justify-center gap-6 text-sm">
            <Link to="/help" className="text-blue-600 hover:text-blue-800 transition-colors">
              Trung tâm hỗ trợ
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
};

export default NotFoundPage;
