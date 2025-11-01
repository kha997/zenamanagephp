# `<x-shared.table-standardized>` API

Data-heavy Blade component used across dashboards. Source: `resources/views/components/shared/table-standardized.blade.php`.

## Props

| Name | Type | Default | Description |
| --- | --- | --- | --- |
| `title` | `string|null` | `null` | Heading above the table. |
| `subtitle` | `string|null` | `null` | Supporting text. |
| `columns` | `array` | `[]` | Column metadata (see below). |
| `items` | `array|\Illuminate\Support\Collection` | `[]` | Row data. |
| `actions` | `array` | `[]` | Row-level action descriptors (`type`, `icon`, `handler`). |
| `showBulkActions` | `bool` | `false` | If `true`, adds checkbox column + bulk toolbar. |
| `showActions` | `bool` | `true` | Toggle the right-most Actions column. |
| `showSearch` | `bool` | `false` | Enables top-level search input that dispatches `table-search`. |
| `showFilters` | `bool` | `false` | Shows Alpine-driven filter drawer + `{{ $filters }}` slot. |
| `pagination` | `\Illuminate\Contracts\Pagination\Paginator|null` | `null` | (Reserved) pass paginator data. |
| `emptyState` | `array|null` | default icon/text | Provide icon/title/description/action. |
| `loading` | `bool` | `false` | Adds overlay and disables interactions. |
| `sortable` | `bool` | `true` | Enables click-to-sort controls per column. |
| `sticky` | `bool` | `false` | Applies sticky header styles. |
| `variant` | `'default' \| 'compact' \| 'bordered'` | `'default'` | Layout density. |
| `theme` | `'light' \| 'dark'` | `'light'` | CSS token switch. |

### Column definition

```php
$columns = [
    [
        'key' => 'name',
        'label' => 'Project',
        'sortable' => true,
        'class' => 'w-1/3',
        'component' => 'components.shared.tables.project-name-cell', // optional Blade include
    ],
];
```

## Slots

- `filters`: optional slot used when `showFilters` is `true`.
- Default slot is not used; table renders from `items`.

## Events (Alpine)

| Event | Payload | Trigger |
| --- | --- | --- |
| `table-sort` | `{ field, direction }` | Clicking on sortable column controls. |
| `table-search` | `{ query }` | Typing into search input. |
| `table-refresh` | `{}` | After successful bulk delete. |

Listen via `x-on:table-sort.window="..."` or parent Alpine components.

## Usage

```blade
<x-shared.table-standardized
    title="Projects"
    subtitle="Active records in your tenant"
    :columns="$columns"
    :items="$projects"
    :show-bulk-actions="true"
    :actions="$rowActions"
>
    <x-slot name="filters">
        @include('projects.partials.filters')
    </x-slot>
</x-shared.table-standardized>
```

## Accessibility

- Table markup follows semantic `<table>/<thead>/<tbody>` ordering.
- Loading overlay supplies `aria-live="polite"` text via spinner container.
- Bulk action buttons include descriptive labels + icons to satisfy WCAG 1.3.
