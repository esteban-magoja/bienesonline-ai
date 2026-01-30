<?php

return [
    /*
    |--------------------------------------------------------------------------
    | General Messages - Spanish
    |--------------------------------------------------------------------------
    |
    | Mensajes generales, flash messages, notificaciones
    |
    */

    // Bienvenida y saludos
    'welcome' => 'Bienvenido',
    'welcome_back' => 'Bienvenido de nuevo, :name',
    'hello' => 'Hola',
    'goodbye' => 'Adiós',
    
    // Mensajes de éxito
    'success' => 'Éxito',
    'saved_successfully' => 'Guardado exitosamente',
    'created_successfully' => 'Creado exitosamente',
    'updated_successfully' => 'Actualizado exitosamente',
    'deleted_successfully' => 'Eliminado exitosamente',
    'sent_successfully' => 'Enviado exitosamente',
    'operation_successful' => 'Operación completada con éxito',
    
    // Mensajes de error
    'error' => 'Error',
    'error_occurred' => 'Ha ocurrido un error',
    'try_again' => 'Por favor, intenta nuevamente',
    'something_went_wrong' => 'Algo salió mal',
    'not_found' => 'No encontrado',
    'unauthorized' => 'No autorizado',
    'forbidden' => 'Acceso denegado',
    'validation_error' => 'Error de validación',
    
    // Mensajes de advertencia
    'warning' => 'Advertencia',
    'are_you_sure' => '¿Estás seguro?',
    'cannot_be_undone' => 'Esta acción no se puede deshacer',
    'confirm_action' => 'Por favor confirma esta acción',
    
    // Mensajes de información
    'info' => 'Información',
    'loading' => 'Cargando...',
    'please_wait' => 'Por favor espera',
    'processing' => 'Procesando...',
    'no_data' => 'No hay datos disponibles',
    'no_results' => 'No se encontraron resultados',
    
    // Autenticación
    'login' => 'Iniciar Sesión',
    'logout' => 'Cerrar Sesión',
    'register' => 'Registrarse',
    'forgot_password' => '¿Olvidaste tu contraseña?',
    'reset_password' => 'Restablecer Contraseña',
    'remember_me' => 'Recordarme',
    'login_successful' => 'Inicio de sesión exitoso',
    'logout_successful' => 'Sesión cerrada exitosamente',
    'registered_successfully' => 'Registro exitoso',
    
    // Validación común
    'validation' => [
        'required_field' => 'Este campo es requerido',
        'country_required' => 'Debes seleccionar un país.',
        'search_term_required' => 'Debes escribir un término de búsqueda.',
        'search_term_min' => 'El término de búsqueda debe tener al menos :min caracteres.',
        'invalid_email' => 'Email inválido',
        'invalid_phone' => 'Teléfono inválido',
        'min_length' => 'Mínimo :min caracteres',
        'max_length' => 'Máximo :max caracteres',
        'must_match' => 'Los campos deben coincidir',
    ],
    
    // Paginación
    'previous' => 'Anterior',
    'next' => 'Siguiente',
    'showing' => 'Mostrando',
    'of' => 'de',
    'results' => 'resultados',
    'page' => 'Página',
    
    // Búsqueda y filtros
    'search' => 'Buscar',
    'search_placeholder' => 'Buscar...',
    'filter' => 'Filtrar',
    'filters' => 'Filtros',
    'clear_filters' => 'Limpiar Filtros',
    'apply_filters' => 'Aplicar Filtros',
    'sort_by' => 'Ordenar por',
    'sort_asc' => 'Ascendente',
    'sort_desc' => 'Descendente',
    
    // Formularios
    'submit' => 'Enviar',
    'save' => 'Guardar',
    'cancel' => 'Cancelar',
    'delete' => 'Eliminar',
    'edit' => 'Editar',
    'create' => 'Crear',
    'update' => 'Actualizar',
    'confirm' => 'Confirmar',
    'back' => 'Volver',
    'close' => 'Cerrar',
    'yes' => 'Sí',
    'no' => 'No',
    
    // Tiempos y fechas
    'today' => 'Hoy',
    'yesterday' => 'Ayer',
    'tomorrow' => 'Mañana',
    'now' => 'Ahora',
    'never' => 'Nunca',
    'date' => 'Fecha',
    'time' => 'Hora',
    'created_at' => 'Creado el',
    'updated_at' => 'Actualizado el',
    'deleted_at' => 'Eliminado el',
    
    // Estados
    'active' => 'Activo',
    'inactive' => 'Inactivo',
    'enabled' => 'Habilitado',
    'disabled' => 'Deshabilitado',
    'published' => 'Publicado',
    'draft' => 'Borrador',
    'pending' => 'Pendiente',
    'approved' => 'Aprobado',
    'rejected' => 'Rechazado',
    
    // Contacto
    'contact' => 'Contacto',
    'contact_us' => 'Contáctanos',
    'name' => 'Nombre',
    'email' => 'Email',
    'phone' => 'Teléfono',
    'message' => 'Mensaje',
    'send_message' => 'Enviar Mensaje',
    'message_sent' => 'Mensaje enviado exitosamente',
    
    // Compartir
    'share' => 'Compartir',
    'share_on_facebook' => 'Compartir en Facebook',
    'share_on_twitter' => 'Compartir en Twitter',
    'copy_link' => 'Copiar Enlace',
    'link_copied' => 'Enlace copiado al portapapeles',
    
    // Archivos
    'upload' => 'Subir',
    'download' => 'Descargar',
    'file' => 'Archivo',
    'files' => 'Archivos',
    'image' => 'Imagen',
    'images' => 'Imágenes',
    'select_file' => 'Seleccionar Archivo',
    'drag_drop' => 'Arrastra y suelta aquí',
    
    // Errores HTTP
    '404_title' => 'Página No Encontrada',
    '404_message' => 'La página que buscas no existe',
    '500_title' => 'Error del Servidor',
    '500_message' => 'Algo salió mal en nuestro servidor',
    'go_home' => 'Ir al Inicio',
    
    // Cookies y privacidad
    'cookies_message' => 'Usamos cookies para mejorar tu experiencia',
    'accept_cookies' => 'Aceptar',
    'privacy_policy' => 'Política de Privacidad',
    'terms_of_service' => 'Términos de Servicio',
    
    // Mensajes específicos de propiedades
    'property' => [
        'message_sent' => '¡Tu mensaje ha sido enviado! El anunciante se pondrá en contacto contigo pronto.',
        'cannot_contact_own' => 'No puedes enviar un mensaje a tu propia propiedad.',
    ],
    
    // Mensajes de autenticación
    'auth' => [
        'login_required' => 'Debes iniciar sesión para enviar un mensaje.',
    ],
    
    // Request messages
    'request_created_successfully' => 'Solicitud creada exitosamente',
    'request_updated_successfully' => 'Solicitud actualizada exitosamente',
    'request_deleted_successfully' => 'Solicitud eliminada exitosamente',
    'request_activated' => 'Solicitud activada exitosamente',
    'request_deactivated' => 'Solicitud desactivada exitosamente',
    
    // Message messages
    'message_marked_read' => 'Mensaje marcado como leído',
    'message_marked_unread' => 'Mensaje marcado como no leído',
    'message_deleted' => 'Mensaje eliminado correctamente',
    
    // Additional UI
    'clear' => 'Limpiar',
    'searching' => 'Buscando...',
    'search_error' => 'No se pudo realizar la búsqueda',
    'no_image' => 'Sin Imagen',
    'home' => 'Inicio',
    'copy_link' => 'Copiar',
];
