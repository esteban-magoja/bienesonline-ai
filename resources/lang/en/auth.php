<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | Translations for authentication pages
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // Login
    'login' => [
        'page_title' => 'Sign In',
        'headline' => 'Sign In',
        'subheadline' => 'Login to your account below',
        'email_address' => 'Email Address',
        'password' => 'Password',
        'remember_me' => 'Remember me',
        'edit' => 'Edit',
        'button' => 'Continue',
        'forget_password' => 'Forget your password?',
        'dont_have_an_account' => "Don't have an account?",
        'sign_up' => 'Sign up',
        'social_auth_authenticated_message' => 'You have been authenticated via __social_providers_list__. Please login to that network below.',
        'change_email' => 'Change Email',
        'couldnt_find_your_account' => "Couldn't find your account",
    ],

    // Register
    'register' => [
        'page_title' => 'Sign Up',
        'headline' => 'Sign Up',
        'subheadline' => 'Register for your free account below.',
        'name' => 'Name',
        'email_address' => 'Email Address',
        'password' => 'Password',
        'password_confirmation' => 'Confirm Password',
        'already_have_an_account' => 'Already have an account?',
        'sign_in' => 'Sign in',
        'button' => 'Continue',
        'email_registration_disabled' => 'Email registration is currently disabled. Please use social login.',
        'registrations_disabled' => 'Registrations are currently disabled.',
    ],

    // Verify Email
    'verify' => [
        'page_title' => 'Verify Your Account',
        'headline' => 'Verify your email address',
        'page_title' => 'Verify Your Account',
        'headline' => 'Verify your account',
        'subheadline' => 'Two steps to fully activate your access.',
        'email_step_title' => '1. Verify your email address',
        'description' => 'We sent you a verification link. Check your spam folder if you don\'t see it. If you didn\'t receive it,',
        'new_request_link' => 'click here to resend',
        'check_spam' => 'Check your spam folder if it doesn\'t appear in your inbox.',
        'whatsapp_step_title' => '2. Confirm your WhatsApp number',
        'whatsapp_description' => 'We sent a WhatsApp message to the number you registered. Follow the instructions in the message to verify your number.',
        'whatsapp_required_notice' => 'WhatsApp verification is mandatory. The entire alerts, matches, and notifications system works through that number.',
        'go_dashboard' => 'Go to dashboard',
        'new_link_sent' => 'A new link has been sent to your email address.',
        'or' => 'Or',
        'logout' => 'sign out',
    ],

    // Password Confirm
    'password_confirm' => [
        'page_title' => 'Confirm Your Password',
        'headline' => 'Confirm Password',
        'subheadline' => 'Be sure to confirm your password below',
        'password' => 'Password',
        'button' => 'Confirm password',
    ],

    // Password Reset Request
    'password_reset_request' => [
        'page_title' => 'Request a Password Reset',
        'headline' => 'Reset password',
        'subheadline' => 'Enter your email below to reset your password',
        'email' => 'Email Address',
        'button' => 'Send password reset link',
        'or' => 'or',
        'return_to_login' => 'return to login',
    ],

    // Password Reset
    'password_reset' => [
        'page_title' => 'Reset Your Password',
        'headline' => 'Reset Password',
        'subheadline' => 'Reset your password below',
        'email' => 'Email Address',
        'password' => 'Password',
        'password_confirm' => 'Confirm Password',
        'button' => 'Reset Password',
    ],

    // Two Factor Challenge
    'two_factor_challenge' => [
        'page_title' => 'Two Factor Challenge',
        'headline_auth' => 'Authentication Code',
        'subheadline_auth' => 'Enter the authentication code provided by your authenticator application.',
        'headline_recovery' => 'Recovery Code',
        'subheadline_recovery' => 'Please confirm access to your account by entering one of your emergency recovery codes.',
    ],

    // Signup (Custom)
    'signup' => [
        'page_title' => 'Sign Up',
        'headline' => 'Create Account',
        'subheadline' => 'Join our platform',
        'name' => 'Full Name',
        'agency' => 'Real estate agency / company name',
        'agency_placeholder' => 'e.g: Rodriguez Real Estate',
        'agency_help' => 'Optional. Fill this in only if you represent a real estate agency or company.',
        'email' => 'Email Address',
        'phone' => 'Mobile Phone (WhatsApp)',
        'phone_country_code' => 'Country',
        'phone_local' => 'Phone number',
        'phone_local_placeholder' => 'e.g: 91112345678',
        'phone_local_help' => 'Local number only, without the country code or leading zeros.',
        'phone_preview' => 'Full number',
        'phone_placeholder' => '+1600123456',
        'phone_help' => 'International format for WhatsApp e.g: +1600123456',
        'password' => 'Password',
        'password_confirmation' => 'Confirm Password',
        'button' => 'Create Account',
        'have_account' => 'Already have an account?',
        'login_link' => 'Sign In',
        'whatsapp_opt_in' => 'I agree to receive WhatsApp notifications about new properties, matches, and system alerts.',
        'whatsapp_opt_in_required' => 'You must agree to receive WhatsApp notifications to continue.',
    ],

    // Verify Email Template
    'verify_email' => [
        'subject' => 'Verify Your Email Address',
        'greeting' => 'Welcome to the site :name',
        'body' => 'Your registered email is :email. Please click on the link below to verify your email account.',
        'action' => 'Verify Email',
        'outro' => 'If you did not create an account, no further action is required.',
    ],

    // Phone number verification
    'phone_verification' => [
        'verified' => 'Your phone number has been verified successfully!',
        'already_verified' => 'Your phone number was already verified.',
        'invalid_token' => 'The verification link is invalid or has already been used.',
        'title_success' => 'Phone verified',
        'title_error' => 'Invalid link',
        'subtitle_success' => 'Your WhatsApp number has been confirmed.',
        'subtitle_error' => 'We could not verify your phone number.',
        'go_home' => 'Go to home',
        'go_dashboard' => 'Go to dashboard',
    ],
];
