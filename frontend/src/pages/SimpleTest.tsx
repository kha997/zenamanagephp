import React from 'react';

export default function SimpleTest() {
  return (
    <div style={{ 
      padding: '20px', 
      fontFamily: 'Arial, sans-serif',
      backgroundColor: '#f0f0f0',
      minHeight: '100vh'
    }}>
      <h1 style={{ color: '#333', marginBottom: '20px' }}>
        🎉 Frontend Test Page
      </h1>
      
      <div style={{
        backgroundColor: 'white',
        padding: '20px',
        borderRadius: '8px',
        boxShadow: '0 2px 4px rgba(0,0,0,0.1)',
        marginBottom: '20px'
      }}>
        <h2 style={{ color: '#2c3e50', marginBottom: '15px' }}>
          ✅ React is Working!
        </h2>
        <p style={{ color: '#666', lineHeight: '1.6' }}>
          Nếu bạn thấy trang này, có nghĩa là React frontend đã hoạt động thành công.
        </p>
      </div>
      
      <div style={{
        backgroundColor: '#e8f5e8',
        border: '1px solid #4caf50',
        padding: '15px',
        borderRadius: '5px',
        marginBottom: '20px'
      }}>
        <h3 style={{ color: '#2e7d32', margin: '0 0 10px 0' }}>
          🚀 System Status
        </h3>
        <ul style={{ color: '#388e3c', margin: 0, paddingLeft: '20px' }}>
          <li>✅ React Frontend: Running on port 5175</li>
          <li>✅ Laravel Backend: Running on port 8000</li>
          <li>✅ WebSocket Server: Running on port 8080</li>
        </ul>
      </div>
      
      <div style={{
        backgroundColor: '#fff3cd',
        border: '1px solid #ffc107',
        padding: '15px',
        borderRadius: '5px'
      }}>
        <h3 style={{ color: '#856404', margin: '0 0 10px 0' }}>
          📝 Next Steps
        </h3>
        <p style={{ color: '#856404', margin: 0 }}>
          Bây giờ bạn có thể test các tính năng khác của hệ thống!
        </p>
      </div>
    </div>
  );
}
