import React from 'react';

export interface ContainerProps extends React.HTMLAttributes<HTMLDivElement> {
  maxWidth?: number;
  padding?: number;
}

export const Container: React.FC<ContainerProps> = ({ maxWidth = 1200, padding = 24, style, ...props }) => {
  return (
    <div
      {...props}
      style={{
        maxWidth,
        margin: '0 auto',
        paddingLeft: padding,
        paddingRight: padding,
        ...style,
      }}
    />
  );
};

export default Container;

