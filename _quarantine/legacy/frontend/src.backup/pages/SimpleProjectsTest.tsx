import React from 'react';

export default function SimpleProjectsTest() {
  return (
    <div style={{ padding: '20px', fontFamily: 'Arial, sans-serif' }}>
      <h1 style={{ color: '#333', marginBottom: '20px' }}>
        Simple Projects Test Page
      </h1>
      
      <div style={{ 
        background: 'white', 
        padding: '20px', 
        borderRadius: '8px',
        boxShadow: '0 2px 4px rgba(0,0,0,0.1)',
        marginBottom: '20px'
      }}>
        <h2 style={{ color: '#2563eb', marginBottom: '10px' }}>
          Dashboard Card Test
        </h2>
        <p style={{ color: '#666' }}>
          This is a simple test to verify React is working.
        </p>
      </div>

      <div style={{ 
        display: 'grid', 
        gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))', 
        gap: '20px' 
      }}>
        <div style={{ 
          background: 'linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%)',
          color: 'white',
          padding: '20px',
          borderRadius: '8px'
        }}>
          <h3 style={{ marginBottom: '10px' }}>Total Projects</h3>
          <div style={{ fontSize: '2rem', fontWeight: 'bold' }}>5</div>
          <div style={{ opacity: 0.8 }}>Active projects</div>
        </div>

        <div style={{ 
          background: 'linear-gradient(135deg, #10B981 0%, #34D399 100%)',
          color: 'white',
          padding: '20px',
          borderRadius: '8px'
        }}>
          <h3 style={{ marginBottom: '10px' }}>In Progress</h3>
          <div style={{ fontSize: '2rem', fontWeight: 'bold' }}>3</div>
          <div style={{ opacity: 0.8 }}>Currently active</div>
        </div>

        <div style={{ 
          background: 'linear-gradient(135deg, #F59E0B 0%, #FBBF24 100%)',
          color: 'white',
          padding: '20px',
          borderRadius: '8px'
        }}>
          <h3 style={{ marginBottom: '10px' }}>On Hold</h3>
          <div style={{ fontSize: '2rem', fontWeight: 'bold' }}>1</div>
          <div style={{ opacity: 0.8 }}>Paused projects</div>
        </div>

        <div style={{ 
          background: 'linear-gradient(135deg, #8B5CF6 0%, #A78BFA 100%)',
          color: 'white',
          padding: '20px',
          borderRadius: '8px'
        }}>
          <h3 style={{ marginBottom: '10px' }}>Completed</h3>
          <div style={{ fontSize: '2rem', fontWeight: 'bold' }}>1</div>
          <div style={{ opacity: 0.8 }}>Finished projects</div>
        </div>
      </div>

      <div style={{ marginTop: '20px' }}>
        <button style={{
          background: '#3B82F6',
          color: 'white',
          padding: '10px 20px',
          border: 'none',
          borderRadius: '6px',
          cursor: 'pointer',
          marginRight: '10px'
        }}>
          Create Project
        </button>
        <button style={{
          background: 'transparent',
          color: '#3B82F6',
          padding: '10px 20px',
          border: '1px solid #3B82F6',
          borderRadius: '6px',
          cursor: 'pointer'
        }}>
          Export Report
        </button>
      </div>
    </div>
  );
}