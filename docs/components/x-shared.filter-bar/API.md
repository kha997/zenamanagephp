# `<x-shared.filter-bar>` API

Shared Blade component that renders search, filter, sort, bulk actions, and view-mode controls. Source: `resources/views/components/shared/filter-bar.blade.php`.

## Props

| Name | Type | Default | Description |
| --- | --- | --- | --- |
| `search` | `bool` | `true` | Toggle search input. |
| `searchPlaceholder` | `string` | `'Search...'` | Placeholder text. |
| `filters` | `array` | `[]` | Each filter: `{ key, label, type, options?, placeholder? }`. |
| `sortOptions` | `array` | `[]` | `[ { label: 'Updated', value: 'updated_at_desc' } ]`. |
| `viewModes` | `array` | `['table','grid','list']` | Buttons for layout toggle. |
| `currentViewMode` | `string` | `'table'` | Initial mode. |
| `bulkActions` | `array` | `[]` | Each entry: `{ label, icon, handler }` (handler is Alpine expression). |
| `showFilters` | `bool` | `true` | Toggle filter drawer button. |
| `showSort` | `bool` | `true` | Toggle sort dropdown. |
| `showViewMode` | `bool` | `true` | Toggle view-mode buttons. |
| `theme` | `'light' \| 'dark'` | `'light'` | Hook for theming. |

## Slots

- `actions`: `<x-slot name="actions">` adds custom buttons to the right-hand side.

## Events

| Event | Payload | Description |
| --- | --- | --- |
| `filter-search` | `{ query }` | Fired when typing in the search box. |
| `filter-sort` | `{ sortBy, sortDirection }` | Fired when sort select changes. |
| `filter-view-mode` | `{ viewMode }` | Fired when toggling between table/grid/list. |
| `filter-apply` | `{ filters }` | Fired after changing a filter control. |
| `filter-clear` | `{}` | Fired after clearing filters. |

Listen with `x-on:filter-search.window="handleSearch($event.detail)"`.

## Usage

```blade
<x-shared.filter-bar
    :filters="$filters"
    :sort-options="$sortOptions"
    current-view-mode="grid"
>
    <x-slot name="actions">
        <x-shared.button-standardized icon="download">Export</x-shared.button-standardized>
    </x-slot>
</x-shared.filter-bar>
```

## Accessibility

- Buttons render focus states and include icon text.
- Sort/select controls leverage native inputs for screen-reader compatibility.
- Bulk action chip shows `x-text="selectedItems.length"` so assistive tech can announce the count.
