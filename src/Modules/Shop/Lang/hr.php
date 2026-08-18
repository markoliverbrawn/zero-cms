<?php

declare(strict_types=1);

/**
 * File: src/Modules/Shop/Lang/hr.php
 * Architectural Purpose: Module-owned Croatian translation dictionary, merged into the active language by Zero\Support\I18n::init().
 * Package: Zero\Modules\Shop
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

return [
    // Field labels
    'product_name' => 'Naziv Proizvoda',
    'base_sku' => 'Osnovni SKU',
    'primary_image_path' => 'Glavna Slika',
    'rich_description' => 'Obogaćeni Opis (HTML)',
    'compare_at_price' => 'Usporedna Cijena',
    'category' => 'Kategorija',

    // Input helper texts
    'price_help' => 'Osnovna maloprodajna cijena koja se naplaćuje kupcima.',
    'compare_at_price_help' => 'Izvorna maloprodajna cijena prije trenutnih sniženja ili popusta.',
    'main_image_help' => 'Glavna minijatura prikaza ili putanja istaknute slike.',
    'media_ids_help' => 'Popis popratnih slika odvojen zarezima.',
    'sku_help' => 'Šifra jedinice za vođenje zaliha za praćenje i otpremu.',
    'category_id_help' => 'Glavna kategorija odjela kojoj ovaj proizvod pripada.',
    'stock_help' => 'Količina raspoložive zalihe koja je trenutno na skladištu.',
    'product_id_help' => 'Matični proizvod iz kataloga na koji se odnosi ova varijanta.',
    'customer_name_help' => 'Puno ime kupca koji šalje narudžbu.',
    'customer_email_help' => 'Kontakt e-pošta kupca za slanje računa ili potvrda.',
    'total_price_help' => 'Ukupna izračunata cijena uključujući dostavu i popuste.',
    'shipping_address_help' => 'Fizička lokacija na koju se šalje pošiljka narudžbe.',
];
