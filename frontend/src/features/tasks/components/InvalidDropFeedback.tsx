import React from 'react';

interface InvalidDropFeedbackProps {
  columnId: string;
  reason: string;
  isActive: boolean;
}

export const InvalidDropFeedback: React.FC<InvalidDropFeedbackProps> = ({
  columnId,
  reason,
  isActive
}) => {
  if (!isActive) return null;
  
  return (
    <>
      {/* Red border overlay */}
      <div 
        className="absolute inset-0 border-2 border-red-500 rounded-lg pointer-events-none z-10"
        style={{ 
          opacity: 0.3,
          animation: 'fadeIn 0.2s ease-in'
        }}
      />
      
      {/* Tooltip */}
      <div 
        className="absolute top-full left-1/2 transform -translate-x-1/2 mt-2 z-20"
        style={{
          animation: 'slideUp 0.2s ease-out'
        }}
      >
        <div className="bg-red-600 text-white text-xs px-3 py-2 rounded shadow-lg whitespace-nowrap max-w-xs">
          {reason}
          <div className="absolute -top-1 left-1/2 transform -translate-x-1/2 w-2 h-2 bg-red-600 rotate-45" />
        </div>
      </div>
    </>
  );
};

