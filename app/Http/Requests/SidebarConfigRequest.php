<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SidebarConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SidebarConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', SidebarConfig::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'role_name' => [
                'required',
                'string',
                Rule::in(SidebarConfig::VALID_ROLES),
            ],
            'config' => 'required|array',
            'config.items' => 'required|array|min:1',
            'config.items.*.id' => 'required|string|max:64',
            'config.items.*.type' => [
                'required',
                'string',
                Rule::in(['group', 'link', 'external', 'divider']),
            ],
            'config.items.*.label' => 'required|string|max:128',
            'config.items.*.icon' => 'nullable|string|max:32',
            'config.items.*.to' => 'nullable|string|max:255',
            'config.items.*.href' => 'nullable|url|max:500',
            'config.items.*.query' => 'nullable|array',
            'config.items.*.query.*' => 'string|max:128',
            'config.items.*.required_permissions' => 'nullable|array',
            'config.items.*.required_permissions.*' => 'string|max:64',
            'config.items.*.show_badge_from' => 'nullable|string|max:255',
            'config.items.*.enabled' => 'boolean',
            'config.items.*.order' => 'integer|min:0|max:9999',
            'config.items.*.pinned' => 'boolean',
            'config.items.*.show_if' => 'nullable|array',
            'config.items.*.show_if.*' => 'string|max:128',
            
            // Group-specific validation
            'config.items.*.children' => 'nullable|array|required_if:config.items.*.type,group',
            'config.items.*.children.*.id' => 'required_with:config.items.*.children|string|max:64',
            'config.items.*.children.*.type' => [
                'required_with:config.items.*.children',
                'string',
                Rule::in(['link', 'external', 'divider']),
            ],
            'config.items.*.children.*.label' => 'required_with:config.items.*.children|string|max:128',
            'config.items.*.children.*.icon' => 'nullable|string|max:32',
            'config.items.*.children.*.to' => 'nullable|string|max:255',
            'config.items.*.children.*.href' => 'nullable|url|max:500',
            'config.items.*.children.*.query' => 'nullable|array',
            'config.items.*.children.*.query.*' => 'string|max:128',
            'config.items.*.children.*.required_permissions' => 'nullable|array',
            'config.items.*.children.*.required_permissions.*' => 'string|max:64',
            'config.items.*.children.*.show_badge_from' => 'nullable|string|max:255',
            'config.items.*.children.*.enabled' => 'boolean',
            'config.items.*.children.*.order' => 'integer|min:0|max:9999',
            'config.items.*.children.*.pinned' => 'boolean',
            'config.items.*.children.*.show_if' => 'nullable|array',
            'config.items.*.children.*.show_if.*' => 'string|max:128',
            
            'tenant_id' => 'nullable|ulid|exists:tenants,id',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'config.items.required' => 'Sidebar configuration must have at least one item.',
            'config.items.*.id.required' => 'Each sidebar item must have a unique ID.',
            'config.items.*.type.required' => 'Each sidebar item must have a type.',
            'config.items.*.type.in' => 'Sidebar item type must be one of: group, link, external, divider.',
            'config.items.*.label.required' => 'Each sidebar item must have a label.',
            'config.items.*.label.max' => 'Sidebar item label cannot exceed 128 characters.',
            'config.items.*.to.max' => 'Internal link path cannot exceed 255 characters.',
            'config.items.*.href.url' => 'External link must be a valid URL.',
            'config.items.*.href.max' => 'External link URL cannot exceed 500 characters.',
            'config.items.*.required_permissions.*.max' => 'Permission names cannot exceed 64 characters.',
            'config.items.*.order.max' => 'Item order cannot exceed 9999.',
            'config.items.*.children.required_if' => 'Group items must have children.',
            'config.items.*.children.*.id.required_with' => 'Child items must have unique IDs.',
            'config.items.*.children.*.type.required_with' => 'Child items must have a type.',
            'config.items.*.children.*.type.in' => 'Child item type must be one of: link, external, divider.',
            'config.items.*.children.*.label.required_with' => 'Child items must have labels.',
            'config.items.*.children.*.label.max' => 'Child item label cannot exceed 128 characters.',
            'config.items.*.children.*.to.max' => 'Child internal link path cannot exceed 255 characters.',
            'config.items.*.children.*.href.url' => 'Child external link must be a valid URL.',
            'config.items.*.children.*.href.max' => 'Child external link URL cannot exceed 500 characters.',
            'config.items.*.children.*.required_permissions.*.max' => 'Child permission names cannot exceed 64 characters.',
            'config.items.*.children.*.order.max' => 'Child item order cannot exceed 9999.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'config.items.*.id' => 'item ID',
            'config.items.*.type' => 'item type',
            'config.items.*.label' => 'item label',
            'config.items.*.icon' => 'item icon',
            'config.items.*.to' => 'internal link',
            'config.items.*.href' => 'external link',
            'config.items.*.query' => 'query parameters',
            'config.items.*.required_permissions' => 'required permissions',
            'config.items.*.show_badge_from' => 'badge endpoint',
            'config.items.*.enabled' => 'enabled status',
            'config.items.*.order' => 'item order',
            'config.items.*.pinned' => 'pinned status',
            'config.items.*.show_if' => 'conditional display',
            'config.items.*.children' => 'child items',
            'config.items.*.children.*.id' => 'child item ID',
            'config.items.*.children.*.type' => 'child item type',
            'config.items.*.children.*.label' => 'child item label',
            'config.items.*.children.*.icon' => 'child item icon',
            'config.items.*.children.*.to' => 'child internal link',
            'config.items.*.children.*.href' => 'child external link',
            'config.items.*.children.*.query' => 'child query parameters',
            'config.items.*.children.*.required_permissions' => 'child required permissions',
            'config.items.*.children.*.show_badge_from' => 'child badge endpoint',
            'config.items.*.children.*.enabled' => 'child enabled status',
            'config.items.*.children.*.order' => 'child item order',
            'config.items.*.children.*.pinned' => 'child pinned status',
            'config.items.*.children.*.show_if' => 'child conditional display',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateUniqueIds($validator);
            $this->validateRequiredFields($validator);
            $this->validateLinkFields($validator);
        });
    }

    /**
     * Validate that all item IDs are unique.
     */
    protected function validateUniqueIds($validator): void
    {
        $items = $this->input('config.items', []);
        $ids = [];
        
        foreach ($items as $index => $item) {
            if (isset($item['id'])) {
                if (in_array($item['id'], $ids)) {
                    $validator->errors()->add(
                        "config.items.{$index}.id",
                        "Item ID '{$item['id']}' is not unique."
                    );
                }
                $ids[] = $item['id'];
            }
            
            // Check child item IDs
            if (isset($item['children'])) {
                foreach ($item['children'] as $childIndex => $child) {
                    if (isset($child['id'])) {
                        if (in_array($child['id'], $ids)) {
                            $validator->errors()->add(
                                "config.items.{$index}.children.{$childIndex}.id",
                                "Child item ID '{$child['id']}' is not unique."
                            );
                        }
                        $ids[] = $child['id'];
                    }
                }
            }
        }
    }

    /**
     * Validate required fields based on item type.
     */
    protected function validateRequiredFields($validator): void
    {
        $items = $this->input('config.items', []);
        
        foreach ($items as $index => $item) {
            $type = $item['type'] ?? null;
            
            switch ($type) {
                case 'link':
                    if (empty($item['to'])) {
                        $validator->errors()->add(
                            "config.items.{$index}.to",
                            "Link items must have a 'to' field."
                        );
                    }
                    break;
                    
                case 'external':
                    if (empty($item['href'])) {
                        $validator->errors()->add(
                            "config.items.{$index}.href",
                            "External items must have an 'href' field."
                        );
                    }
                    break;
                    
                case 'group':
                    if (empty($item['children']) || !is_array($item['children'])) {
                        $validator->errors()->add(
                            "config.items.{$index}.children",
                            "Group items must have children."
                        );
                    }
                    break;
            }
        }
    }

    /**
     * Validate link fields are mutually exclusive.
     */
    protected function validateLinkFields($validator): void
    {
        $items = $this->input('config.items', []);
        
        foreach ($items as $index => $item) {
            $hasTo = !empty($item['to']);
            $hasHref = !empty($item['href']);
            
            if ($hasTo && $hasHref) {
                $validator->errors()->add(
                    "config.items.{$index}",
                    "Items cannot have both 'to' and 'href' fields."
                );
            }
            
            // Check child items
            if (isset($item['children'])) {
                foreach ($item['children'] as $childIndex => $child) {
                    $childHasTo = !empty($child['to']);
                    $childHasHref = !empty($child['href']);
                    
                    if ($childHasTo && $childHasHref) {
                        $validator->errors()->add(
                            "config.items.{$index}.children.{$childIndex}",
                            "Child items cannot have both 'to' and 'href' fields."
                        );
                    }
                }
            }
        }
    }
}
