import React, { useState, useRef, useEffect } from 'react';
import type { Task } from '../types';
import { getBusinessRulesForTask } from '../utils/businessRules';

interface TaskStatusTooltipProps {
  task: Task;
  showOnHover?: boolean;
  hoverDelay?: number; // milliseconds
}

export const TaskStatusTooltip: React.FC<TaskStatusTooltipProps> = ({
  task,
  showOnHover = true,
  hoverDelay = 1500
}) => {
  const [showTooltip, setShowTooltip] = useState(false);
  const timeoutRef = useRef<NodeJS.Timeout | null>(null);
  const hoverStartTimeRef = useRef<number | null>(null);
  
  const rules = getBusinessRulesForTask(task);
  
  useEffect(() => {
    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
    };
  }, []);
  
  const handleMouseEnter = () => {
    if (showOnHover && rules) {
      hoverStartTimeRef.current = Date.now();
      timeoutRef.current = setTimeout(() => {
        setShowTooltip(true);
      }, hoverDelay);
    }
  };
  
  const handleMouseLeave = () => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
      timeoutRef.current = null;
    }
    hoverStartTimeRef.current = null;
    setShowTooltip(false);
  };
  
  if (!rules) return null;
  
  // Wrap the tooltip in a container that handles hover
  return (
    <div
      className="absolute inset-0 pointer-events-none"
      onMouseEnter={handleMouseEnter}
      onMouseLeave={handleMouseLeave}
    >
      {showTooltip && (
        <div 
          className="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-30"
          style={{
            animation: 'fadeIn 0.2s ease-out'
          }}
        >
          <div className="bg-[var(--surface)] border border-[var(--border)] rounded-lg shadow-lg p-3 max-w-xs">
            <p className="text-xs font-semibold text-[var(--text)] mb-1">
              {rules.title}
            </p>
            <p className="text-xs text-[var(--muted)]">
              {rules.description}
            </p>
          </div>
          {/* Arrow pointing down */}
          <div className="absolute -bottom-1 left-1/2 transform -translate-x-1/2 w-2 h-2 bg-[var(--surface)] border-r border-b border-[var(--border)] rotate-45" />
        </div>
      )}
    </div>
  );
};

