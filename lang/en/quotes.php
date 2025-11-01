<?php

return [
    // Page titles
    'title' => 'Quotes',
    'subtitle' => 'Manage your quotes and proposals',
    'create_quote' => 'Create Quote',
    'create_quote_description' => 'Create a new quote for your client',
    'edit_quote' => 'Edit Quote',
    'back_to_quotes' => 'Back to Quotes',

    // Quote information
    'quote' => 'Quote',
    'quote_list' => 'Quote List',
    'title' => 'Title',
    'description' => 'Description',
    'type' => 'Type',
    'status' => 'Status',
    'amount' => 'Amount',
    'total_amount' => 'Total Amount',
    'tax_rate' => 'Tax Rate',
    'tax_amount' => 'Tax Amount',
    'discount_amount' => 'Discount Amount',
    'final_amount' => 'Final Amount',
    'valid_until' => 'Valid Until',
    'created' => 'Created',
    'sent_at' => 'Sent At',
    'viewed_at' => 'Viewed At',
    'accepted_at' => 'Accepted At',
    'rejected_at' => 'Rejected At',
    'rejection_reason' => 'Rejection Reason',

    // Quote types
    'design' => 'Design',
    'construction' => 'Construction',

    // Quote statuses
    'draft' => 'Draft',
    'sent' => 'Sent',
    'viewed' => 'Viewed',
    'accepted' => 'Accepted',
    'rejected' => 'Rejected',
    'expired' => 'Expired',

    // Client information
    'client' => 'Client',
    'project' => 'Project',

    // Statistics
    'total_quotes' => 'Total Quotes',
    'accepted' => 'Accepted',
    'expiring_soon' => 'Expiring Soon',
    'total_value' => 'Total Value',

    // Filters
    'search' => 'Search',
    'search_placeholder' => 'Search quotes...',
    'all_statuses' => 'All Statuses',
    'all_types' => 'All Types',
    'all_clients' => 'All Clients',

    // Actions
    'actions' => 'Actions',
    'view' => 'View',
    'edit' => 'Edit',
    'send' => 'Send',
    'accept' => 'Accept',
    'reject' => 'Reject',
    'cancel' => 'Cancel',
    'save' => 'Save',
    'delete' => 'Delete',
    'filter' => 'Filter',

    // Form sections
    'basic_information' => 'Basic Information',
    'quote_details' => 'Quote Details',
    'line_items' => 'Line Items',
    'terms_conditions' => 'Terms & Conditions',

    // Line items
    'item_description' => 'Item Description',
    'quantity' => 'Quantity',
    'unit_price' => 'Unit Price',
    'total' => 'Total',

    // Messages
    'quote_created_successfully' => 'Quote created successfully.',
    'quote_updated_successfully' => 'Quote updated successfully.',
    'quote_deleted_successfully' => 'Quote deleted successfully.',
    'quote_sent_successfully' => 'Quote sent successfully to client.',
    'quote_accepted_successfully' => 'Quote accepted successfully. Project created.',
    'quote_rejected_successfully' => 'Quote rejected successfully.',
    'failed_to_create_quote' => 'Failed to create quote. Please try again.',
    'failed_to_update_quote' => 'Failed to update quote. Please try again.',
    'failed_to_delete_quote' => 'Failed to delete quote. Please try again.',
    'failed_to_send_quote' => 'Failed to send quote. Please try again.',
    'failed_to_accept_quote' => 'Failed to accept quote. Please try again.',
    'failed_to_reject_quote' => 'Failed to reject quote. Please try again.',
    'quote_cannot_be_sent' => 'Quote cannot be sent in its current status.',
    'quote_cannot_be_accepted' => 'Quote cannot be accepted in its current status.',
    'quote_cannot_be_rejected' => 'Quote cannot be rejected in its current status.',

    // Empty states
    'no_quotes' => 'No Quotes',
    'no_quotes_description' => 'You haven\'t created any quotes yet. Start by creating your first quote.',
    'create_first_quote' => 'Create First Quote',

    // Validation
    'client_required' => 'Client is required.',
    'title_required' => 'Title is required.',
    'total_amount_required' => 'Total amount is required.',
    'valid_until_required' => 'Valid until date is required.',
    'valid_until_after_today' => 'Valid until date must be after today.',
    'tax_rate_max' => 'Tax rate cannot exceed 100%.',
    'discount_amount_min' => 'Discount amount cannot be negative.',

    // Status messages
    'quote_is_expired' => 'This quote has expired.',
    'quote_expiring_soon' => 'This quote is expiring soon.',
    'quote_can_be_sent' => 'This quote can be sent to the client.',
    'quote_can_be_accepted' => 'This quote can be accepted.',
    'quote_can_be_rejected' => 'This quote can be rejected.',
];
