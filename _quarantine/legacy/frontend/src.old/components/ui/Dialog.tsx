import React, { useEffect } from 'react';
import { cn } from '@/lib/utils';

interface DialogProps {
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
  children: React.ReactNode;
}

interface DialogContentProps {
  className?: string;
  children: React.ReactNode;
}

interface DialogHeaderProps {
  children: React.ReactNode;
}

interface DialogTitleProps {
  children: React.ReactNode;
}

interface DialogTriggerProps {
  asChild?: boolean;
  children: React.ReactNode;
}

export const Dialog: React.FC<DialogProps> = ({ open, onOpenChange, children }) => {
  useEffect(() => {
    if (open) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = 'unset';
    }
    
    return () => {
      document.body.style.overflow = 'unset';
    };
  }, [open]);

  // Use React Context to pass onOpenChange to DialogTrigger
  const [trigger, setTrigger] = React.useState<React.ReactNode>(null);
  const [content, setContent] = React.useState<React.ReactNode>(null);

  React.useEffect(() => {
    const childrenArray = React.Children.toArray(children);
    const triggerChild = childrenArray.find((child: any) => 
      child?.type === DialogTrigger || (child as any)?.props?.children?.type === DialogTrigger
    );
    const contentChildren = childrenArray.filter((child: any) => 
      child?.type !== DialogTrigger && child?.type !== DialogContent
    );

    if (triggerChild) {
      setTrigger(React.cloneElement(triggerChild as React.ReactElement, {
        onOpenChange,
      }));
    }
    setContent(contentChildren);
  }, [children, onOpenChange]);

  return (
    <>
      {trigger}
      {open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
          <div 
            className="fixed inset-0 bg-black/50" 
            onClick={() => onOpenChange?.(false)}
          />
          <div className="relative z-50">
            {content}
          </div>
        </div>
      )}
    </>
  );
};

export const DialogContent: React.FC<DialogContentProps> = ({ className, children }) => {
  return (
    <div 
      className={cn(
        "bg-white rounded-lg shadow-lg border border-gray-200 p-6 max-w-md w-full mx-4",
        className
      )}
      onClick={(e) => e.stopPropagation()}
    >
      {children}
    </div>
  );
};

export const DialogHeader: React.FC<DialogHeaderProps> = ({ children }) => {
  return (
    <div className="mb-4">
      {children}
    </div>
  );
};

export const DialogTitle: React.FC<DialogTitleProps> = ({ children }) => {
  return (
    <h2 className="text-lg font-semibold text-gray-900">
      {children}
    </h2>
  );
};

export const DialogTrigger: React.FC<DialogTriggerProps & { onOpenChange?: (open: boolean) => void }> = ({ 
  asChild, 
  children,
  onOpenChange 
}) => {
  const handleClick = () => {
    onOpenChange?.(true);
  };

  if (asChild && React.isValidElement(children)) {
    return React.cloneElement(children, {
      onClick: (e: React.MouseEvent) => {
        children.props.onClick?.(e);
        handleClick();
      },
    });
  }
  
  return (
    <div onClick={handleClick} style={{ display: 'inline-block' }}>
      {children}
    </div>
  );
};

// Label component
export const Label: React.FC<React.LabelHTMLAttributes<HTMLLabelElement>> = ({ 
  className, 
  children, 
  ...props 
}) => {
  return (
    <label 
      className={cn("text-sm font-medium text-gray-700", className)}
      {...props}
    >
      {children}
    </label>
  );
};
