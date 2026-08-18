<?php

declare(strict_types=1);

/**
 * File: src/Modules/Shop/Lang/en.php
 * Architectural Purpose: Module-owned English translation dictionary, merged into the active language by Zero\Support\I18n::init().
 * Package: Zero\Modules\Shop
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

return [
    // Field labels
    'product_name' => 'Product Name',
    'base_sku' => 'Base SKU',
    'primary_image_path' => 'Primary Image',
    'rich_description' => 'Rich Description (HTML)',
    'compare_at_price' => 'Compare At Price',
    'category' => 'Category',

    // Input helper texts
    'price_help' => 'The base retail cost charged to customers.',
    'compare_at_price_help' => 'The original list price before current markdowns or discounts.',
    'main_image_help' => 'The primary display thumbnail or hero image path.',
    'media_ids_help' => 'Comma-separated collection of supporting image attachments.',
    'sku_help' => 'The Stock Keeping Unit code for inventory tracking and shipping.',
    'category_id_help' => 'The parent department category this product belongs to.',
    'stock_help' => 'Quantity of available inventory currently on hand in warehouse.',
    'product_id_help' => 'The parent catalog product this variant relates to.',
    'customer_name_help' => 'The full name of the customer placing this order.',
    'customer_email_help' => 'Customer contact email for sending invoice or receipt updates.',
    'total_price_help' => 'The complete calculated price including shipping and discounts.',
    'shipping_address_help' => 'The physical location destination where order shipment is dispatched.',
];
