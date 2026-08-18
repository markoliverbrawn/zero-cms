<?php

declare(strict_types=1);

/**
 * File: src/Lang/es.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

return [
    // Sidebar
    'home' => 'Inicio',
    'view_site' => 'Ver Sitio',
    'admin_dashboard' => 'Panel de Control',
    'content_management' => 'Gestión de Contenido',
    'security' => 'Seguridad',
    'manage_pages' => 'Gestionar Páginas',
    'media_library' => 'Biblioteca de Medios',
    'manage_users' => 'Gestionar Usuarios',
    'preferences' => 'Preferencias',

    // Header
    'logged_in_as' => 'Conectado como',
    'logout' => 'Cerrar Sesión',

    // Dashboard
    'configure_dashboard' => 'Configurar Panel',
    'quick_links' => 'Enlaces Rápidos',
    'recent_pages' => 'Páginas Recientes',
    'recent_media' => 'Medios Recientes',
    'no_pages_found' => 'No se encontraron páginas.',
    'no_media_found' => 'No se encontraron archivos de medios.',
    'view_media_library' => 'Ver Biblioteca de Medios',
    'dashboard_empty_title' => 'Tu Panel de Control está Vacío',
    'dashboard_empty_desc' => 'Has desactivado todos los widgets. Haz clic en el botón de abajo para elegir qué tarjetas deseas mostrar.',
    'configure_widgets' => 'Configurar Widgets',

    // Preferences View
    'user_preferences' => 'Preferencias de Usuario',
    'interface_appearance' => 'Interfaz y Apariencia',
    'language_localization' => 'Idioma y Localización',
    'workspace_dashboard' => 'Área de Trabajo y Panel',
    'color_preset' => 'Ajuste de Color',
    'theme_preset_desc' => 'Selecciona un esquema de color premium para aplicar globalmente.',
    'theme_mode' => 'Modo de Tema',
    'theme_mode_desc' => 'Ajusta el esquema de brillo (compatible con todos los ajustes).',
    'light_mode' => 'Modo Claro',
    'dark_mode' => 'Modo Oscuro',
    'default_pagination_limit' => 'Límite de Paginación Predeterminado',
    'pagination_limit_desc' => 'Establece el tamaño de lista predeterminado para tablas.',
    'user_timezone' => 'Zona Horaria del Usuario',
    'timezone_desc' => 'Selecciona la zona horaria para fechas y registros.',
    'dashboard_configurations' => 'Configuraciones del Panel',
    'dashboard_configurations_desc' => 'Elige qué paneles de widgets de resumen deseas mostrar en tu panel de control:',
    'back_to_dashboard' => 'Volver al Panel',
    'save_preferences' => 'Guardar Preferencias',
    'language' => 'Idioma',
    'language_desc' => 'Selecciona tu idioma preferido para la interfaz de administración.',
    'save_success_msg' => '¡Preferencias guardadas con éxito!',

    // Models & Crud Labels
    'title' => 'Título',
    'slug' => 'Slug',
    'content' => 'Contenido',
    'publish_status' => 'Estado de Publicación',
    'status' => 'Estado de Publicación',
    'created_at' => 'Creado El',
    'updated_at' => 'Actualizado El',
    'username' => 'Nombre de Usuario',
    'email' => 'Correo Electrónico',
    'id' => 'ID',
    'draft' => 'borrador',
    'published' => 'publicado',

    // Input Helper Texts
    'title_help' => 'El titular o título que se muestra de cara al usuario para esta entrada.',
    'slug_help' => 'La porción única de URL amigable para la web generada a partir del título.',
    'content_help' => 'El diseño del cuerpo principal compuesto por bloques visuales estructurados.',
    'controller_help' => 'Superposición opcional del controlador PHP responsable del mapeo de solicitudes dinámicas.',
    'view_help' => 'Nombre opcional del archivo de plantilla de vista de tema (sin extensión .php) para anular el diseño.',
    'precedence_help' => 'Valor de orden de peso de visualización. Los números más altos aparecen primero en las listas.',
    'status_help' => 'Estado de visibilidad que determina si el registro es accesible en el sitio web.',
    'username_help' => 'El identificador único utilizado para iniciar sesión en el espacio de trabajo seguro de administración.',
    'email_help' => 'Dirección de contacto principal del usuario utilizada para correos del sistema y restablecimiento de contraseña.',
    'role_help' => 'Nivel de privilegio de la cuenta que regula el acceso de seguridad y la gestión del sitio.',
    'name_help' => 'El nombre oficial de esta partición de sitio multi-inquilino.',
    'domain_help' => 'La cadena del dominio de host HTTP mapeada para enrutar a este inquilino del sitio.',
    'theme_help' => 'La plantilla de apariencia de diseño utilizada para dar estilo a la interfaz pública.',
    'enabled_modules_help' => 'Marque qué áreas funcionales premium especializadas están activas.',
    'description_help' => 'Un resumen descriptivo de este elemento.',
    'no_records_found' => 'No se encontraron registros.',
];
