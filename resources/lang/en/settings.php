<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Settings Translations - English
    |--------------------------------------------------------------------------
    |
    | Translations for settings section
    |
    */

    // Profile
    'profile' => [
        'title' => 'Profile',
        'my_account' => 'My Account',
        'my_account_description' => 'Manage your avatar, name, email, and more.',
        'update_profile' => 'Update Profile',
        'full_name' => 'Full Name',
        'email' => 'Email Address',
        'agency' => 'Agency/Real Estate',
        'phone' => 'Mobile Phone (WhatsApp)',
        'phone_placeholder' => '+1234567890',
        'address' => 'Address',
        'city' => 'City',
        'state' => 'State/Province',
        'country' => 'Country',
        'avatar' => 'Profile Photo',
        'change_avatar' => 'Change Photo',
        'save' => 'Save Changes',
        'saved_success' => 'Profile updated successfully',
        'language' => 'Preferred Language',
        'language_description' => 'Select your language for emails and notifications',
    ],

    // Security
    'security' => [
        'title' => 'Security',
        'change_password' => 'Change Password',
        'current_password' => 'Current Password',
        'new_password' => 'New Password',
        'confirm_password' => 'Confirm Password',
        'update_password' => 'Update Password',
        'password_changed' => 'Password changed successfully',
        'two_factor' => 'Two-Factor Authentication',
        'enable_2fa' => 'Enable 2FA',
        'disable_2fa' => 'Disable 2FA',
        'active_sessions' => 'Active Sessions',
        'logout_other' => 'Logout Other Sessions',
    ],

    // Subscription
    'subscription' => [
        'title' => 'Subscription',
        'current_plan' => 'Current Plan',
        'plan_details' => 'Your membership details',
        'change_plan' => 'Change Plan',
        'cancel_subscription' => 'Cancel Subscription',
        'renews_on' => 'Renews on',
        'expires_on' => 'Expires on',
        'free_plan' => 'Free Plan',
        'upgrade' => 'Upgrade',
        'manage_billing' => 'Manage Billing',
        'admin_notice' => 'You are logged in as an admin and have full access. Authenticate with a different user and visit this page to see the subscription checkout process.',
        'currently_subscribed' => 'You are currently subscribed to the :plan :interval plan.',
        'manage_below' => 'Manage your subscription by clicking below.',
        'updated_success' => 'Your subscription has been successfully updated',
        'no_active_subscription' => 'No active subscriptions found. Please select a plan below.',
        'billing_managed_by' => 'Billing is securely managed through',
        'switch_plans_title' => 'Switch Plans',
        'switch_plans_confirm' => 'Are you sure you want to switch to the',
        'yes_switch' => 'Yes, Switch My Plan',
        'no_thanks' => 'No Thanks',
    ],

    // Invoices
    'invoices' => [
        'title' => 'Invoices',
        'invoice_history' => 'Your previous plan invoices',
        'date' => 'Date of Invoice',
        'amount' => 'Price',
        'status' => 'Status',
        'download' => 'Download',
        'pdf_download' => 'PDF Download',
        'no_invoices' => 'No invoices available.',
        'no_invoices_message' => 'You don\'t have any previous invoices. When you subscribe to a plan, you\'ll see your previous invoices here.',
        'paid' => 'Paid',
        'pending' => 'Pending',
        'failed' => 'Failed',
    ],

    // API
    'api' => [
        'title' => 'API',
        'api_tokens' => 'Manage your API Keys',
        'create_token' => 'Create New Key',
        'token_name' => 'Name',
        'token_created' => 'Successfully created new API Key',
        'token_revoked' => 'Token revoked successfully',
        'revoke' => 'Revoke',
        'permissions' => 'Permissions',
        'last_used' => 'Last Used',
        'never' => 'Never',
        'created_at' => 'Created',
        'current_tokens' => 'Current API Keys',
        'documentation' => 'API Documentation',
    ],

    // General
    'general' => [
        'cancel' => 'Cancel',
        'save' => 'Save',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'confirm' => 'Confirm',
        'success' => 'Success',
        'error' => 'Error',
        'loading' => 'Loading...',
    ],

    // Menu
    'menu' => [
        'configuration' => 'Configuration',
        'profile' => 'Profile',
        'security' => 'Security',
        'api' => 'API Keys',
        'billing' => 'Billing',
        'subscription' => 'Subscription',
        'invoices' => 'Invoices',
    ],
];
