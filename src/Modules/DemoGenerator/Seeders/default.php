<?php
// Generated from corporate.json

return [
    'sites' => [
        [
            'name' => 'Zero CMS Main Site (d6laptop.zero)',
            'domain' => 'd6laptop.zero',
            'theme' => 'default',
            'enabled_modules' => [
                'blog',
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
    'media' => [
        [
            'filename' => 'screenshot-main.svg',
            'mime' => 'image/svg+xml',
            'site_domain' => 'd6laptop.zero',
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
                    'title' => 'Welcome to Zero CMS Corporate Portal',
                    'content' => '<p>Zero CMS Corporate is an enterprise-ready, multi-tenant framework running on a unified, high-performance database core. It provides absolute site isolation, custom views, and rapid page-builder speeds with 100% local, zero-dependency engineering.</p>',
                ],
                [
                    'type' => 'text_image',
                    'title' => 'Unified Domain and Tenant Architecture',
                    'content' => '<p>With Zero CMS, developers can map independent subdomains (such as localhost, zero.guide, or d6laptop.zero.guide) directly to dedicated tenant profiles, serving distinct page layouts, media files, and blog publications transparently from the same database.</p>',
                    'media_placeholder' => 'screenshot-main.svg',
                ],
            ],
            'type' => 'page',
        ],
        [
            'title' => 'About Us',
            'slug' => 'about',
            'status' => 'published',
            'site_domain' => 'd6laptop.zero',
            'content' => [
                [
                    'type' => 'text',
                    'title' => 'Our Core Philosophy',
                    'content' => '<p>Our engineering team believes in clean, lightweight, dependency-free development. By eliminating bloated third-party node packages, vendor wrappers, and heavy servers, Zero CMS achieves load times under 10 milliseconds while maintaining bulletproof data protection.</p>',
                ],
            ],
            'type' => 'page',
        ],
        [
            'title' => 'Contact Us',
            'slug' => 'contact',
            'status' => 'published',
            'site_domain' => 'd6laptop.zero',
            'content' => [
                [
                    'type' => 'text',
                    'title' => 'Get in Touch',
                    'content' => '<p>Have any questions, inquiries, or feedback? Please use the form below to reach out to our team. We respond to all submissions within 24 hours.</p>',
                ],
                [
                    'type' => 'form_builder',
                    'title' => 'Send Us a Message',
                    'content' => '',
                    'recipient_email' => 'admin@d6laptop.zero',
                    'id' => 'cf_corp_contact',
                    'items' => [
                        [
                            'name' => 'full_name',
                            'label' => 'Your Full Name',
                            'type' => 'text',
                            'required' => '1',
                            'options' => '',
                            'validation' => 'none',
                        ],
                        [
                            'name' => 'email_address',
                            'label' => 'Your Email Address',
                            'type' => 'email',
                            'required' => '1',
                            'options' => '',
                            'validation' => 'email',
                        ],
                        [
                            'name' => 'subject',
                            'label' => 'Inquiry Subject',
                            'type' => 'select',
                            'required' => '0',
                            'options' => 'General Question, Partnership Proposal, Tech Support, Careers',
                            'validation' => 'none',
                        ],
                        [
                            'name' => 'message',
                            'label' => 'Detailed Message',
                            'type' => 'textarea',
                            'required' => '1',
                            'options' => '',
                            'validation' => 'none',
                        ],
                    ],
                ],
            ],
            'type' => 'page',
        ],
    ],
    'blog_posts' => [
        [
            'title' => 'Welcome to our New Multisite CMS!',
            'slug' => 'welcome',
            'status' => 'published',
            'site_domain' => 'd6laptop.zero',
            'content' => [
                [
                    'type' => 'text',
                    'title' => 'Multi-domain Tenant Separation is Live',
                    'content' => '<p>Today we are thrilled to announce the official release of our 100% zero-dependency Multisite framework! Developers can now seed, configure, and isolate discrete websites cleanly by filtering resource lookups based on domain host headers.</p>',
                ],
            ],
            'type' => 'post',
            'summary' => 'Explore professional and extensible corporate-themed publications detailing system-wide guidelines, performance bounds, and zero-dependency OOP conventions. Specifically, this article explores the core paradigms, implementation metrics, and design philosophies regarding "Welcome to our New Multisite CMS!".',
        ],
        [
            'title' => 'Why Zero Dependencies Matter in 2026',
            'slug' => 'zero-dependency-philosophy',
            'status' => 'published',
            'site_domain' => 'd6laptop.zero',
            'content' => [
                [
                    'type' => 'text',
                    'title' => 'The Engineering Advantage',
                    'content' => '<p>As software setups grow increasingly complex, keeping your application zero-dependency avoids vulnerability cascades, simplifies security audits, and ensures robust backwards compatibility across PHP generations.</p>',
                ],
            ],
            'type' => 'post',
            'summary' => 'Explore professional and extensible corporate-themed publications detailing system-wide guidelines, performance bounds, and zero-dependency OOP conventions. Specifically, this article explores the core paradigms, implementation metrics, and design philosophies regarding "Why Zero Dependencies Matter in 2026".',
        ],
    ],
];
