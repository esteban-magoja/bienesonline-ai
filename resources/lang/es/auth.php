<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | Traducciones para las páginas de autenticación
    |
    */

    'failed' => 'Las credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña proporcionada es incorrecta.',
    'throttle' => 'Demasiados intentos de inicio de sesión. Por favor, inténtalo de nuevo en :seconds segundos.',

    // Login
    'login' => [
        'page_title' => 'Iniciar Sesión',
        'headline' => 'Iniciar Sesión',
        'subheadline' => 'Ingresa a tu cuenta',
        'email_address' => 'Correo Electrónico',
        'password' => 'Contraseña',
        'remember_me' => 'Recordarme',
        'edit' => 'Editar',
        'button' => 'Continuar',
        'forget_password' => '¿Olvidaste tu contraseña?',
        'dont_have_an_account' => '¿No tienes una cuenta?',
        'sign_up' => 'Regístrate',
        'social_auth_authenticated_message' => 'Has sido autenticado mediante __social_providers_list__. Por favor, inicia sesión en esa red.',
        'change_email' => 'Cambiar Email',
        'couldnt_find_your_account' => 'No pudimos encontrar tu cuenta',
    ],

    // Register
    'register' => [
        'page_title' => 'Crear Cuenta',
        'headline' => 'Crear Cuenta',
        'subheadline' => 'Regístrate para obtener tu cuenta gratuita.',
        'name' => 'Nombre',
        'email_address' => 'Correo Electrónico',
        'password' => 'Contraseña',
        'password_confirmation' => 'Confirmar Contraseña',
        'already_have_an_account' => '¿Ya tienes una cuenta?',
        'sign_in' => 'Iniciar Sesión',
        'button' => 'Continuar',
        'email_registration_disabled' => 'El registro por email está deshabilitado. Por favor, usa inicio de sesión social.',
        'registrations_disabled' => 'Los registros están actualmente deshabilitados.',
    ],

    // Verify Email
    'verify' => [
        'page_title' => 'Verificar tu Cuenta',
        'headline' => 'Verifica tu correo electrónico',
        'subheadline' => 'Antes de continuar, debes verificar tu email.',
        'description' => 'Antes de continuar, por favor revisa tu correo electrónico para el enlace de verificación. Si no recibiste el email,',
        'new_request_link' => 'haz clic aquí para solicitar otro',
        'new_link_sent' => 'Se ha enviado un nuevo enlace a tu dirección de correo.',
        'or' => 'O',
        'logout' => 'haz clic aquí para cerrar sesión',
    ],

    // Password Confirm
    'password_confirm' => [
        'page_title' => 'Confirmar tu Contraseña',
        'headline' => 'Confirmar Contraseña',
        'subheadline' => 'Confirma tu contraseña para continuar',
        'password' => 'Contraseña',
        'button' => 'Confirmar contraseña',
    ],

    // Password Reset Request
    'password_reset_request' => [
        'page_title' => 'Solicitar Restablecimiento de Contraseña',
        'headline' => 'Restablecer contraseña',
        'subheadline' => 'Ingresa tu email para restablecer tu contraseña',
        'email' => 'Correo Electrónico',
        'button' => 'Enviar enlace de restablecimiento',
        'or' => 'o',
        'return_to_login' => 'volver al inicio de sesión',
    ],

    // Password Reset
    'password_reset' => [
        'page_title' => 'Restablecer tu Contraseña',
        'headline' => 'Restablecer Contraseña',
        'subheadline' => 'Restablece tu contraseña a continuación',
        'email' => 'Correo Electrónico',
        'password' => 'Contraseña',
        'password_confirm' => 'Confirmar Contraseña',
        'button' => 'Restablecer Contraseña',
    ],

    // Two Factor Challenge
    'two_factor_challenge' => [
        'page_title' => 'Verificación en Dos Pasos',
        'headline_auth' => 'Código de Autenticación',
        'subheadline_auth' => 'Ingresa el código de autenticación proporcionado por tu aplicación.',
        'headline_recovery' => 'Código de Recuperación',
        'subheadline_recovery' => 'Confirma el acceso a tu cuenta ingresando uno de tus códigos de recuperación.',
    ],

    // Signup (Custom)
    'signup' => [
        'page_title' => 'Registro',
        'headline' => 'Crear Cuenta',
        'subheadline' => 'Únete a nuestra plataforma',
        'name' => 'Nombre Completo',
        'email' => 'Dirección de Email',
        'phone' => 'Teléfono Móvil (WhatsApp)',
        'phone_placeholder' => '+34600123456',
        'phone_help' => 'Formato internacional para WhatsApp Ej: +1600123456',
        'password' => 'Contraseña',
        'password_confirmation' => 'Confirmar Contraseña',
        'button' => 'Crear Cuenta',
        'have_account' => '¿Ya tienes una cuenta?',
        'login_link' => 'Iniciar Sesión',
    ],

    // Email de verificación
    'verify_email' => [
        'subject' => 'Verifica tu Correo Electrónico',
        'greeting' => 'Bienvenido al sitio :name',
        'body' => 'Tu correo registrado es :email. Por favor haz clic en el siguiente enlace para verificar tu cuenta.',
        'action' => 'Verificar Email',
    ],
];
