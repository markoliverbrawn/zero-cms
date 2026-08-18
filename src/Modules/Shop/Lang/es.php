<?php

declare(strict_types=1);

/**
 * File: src/Modules/Shop/Lang/es.php
 * Architectural Purpose: Module-owned Spanish translation dictionary, merged into the active language by Zero\Support\I18n::init().
 * Package: Zero\Modules\Shop
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

return [
    // Field labels
    'product_name' => 'Nombre del Producto',
    'base_sku' => 'SKU Base',
    'primary_image_path' => 'Imagen Principal',
    'rich_description' => 'Descripción Enriquecida (HTML)',
    'compare_at_price' => 'Precio Comparativo',
    'category' => 'Categoría',

    // Input helper texts
    'price_help' => 'El costo minorista base cobrado a los clientes.',
    'compare_at_price_help' => 'El precio de lista original antes de las rebajas o descuentos actuales.',
    'main_image_help' => 'La miniatura de visualización principal o la ruta de la imagen hero.',
    'media_ids_help' => 'Colección separada por comas de archivos adjuntos de imágenes de soporte.',
    'sku_help' => 'El código de Unidad de Mantenimiento de Existencias para el seguimiento de inventario.',
    'category_id_help' => 'La categoría del departamento principal al que pertenece este producto.',
    'stock_help' => 'Cantidad de inventario disponible actualmente en el almacén.',
    'product_id_help' => 'El producto del catálogo principal al que se relaciona esta variante.',
    'customer_name_help' => 'El nombre completo del cliente que realiza este pedido.',
    'customer_email_help' => 'Correo electrónico de contacto del cliente para enviar facturas o actualizaciones de recibos.',
    'total_price_help' => 'El precio total calculado que incluye envío y descuentos.',
    'shipping_address_help' => 'La dirección física de destino a donde se envía el pedido.',
];
