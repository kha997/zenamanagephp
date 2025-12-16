import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { vi, describe, it, expect } from 'vitest';
import { GlobalSearchResultItem } from '@/features/search/components/GlobalSearchResultItem';
import type { GlobalSearchResult } from '@/api/searchApi';

describe('GlobalSearchResultItem', () => {
  const mockResult: GlobalSearchResult = {
    id: 'test-1',
    module: 'projects',
    type: 'project',
    title: 'Riviera Tower',
    subtitle: 'Code: RT-001',
    description: 'A beautiful tower project',
    project_id: 'proj-1',
    project_name: 'Riviera Tower',
    status: 'active',
    entity: {},
  };

  it('renders result with title, subtitle, and description', () => {
    render(<GlobalSearchResultItem result={mockResult} />);

    expect(screen.getByText('Riviera Tower')).toBeInTheDocument();
    expect(screen.getByText('Code: RT-001')).toBeInTheDocument();
    expect(screen.getByText('A beautiful tower project')).toBeInTheDocument();
    expect(screen.getByText('Project')).toBeInTheDocument();
  });

  it('highlights matching text in title (case-insensitive)', () => {
    render(<GlobalSearchResultItem result={mockResult} highlightTerm="Riviera" />);

    // Find the h3 element containing the title
    const titleElement = screen.getByRole('heading', { level: 3 });
    expect(titleElement).toBeInTheDocument();

    // Check that mark element exists (highlighted text)
    const marks = titleElement.querySelectorAll('mark');
    expect(marks.length).toBeGreaterThan(0);
    expect(marks[0]).toHaveTextContent('Riviera');
  });

  it('highlights matching text in subtitle', () => {
    const { container } = render(<GlobalSearchResultItem result={mockResult} highlightTerm="RT" />);

    // Find all mark elements
    const marks = container.querySelectorAll('mark');
    expect(marks.length).toBeGreaterThan(0);
    
    // Find the mark that contains "RT"
    const rtMark = Array.from(marks).find((mark) => mark.textContent === 'RT');
    expect(rtMark).toBeInTheDocument();
    
    // Verify it's within a paragraph that contains "Code:"
    const subtitleParagraph = rtMark?.closest('p');
    expect(subtitleParagraph).toBeInTheDocument();
    expect(subtitleParagraph?.textContent).toContain('Code:');
  });

  it('highlights matching text in description', () => {
    const { container } = render(<GlobalSearchResultItem result={mockResult} highlightTerm="tower" />);

    // Find all mark elements
    const marks = container.querySelectorAll('mark');
    expect(marks.length).toBeGreaterThan(0);
    
    // Find the mark that contains "tower"
    const towerMark = Array.from(marks).find((mark) => mark.textContent === 'tower');
    expect(towerMark).toBeInTheDocument();
    
    // Verify it's within a paragraph that contains "beautiful"
    const descriptionParagraph = towerMark?.closest('p');
    expect(descriptionParagraph).toBeInTheDocument();
    expect(descriptionParagraph?.textContent).toContain('beautiful');
  });

  it('does not highlight when highlightTerm is empty', () => {
    render(<GlobalSearchResultItem result={mockResult} highlightTerm="" />);

    const titleElement = screen.getByText('Riviera Tower');
    const marks = titleElement.querySelectorAll('mark');
    expect(marks.length).toBe(0);
  });

  it('calls onClick when card is clicked', () => {
    const handleClick = vi.fn();
    render(<GlobalSearchResultItem result={mockResult} onClick={handleClick} />);

    const card = screen.getByTestId('global-search-result');
    fireEvent.click(card);

    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('calls onClick when Enter key is pressed on card', () => {
    const handleClick = vi.fn();
    render(<GlobalSearchResultItem result={mockResult} onClick={handleClick} />);

    const card = screen.getByTestId('global-search-result');
    fireEvent.keyDown(card, { key: 'Enter' });

    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('calls onClick when Space key is pressed on card', () => {
    const handleClick = vi.fn();
    render(<GlobalSearchResultItem result={mockResult} onClick={handleClick} />);

    const card = screen.getByTestId('global-search-result');
    fireEvent.keyDown(card, { key: ' ' });

    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('renders correct module label for tasks', () => {
    const taskResult: GlobalSearchResult = {
      ...mockResult,
      module: 'tasks',
      type: 'task',
      title: 'Design Task',
    };

    render(<GlobalSearchResultItem result={taskResult} />);
    expect(screen.getByText('Task')).toBeInTheDocument();
  });

  it('renders correct module label for documents', () => {
    const docResult: GlobalSearchResult = {
      ...mockResult,
      module: 'documents',
      type: 'document',
      title: 'Project Document',
    };

    render(<GlobalSearchResultItem result={docResult} />);
    expect(screen.getByText('Document')).toBeInTheDocument();
  });

  it('renders secondary action button for tasks with onSecondaryClick', () => {
    const taskResult: GlobalSearchResult = {
      ...mockResult,
      module: 'tasks',
      type: 'task',
      title: 'Task Title',
      project_id: 'proj-1',
    };

    const handleSecondaryClick = vi.fn();
    render(
      <GlobalSearchResultItem
        result={taskResult}
        onSecondaryClick={handleSecondaryClick}
      />
    );

    const secondaryButton = screen.getByTestId('secondary-action-button');
    expect(secondaryButton).toBeInTheDocument();
    expect(secondaryButton).toHaveTextContent('Open in project');

    fireEvent.click(secondaryButton);
    expect(handleSecondaryClick).toHaveBeenCalledTimes(1);
  });

  it('renders secondary action button for documents with onSecondaryClick', () => {
    const docResult: GlobalSearchResult = {
      ...mockResult,
      module: 'documents',
      type: 'document',
      title: 'Document Title',
      project_id: 'proj-1',
    };

    const handleSecondaryClick = vi.fn();
    render(
      <GlobalSearchResultItem
        result={docResult}
        onSecondaryClick={handleSecondaryClick}
      />
    );

    const secondaryButton = screen.getByTestId('secondary-action-button');
    expect(secondaryButton).toBeInTheDocument();
    expect(secondaryButton).toHaveTextContent('Open in project');
  });

  it('renders secondary action button for change_order cost with onSecondaryClick', () => {
    const costResult: GlobalSearchResult = {
      ...mockResult,
      module: 'cost',
      type: 'change_order',
      title: 'Change Order',
      project_id: 'proj-1',
      entity: { contract_id: 'contract-1' },
    };

    const handleSecondaryClick = vi.fn();
    render(
      <GlobalSearchResultItem
        result={costResult}
        onSecondaryClick={handleSecondaryClick}
      />
    );

    const secondaryButton = screen.getByTestId('secondary-action-button');
    expect(secondaryButton).toBeInTheDocument();
    expect(secondaryButton).toHaveTextContent('Open contract');
  });

  it('does not render secondary action button when onSecondaryClick is not provided', () => {
    render(<GlobalSearchResultItem result={mockResult} />);

    const secondaryButton = screen.queryByTestId('secondary-action-button');
    expect(secondaryButton).not.toBeInTheDocument();
  });

  it('does not render secondary action button for projects', () => {
    const handleSecondaryClick = vi.fn();
    render(
      <GlobalSearchResultItem
        result={mockResult}
        onSecondaryClick={handleSecondaryClick}
      />
    );

    const secondaryButton = screen.queryByTestId('secondary-action-button');
    expect(secondaryButton).not.toBeInTheDocument();
  });

  it('stops propagation when secondary button is clicked', () => {
    const handleClick = vi.fn();
    const handleSecondaryClick = vi.fn();

    const taskResult: GlobalSearchResult = {
      ...mockResult,
      module: 'tasks',
      type: 'task',
      title: 'Task Title',
      project_id: 'proj-1',
    };

    render(
      <GlobalSearchResultItem
        result={taskResult}
        onClick={handleClick}
        onSecondaryClick={handleSecondaryClick}
      />
    );

    const secondaryButton = screen.getByTestId('secondary-action-button');
    fireEvent.click(secondaryButton);

    // Secondary click should be called
    expect(handleSecondaryClick).toHaveBeenCalledTimes(1);
    // Primary click should NOT be called (propagation stopped)
    expect(handleClick).not.toHaveBeenCalled();
  });
});
