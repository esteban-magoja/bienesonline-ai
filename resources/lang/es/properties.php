<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Property Translations - Spanish
    |--------------------------------------------------------------------------
    |
    | Traducciones relacionadas con propiedades inmobiliarias
    |
    */

    // Tipos de propiedades
    'types' => [
        'house' => 'Casa',
        'apartment' => 'Departamento',
        'office' => 'Oficina',
        'commercial' => 'Local Comercial',
        'land' => 'Terreno',
        'field' => 'Campo',
        'farm' => 'Finca',
        'warehouse' => 'Galpón',
    ],

    // Tipos de transacción
    'transaction_types' => [
        'sale' => 'Venta',
        'rent' => 'Alquiler',
        'temporary_rent' => 'Alquiler Temporal',
    ],

    // Características de propiedades
    'features' => [
        'bedrooms' => 'Habitaciones',
        'bedrooms_short' => 'hab.',
        'bathrooms' => 'Baños',
        'bathrooms_short' => 'baños',
        'garage' => 'Cochera',
        'garages' => 'Cocheras',
        'parking_spaces' => 'Cocheras',
        'parking_short' => 'cochera',
        'covered_area' => 'Área Cubierta',
        'covered_area_short' => 'Área (m²)',
        'land_area' => 'Área de Terreno',
        'pool' => 'Piscina',
        'garden' => 'Jardín',
        'balcony' => 'Balcón',
        'terrace' => 'Terraza',
        'furnished' => 'Amueblado',
        'pets_allowed' => 'Se Aceptan Mascotas',
    ],

    // Monedas
    'currencies' => [
        'USD' => 'Dólares (USD)',
        'ARS' => 'Pesos Argentinos (ARS)',
        'EUR' => 'Euros (EUR)',
    ],

    // Labels y textos de UI
    'in' => 'en',
    'for' => 'para',
    'search_properties' => 'Buscar Propiedades',
    'property_detail' => 'Detalle de Propiedad',
    'view_details' => 'Ver Detalles',
    'contact_owner' => 'Contactar al Anunciante',
    'share_property' => 'Compartir Propiedad',
    'related_properties' => 'Propiedades Relacionadas',
    'price' => 'Precio',
    'location' => 'Ubicación',
    'description' => 'Descripción',
    'characteristics' => 'Características',
    'amenities' => 'Comodidades',
    'map' => 'Mapa',
    'images' => 'Imágenes',
    'contact_form' => 'Formulario de Contacto',
    
    // Búsqueda
    'search' => 'Buscar',
    'search_placeholder' => 'Ej: Casa moderna con jardín en Córdoba',
    'filter_by_country' => 'Filtrar por País',
    'select_country' => 'Selecciona un país',
    'search_results' => 'Resultados de Búsqueda',
    'no_results' => 'No se encontraron propiedades',
    'showing_results' => 'Mostrando :count resultados',
    'similarity_score' => 'Coincidencia',
    
    // Formulario de contacto
    'your_name' => 'Tu Nombre',
    'your_email' => 'Tu Email',
    'your_phone' => 'Tu Teléfono',
    'your_message' => 'Tu Mensaje',
    'send_message' => 'Enviar Mensaje',
    'call_now' => 'Llamar Ahora',
    'whatsapp_contact' => 'Contactar por WhatsApp',
    
    // Estados
    'available' => 'Disponible',
    'sold' => 'Vendida',
    'rented' => 'Alquilada',
    'reserved' => 'Reservada',
    
    // Ubicación
    'country' => 'País',
    'province' => 'Provincia',
    'city' => 'Ciudad',
    'neighborhood' => 'Barrio',
    'address' => 'Dirección',
    
    // Unidades
    'sqm' => 'm²',
    'per_month' => 'por mes',
    
    // Mensajes
    'message_sent' => 'Mensaje enviado exitosamente',
    'message_error' => 'Error al enviar el mensaje',
    'loading' => 'Cargando...',
    
    // Búsqueda avanzada
    'search_all_listings' => 'Buscar Todos los Anuncios',
    'no_listings_found' => 'No se encontraron anuncios',
    'adjust_search_term' => 'Intenta ajustar tu término de búsqueda',
    'enter_search_term' => 'Ingresa un término arriba para buscar propiedades',
    'similarity' => 'Similitud',
    'by' => 'Por',
    'view_details' => 'Ver Detalles',
    
    // Search page
    'search_hero_title' => 'Encuentra la Propiedad de tus Sueños',
    'search_hero_subtitle' => 'Búsqueda inteligente impulsada por IA para encontrar exactamente lo que necesitas',
    
    // Search form
    'search_form' => [
        'country_label' => 'País',
        'country_required' => '(obligatorio)',
        'select_country' => 'Selecciona un país',
        'what_looking_for' => '¿Qué estás buscando?',
        'min_chars' => '(mínimo 5 caracteres)',
        'char_counter' => 'caracteres mínimos',
        'search_button' => 'Buscar',
        'searching' => 'Buscando...',
        'clear' => 'Limpiar',
        'clear_filters' => 'Limpiar filtros',
        'check_fields' => 'Revisa los siguientes campos:',
        'searching_properties' => 'Buscando propiedades',
        'processing_search' => 'Procesando tu búsqueda...',
        'placeholder' => 'Ej: Casa moderna con piscina en zona tranquila, departamento céntrico...',
    ],
    
    // Search results
    'search_results' => [
        'title' => 'Resultados de búsqueda',
        'property_found' => 'propiedad encontrada',
        'properties_found' => 'propiedades encontradas',
        'in_country' => 'en :country',
        'for_term' => 'para ":term"',
        'no_properties_found' => 'No se encontraron propiedades',
        'try_different_search' => 'Intenta con otros términos de búsqueda o cambia el país seleccionado.',
        'relevance' => 'Relevancia',
        'featured' => 'Destacado',
    ],
    
    // CTA Section
    'cta' => [
        'ready_title' => '¿Listo para encontrar tu próximo hogar?',
        'ready_subtitle' => 'Usa nuestra búsqueda inteligente para encontrar exactamente lo que necesitas',
        'smart_search_title' => 'Búsqueda Inteligente',
        'smart_search_desc' => 'Describe lo que buscas en lenguaje natural y nuestra IA encontrará las mejores coincidencias',
        'multiple_locations_title' => 'Múltiples Ubicaciones',
        'multiple_locations_desc' => 'Explora propiedades en diferentes países y encuentra el lugar perfecto para ti',
        'relevant_results_title' => 'Resultados Relevantes',
        'relevant_results_desc' => 'Ve un puntaje de relevancia para cada propiedad basado en tu búsqueda',
        'search_hint' => 'Comienza tu búsqueda escribiendo algo como: "Casa moderna con jardín cerca del centro" o "Departamento luminoso con vista al mar"',
    ],
    
    // JS messages
    'js' => [
        'generating_embeddings' => 'Generando embeddings con IA...',
        'analyzing_similarities' => 'Analizando similitudes semánticas...',
        'sorting_results' => 'Ordenando resultados por relevancia...',
        'filtering_properties' => 'Filtrando propiedades...',
        'smart_search_progress' => 'Búsqueda inteligente en progreso...',
        'searching_properties' => 'Buscando propiedades...',
        'select_country_error' => 'Debes seleccionar un país.',
        'enter_search_error' => 'Debes escribir un término de búsqueda.',
        'min_chars_error' => 'El término de búsqueda debe tener al menos 5 caracteres.',
    ],
    
    // Property detail
    'property_detail' => 'Detalle de Propiedad',
    'property_type' => 'Tipo de Propiedad',
    'transaction_type' => 'Tipo de Operación',
    'condition' => 'Estado',
    'covered_area_sqm' => 'm² Cubiertos',
    'land_area_sqm' => 'm² Terreno',
    'contact_advertiser' => 'Contactar al Anunciante',
    'whatsapp_message' => 'Hola, estoy interesado en la propiedad: :property',
    'message_placeholder' => 'Estoy interesado en esta propiedad...',
    'default_message' => 'Hola, estoy interesado en la propiedad ":property". Me gustaría recibir más información.',
    'send_inquiry' => 'Enviar Consulta',
    'your_property' => 'Esta es tu propiedad',
    'related_properties' => 'Propiedades Relacionadas',
];
