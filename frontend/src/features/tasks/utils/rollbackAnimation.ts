/**
 * Animate task rollback to original position
 * 
 * @param taskElement - The DOM element of the task being animated
 * @param originalPosition - The original position {x, y} before drag
 * @param onComplete - Callback when animation completes
 */
export function animateRollback(
  taskElement: HTMLElement,
  originalPosition: { x: number; y: number },
  onComplete?: () => void
) {
  const currentRect = taskElement.getBoundingClientRect();
  const deltaX = originalPosition.x - currentRect.left;
  const deltaY = originalPosition.y - currentRect.top;
  
  // Apply CSS transition and transform
  taskElement.style.transition = 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
  taskElement.style.transform = `translate(${deltaX}px, ${deltaY}px)`;
  
  // Clean up after animation completes
  setTimeout(() => {
    taskElement.style.transition = '';
    taskElement.style.transform = '';
    onComplete?.();
  }, 300);
}

