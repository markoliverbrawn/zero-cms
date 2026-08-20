<?php

declare(strict_types=1);

/**
 * Zero CMS Default Base Tenant Blueprint
 *
 * This file returns the declarative structure used by bin/seed to initialise the base
 * default site: a single tenant, its super administrator, and a minimal published homepage.
 * Any module-specific content is seeded separately by that module's own class seeder under
 * src/Modules/<Module>/Seeders/.
 */

return [
    'sites' => [
        [
            'name' => 'Zero CMS Main Site (d6laptop.zero)',
            'domain' => 'd6laptop.zero',
            'theme' => 'default',
            'enabled_modules' => [
                'formbuilder',
                'security',
                'queue',
                'site-search',
            ],
        ],
    ],
    'users' => [
        [
            'id' => '019ec865-eb5c-7307-99f1-2f9f1f6db720',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password_hash' => '$2y$10$2tdsRK0UD/QvrVPFoz1WZOtodh33dRR1jfRzQbkDDpUuBfHZJPzhC',
            'role' => 'super_admin',
            'api_token' => 'super-admin-api-key-2026',
            'preferences' => '{"timezone":"Pacific\\/Auckland","language":"en"}',
        ],
    ],
    'pages' => [
        [
            'title' => 'Home',
            'slug' => '',
            'status' => 'published',
            'site_domain' => 'd6laptop.zero',
            'content' => [
                [
                    'type' => 'text',
                    'title' => 'Welcome to Zero CMS',
                    'content' => '<p>Your Zero CMS site is up and running. Log in to the <a href="/admin">admin area</a> to start building pages, blocks, and themes.</p>',
                ],
            ],
            'type' => 'page',
        ],
    ],
];
