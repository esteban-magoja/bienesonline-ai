<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard Translations - English
    |--------------------------------------------------------------------------
    |
    | User dashboard translations
    |
    */

    // Main menu
    'dashboard' => 'Dashboard',
    'my_listings' => 'My Listings',
    'my_requests' => 'My Requests',
    'matches' => 'Matches',
    'messages' => 'Messages',
    'settings' => 'Settings',
    'logout' => 'Logout',
    
    // Dashboard home
    'welcome' => 'Welcome, :name',
    'quick_stats' => 'Quick Stats',
    'total_listings' => 'Total Listings',
    'active_requests' => 'Active Requests',
    'total_matches' => 'Matches Found',
    'recent_activity' => 'Recent Activity',
    'no_activity' => 'No recent activity',
    
    // Listings
    'listings' => [
        'title' => 'My Listings',
        'create' => 'Create Listing',
        'edit' => 'Edit Listing',
        'delete' => 'Delete Listing',
        'view' => 'View Listing',
        'no_listings' => 'You have no listings',
        'create_first' => 'Create your first listing',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'publish' => 'Publish',
        'unpublish' => 'Unpublish',
        'featured' => 'Featured',
        'views' => 'Views',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],
    
    // Requests
    'requests' => [
        'title' => 'My Requests',
        'create' => 'Create Request',
        'edit' => 'Edit Request',
        'delete' => 'Delete Request',
        'view' => 'View Request',
        'view_matches' => 'View Matches',
        'no_requests' => 'You have no active requests',
        'create_first' => 'Create your first request',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'expired' => 'Expired',
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
        'expires_at' => 'Expires at',
        'never_expires' => 'No expiration',
    ],
    
    // Request form
    'request_form' => [
        'title_label' => 'Request Title',
        'title_placeholder' => 'E.g: Looking for house with garden in Córdoba',
        'description_label' => 'Description',
        'description_placeholder' => 'Describe what you are looking for (minimum 20 characters)',
        'property_type' => 'Property Type',
        'transaction_type' => 'Transaction Type',
        'budget' => 'Budget',
        'min_budget' => 'Minimum Budget',
        'max_budget' => 'Maximum Budget',
        'currency' => 'Currency',
        'location' => 'Location',
        'country' => 'Country',
        'province' => 'Province',
        'city' => 'City',
        'minimum_features' => 'Minimum Features (Optional)',
        'min_bedrooms' => 'Minimum Bedrooms',
        'min_bathrooms' => 'Minimum Bathrooms',
        'min_garages' => 'Minimum Garages',
        'min_area' => 'Minimum Area (sqm)',
        'expiration' => 'Expiration Date (Optional)',
        'save' => 'Save Request',
        'update' => 'Update Request',
        'cancel' => 'Cancel',
    ],
    
    // Matches
    'matches_section' => [
        'title' => 'Matches',
        'for_listing' => 'Matches for',
        'for_request' => 'Matching Properties',
        'no_matches' => 'No matches found',
        'match_level' => 'Match Level',
        'exact_match' => 'Exact Match',
        'intelligent_match' => 'Intelligent Match',
        'flexible_match' => 'Flexible Match',
        'match_score' => ':score% match',
        'reasons' => 'Reasons',
        'contact_requester' => 'Contact Requester',
        'requester_info' => 'Requester Information',
        'see_all_matches' => 'See All Matches',
        'matches_summary' => 'Matches Summary',
    ],
    
    // Messages
    'messages_section' => [
        'title' => 'Messages',
        'inbox' => 'Inbox',
        'sent' => 'Sent',
        'no_messages' => 'You have no messages',
        'new_message' => 'New Message',
        'reply' => 'Reply',
        'delete' => 'Delete',
        'mark_read' => 'Mark as Read',
        'mark_unread' => 'Mark as Unread',
    ],
    
    // Common actions
    'actions' => [
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'view' => 'View',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'confirm' => 'Confirm',
        'back' => 'Back',
        'search' => 'Search',
        'filter' => 'Filter',
        'export' => 'Export',
        'import' => 'Import',
    ],
    
    // Confirmations
    'confirmations' => [
        'delete_listing' => 'Are you sure you want to delete this listing?',
        'delete_request' => 'Are you sure you want to delete this request?',
        'delete_message' => 'Are you sure you want to delete this message?',
        'cannot_undo' => 'This action cannot be undone.',
    ],
    
    // Language tabs
    'languages' => [
        'spanish' => 'Español',
        'english' => 'English',
        'fill_both' => 'Fill in information in both languages',
        'spanish_required' => 'Spanish (required)',
        'english_optional' => 'English (optional)',
    ],
];
