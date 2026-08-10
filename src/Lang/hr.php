<?php

declare(strict_types=1);

/**
 * File: src/Lang/hr.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

return [
    // Sidebar
    'home' => 'Početna',
    'view_site' => 'Pogledaj Stranicu',
    'admin_dashboard' => 'Nadzorna ploča',
    'content_management' => 'Upravljanje Sadržajem',
    'security' => 'Sigurnost',
    'manage_posts' => 'Upravljanje Objavama',
    'manage_pages' => 'Upravljanje Stranicama',
    'media_library' => 'Biblioteka Medija',
    'manage_users' => 'Upravljanje Korisnicima',
    'preferences' => 'Postavke',

    // Header
    'logged_in_as' => 'Prijavljeni ste kao',
    'logout' => 'Odjava',

    // Dashboard
    'configure_dashboard' => 'Prilagodi Nadzornu Ploču',
    'quick_links' => 'Brze Poveznice',
    'recent_posts' => 'Nedavne Objave',
    'recent_pages' => 'Nedavne Stranice',
    'recent_media' => 'Nedavni Mediji',
    'no_posts_found' => 'Nema pronađenih objava.',
    'no_pages_found' => 'Nema pronađenih stranica.',
    'no_media_found' => 'Nema pronađenih medijskih datoteka.',
    'view_media_library' => 'Vidi Biblioteku Medija',
    'dashboard_empty_title' => 'Vaša nadzorna ploča je prazna',
    'dashboard_empty_desc' => 'Onemogućili ste sve widgete. Kliknite na gumb ispod kako biste odabrali koje kartice želite prikazati na ekranu.',
    'configure_widgets' => 'Prilagodi Widgete',

    // Preferences View
    'user_preferences' => 'Korisničke Postavke',
    'interface_appearance' => 'Sučelje i Izgled',
    'language_localization' => 'Jezik i Lokalizacija',
    'workspace_dashboard' => 'Radni Prostor i Nadzorna Ploča',
    'color_preset' => 'Tema Boja',
    'theme_preset_desc' => 'Odaberite premium temu boja za primjenu na cijelom sustavu.',
    'theme_mode' => 'Način Teme',
    'theme_mode_desc' => 'Prilagodite svjetlinu (podržano u svim temama boja).',
    'light_mode' => 'Svijetli Način',
    'dark_mode' => 'Tamni Način',
    'default_pagination_limit' => 'Zadano Ograničenje Stranice',
    'pagination_limit_desc' => 'Postavite zadanu veličinu popisa za tablice i popise.',
    'user_timezone' => 'Vremenska Zona',
    'timezone_desc' => 'Odaberite vremensku zonu za prikaze datuma i zapisa.',
    'dashboard_configurations' => 'Konfiguracije Nadzorne Ploče',
    'dashboard_configurations_desc' => 'Odaberite koje widgete želite prikazati na svojoj nadzornoj ploči:',
    'back_to_dashboard' => 'Natrag na Nadzornu Ploču',
    'save_preferences' => 'Spremi Postavke',
    'language' => 'Jezik',
    'language_desc' => 'Odaberite željeni jezik za administratorsko sučelje.',
    'save_success_msg' => 'Postavke su uspješno spremljene!',

    // Models & Crud Labels
    'title' => 'Naslov',
    'slug' => 'Kratki naziv',
    'content' => 'Sadržaj',
    'publish_status' => 'Status Objave',
    'status' => 'Status Objave',
    'created_at' => 'Kreirano',
    'updated_at' => 'Ažurirano',
    'username' => 'Korisničko ime',
    'email' => 'E-pošta',
    'id' => 'ID',
    'draft' => 'skica',
    'published' => 'objavljeno',

    // Input Helper Texts
    'title_help' => 'Naslov ili glavna tema ovog unosa vidljiva korisnicima.',
    'slug_help' => 'Jedinstveni, web-sigurni dio URL-a generiran iz naslova.',
    'content_help' => 'Glavni sadržaj sastavljen od strukturiranih vizualnih blokova.',
    'controller_help' => 'Dodatno PHP premošćivanje kontrolera zaduženo za dinamičko mapiranje zahtjeva.',
    'view_help' => 'Dodatni naziv predloška teme (bez .php ekstenzije) za premošćivanje izgleda.',
    'precedence_help' => 'Vrijednost poretka prikaza. Veći brojevi prikazuju se prvi na popisima.',
    'status_help' => 'Status vidljivosti koji određuje je li zapis dostupan na web stranici.',
    'username_help' => 'Jedinstveno korisničko ime za prijavu u sigurnu administratorsku zonu.',
    'email_help' => 'Primarna kontakt adresa e-pošte za sistemske poruke i oporavak zaporke.',
    'role_help' => 'Razina administratorskih privilegija koja regulira sigurnosni pristup i upravljanje stranicom.',
    'name_help' => 'Službeni naziv ove multi-tenant particije stranice.',
    'domain_help' => 'HTTP host domena mapirana za usmjeravanje na ovu particiju stranice.',
    'theme_help' => 'Predložak dizajna koji se koristi za stiliziranje javnog sučelja.',
    'enabled_modules_help' => 'Označite koje su premium funkcionalne zone aktivne.',
    'description_help' => 'Opisni sažetak ove stavke.',
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
    'no_records_found' => 'Nisu pronađeni zapisi.',
];
