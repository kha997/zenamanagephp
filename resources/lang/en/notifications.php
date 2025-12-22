<?php

return [
    // Email Templates
    'task_completed_title' => 'Task Completed',
    'task_completed_greeting' => 'Hello :name,',
    'task_details' => 'Task Details',
    'task_title' => 'Task Title',
    'project' => 'Project',
    'completed_by' => 'Completed By',
    'completed_at' => 'Completed At',
    'view_task' => 'View Task',
    'view_project' => 'View Project',
    
    'quote_sent_title' => 'Quote Sent',
    'quote_sent_greeting' => 'Dear :name,',
    'quote_details' => 'Quote Details',
    'quote_number' => 'Quote Number',
    'project_type' => 'Project Type',
    'total_amount' => 'Total Amount',
    'valid_until' => 'Valid Until',
    'view_quote' => 'View Quote',
    'download_pdf' => 'Download PDF',
    
    'client_created_title' => 'New Client Created',
    'client_created_greeting' => 'Hello :name,',
    'client_details' => 'Client Details',
    'client_name' => 'Client Name',
    'client_email' => 'Email',
    'client_phone' => 'Phone',
    'client_type' => 'Client Type',
    'potential_client' => 'Potential Client',
    'signed_client' => 'Signed Client',
    'created_by' => 'Created By',
    'view_client' => 'View Client',
    'view_all_clients' => 'View All Clients',
    
    'email_footer' => 'Thank you for using ZenaManage.',
    
    // UI Labels
    'notifications' => 'Notifications',
    'mark_all_read' => 'Mark all as read',
    'no_notifications' => 'No notifications',
    'view_all' => 'View all notifications',
    'email_subject' => 'Notification: :type',
    
    // Message Templates
    'task_completed_message' => 'Task ":task_title" in project ":project_name" has been completed.',
    'quote_sent_message' => 'Quote ":quote_number" has been sent to ":client_name".',
    'client_created_message' => 'New client ":client_name" has been created by ":created_by".',
    
    // Notification Types
    'notification_types' => [
        'task_completed' => 'Task Completed',
        'quote_sent' => 'Quote Sent',
        'client_created' => 'New Client',
        'project_updated' => 'Project Updated',
        'deadline_approaching' => 'Deadline Approaching',
    ],
    
    // Notification Channels
    'channels' => [
        'email' => 'Email',
        'in_app' => 'In-App',
        'sms' => 'SMS',
    ],
    
    // Notification Priorities
    'priorities' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'urgent' => 'Urgent',
    ],
    
    // Notification Status
    'status' => [
        'unread' => 'Unread',
        'read' => 'Read',
        'archived' => 'Archived',
    ],
];
