<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnhancedTenantExportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Export type
            'export_type' => 'required|in:current,selected,all,template',
            
            // Export format
            'export_format' => 'required|in:csv,excel,pdf,json',
            
            // Columns to export
            'columns' => 'required|array|min:1',
            'columns.*' => 'string|in:id,name,domain,status,plan,users_count,projects_count,storage_used,region,created_at,updated_at,trial_ends_at',
            
            // Export options
            'include_headers' => 'boolean',
            'include_metadata' => 'boolean',
            'compress_file' => 'boolean',
            'email_delivery' => 'boolean',
            
            // Email configuration
            'email_address' => 'required_if:email_delivery,true|email',
            'email_subject' => 'string|max:255',
            'email_message' => 'string|max:1000',
            
            // Scheduling
            'schedule_export' => 'boolean',
            'schedule_frequency' => 'required_if:schedule_export,true|in:daily,weekly,monthly',
            'schedule_time' => 'required_if:schedule_export,true|date_format:H:i',
            'schedule_name' => 'required_if:schedule_export,true|string|max:255',
            
            // Template
            'template_id' => 'required_if:export_type,template|string',
            
            // Selected tenants
            'tenant_ids' => 'required_if:export_type,selected|array',
            'tenant_ids.*' => 'string|exists:tenants,id',
            
            // Current view filters (same as TenantIndexRequest)
            'q' => 'nullable|string|max:255',
            'status' => 'nullable|in:trial,active,suspended,archived',
            'plan' => 'nullable|in:free,pro,enterprise',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'range' => 'nullable|in:7d,30d,90d',
            'region' => 'nullable|string|max:255',
            'min_users' => 'nullable|integer|min:0',
            'max_users' => 'nullable|integer|min:0|gte:min_users',
            'min_projects' => 'nullable|integer|min:0',
            'max_projects' => 'nullable|integer|min:0|gte:min_projects',
            'min_storage' => 'nullable|integer|min:0',
            'max_storage' => 'nullable|integer|min:0|gte:min_storage',
            'trial_expiring' => 'nullable|boolean',
            'subscription_status' => 'nullable|string|max:255',
            'owner_email' => 'nullable|email',
            'tags' => 'nullable|string|max:255',
            'sort' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'export_type.required' => 'Export type is required.',
            'export_type.in' => 'Export type must be one of: current, selected, all, template.',
            
            'export_format.required' => 'Export format is required.',
            'export_format.in' => 'Export format must be one of: csv, excel, pdf, json.',
            
            'columns.required' => 'At least one column must be selected.',
            'columns.min' => 'At least one column must be selected.',
            'columns.*.in' => 'Invalid column selected.',
            
            'email_address.required_if' => 'Email address is required when email delivery is enabled.',
            'email_address.email' => 'Email address must be a valid email.',
            
            'schedule_frequency.required_if' => 'Schedule frequency is required when scheduling export.',
            'schedule_frequency.in' => 'Schedule frequency must be one of: daily, weekly, monthly.',
            
            'schedule_time.required_if' => 'Schedule time is required when scheduling export.',
            'schedule_time.date_format' => 'Schedule time must be in HH:MM format.',
            
            'schedule_name.required_if' => 'Schedule name is required when scheduling export.',
            
            'template_id.required_if' => 'Template ID is required when using template export.',
            
            'tenant_ids.required_if' => 'Tenant IDs are required when exporting selected tenants.',
            'tenant_ids.*.exists' => 'One or more selected tenants do not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'export_type' => 'export type',
            'export_format' => 'export format',
            'columns' => 'columns',
            'email_address' => 'email address',
            'email_subject' => 'email subject',
            'email_message' => 'email message',
            'schedule_frequency' => 'schedule frequency',
            'schedule_time' => 'schedule time',
            'schedule_name' => 'schedule name',
            'template_id' => 'template ID',
            'tenant_ids' => 'tenant IDs',
        ];
    }
}
