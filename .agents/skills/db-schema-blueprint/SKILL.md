---
name: db-schema-blueprint
description: Provides a starting-point SQL schema reference for Zero CMS's core and module database tables. Use when you need a quick sense of a table's shape, but ALWAYS verify against the actual migration files under src/*/Database/Migrations/ before relying on exact columns — this reference is known to lag behind newer migrations.
---

# System Database Schema Blueprint (KNOWN INCOMPLETE — verify against migrations)

> **This snapshot is stale.** It was carried over from the original `GEMINI.md` monolith and only reflects roughly the first 3-6 migrations. A cross-check against `src/Database/Migrations/` and each module's own `Database/Migrations/` found columns and entire tables below that are **not represented here**, including: `media` (title, focus point columns, private-storage fields), `pages`/`blog_posts`/`shop_products` (`exclude_from_search`), `pages` (`show_in_nav`, `summary`, `omit_title`), `sites` (`timezone`, `default_language`, `homepage_id`, `expires_at`), a `security_audits` table, `queue_jobs`/`queue_scheduled_tasks` tables, a `search_index` table, a `shop_product_category_links` table, `form_submissions` (never had a documented schema here), and various performance-index migrations.
>
> **Treat the tables below as a rough starting sketch only.** For any real schema question, read the actual migration file — every table's true source of truth is `src/Database/Migrations/*.php` (core) or `src/Modules/<Module>/Database/Migrations/*.php` (module-owned tables), applied in filename order by `MigrationManager`.

```sql
-- 0. Migrations Tracking
CREATE TABLE migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    batch INT NOT NULL,
    run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1. Sites (Tenant definitions) -- NOTE: later migrations add timezone, default_language, homepage_id, expires_at
CREATE TABLE sites (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) NOT NULL UNIQUE,
    theme VARCHAR(100) NOT NULL,
    enabled_modules TEXT NULL,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Users (Accounts and permissions)
CREATE TABLE users (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NULL,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'editor', -- editor, super_admin
    api_token VARCHAR(255) NULL,
    preferences TEXT NULL, -- Serialized JSON configuration map
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    INDEX (api_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Media Assets -- NOTE: later migrations add title, focus point, and private-storage columns
CREATE TABLE media (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NULL,
    filename VARCHAR(255) NOT NULL,
    path VARCHAR(255) NOT NULL,
    mime VARCHAR(255) NOT NULL, -- Core uses 'mime' column
    folder VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Multi-Tenant Pages -- NOTE: later migrations add show_in_nav, summary, omit_title, exclude_from_search
CREATE TABLE pages (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    content TEXT, -- Contains the serialized JSON array of block-builder components
    type VARCHAR(50) NULL,
    controller VARCHAR(255) NULL, -- Custom Controller routing override
    view VARCHAR(255) NULL, -- Custom View template override
    status VARCHAR(20) DEFAULT 'draft', -- draft, published
    precedence INT DEFAULT 0, -- Order / priority of display
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    UNIQUE KEY site_slug_unique (site_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Password Resets (Core auth recovery tokens)
CREATE TABLE password_resets (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NULL,
    user_id VARCHAR(36) NOT NULL,
    token VARCHAR(128) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX (site_id),
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Audit Logs (Core security tracking)
CREATE TABLE audit_logs (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NULL,
    user_id VARCHAR(36) NULL,
    action VARCHAR(100) NOT NULL,
    object_type VARCHAR(100) NULL,
    object_id VARCHAR(100) NULL,
    meta JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX (site_id),
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Blog Module Posts -- NOTE: later migrations add allow_comments, comment_notifiers, summary, featured_image
CREATE TABLE blog_posts (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    content TEXT, -- Block-builder JSON data
    type VARCHAR(50) NULL,
    status VARCHAR(20) DEFAULT 'draft',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    UNIQUE KEY site_slug_unique (site_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Shop Module Categories
CREATE TABLE shop_categories (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL, -- Field is 'title', not 'name'
    slug VARCHAR(255) NOT NULL,
    description TEXT NULL,
    image VARCHAR(255) NULL,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    UNIQUE KEY site_category_slug_unique (site_id, slug),
    INDEX (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Shop Module Products -- NOTE: a later Search-module migration adds exclude_from_search
CREATE TABLE shop_products (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NOT NULL,
    category_id VARCHAR(36) NULL,
    title VARCHAR(255) NOT NULL, -- Field is 'title', not 'name'
    slug VARCHAR(255) NOT NULL,
    sku VARCHAR(255) NULL,
    description TEXT NULL,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    compare_at_price DECIMAL(10, 2) NULL,
    main_image VARCHAR(255) NULL,
    media_ids TEXT NULL,
    status VARCHAR(20) DEFAULT 'published',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    UNIQUE KEY site_product_slug_unique (site_id, slug),
    INDEX (site_id),
    INDEX (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Shop Module Product Variants
CREATE TABLE shop_product_variants (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NOT NULL,
    product_id VARCHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL, -- Field is 'title', not 'name'
    sku VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00, -- Field is 'price', not 'price_override'
    stock INT NOT NULL DEFAULT 0,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    INDEX (site_id),
    INDEX (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Shop Module Orders
CREATE TABLE shop_orders (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00, -- Field is 'total_price', not 'total_amount'
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    INDEX (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Shop Module Order Items
CREATE TABLE shop_order_items (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NOT NULL,
    order_id VARCHAR(36) NOT NULL,
    product_id VARCHAR(36) NOT NULL,
    variant_id VARCHAR(36) NULL,
    title VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00, -- Field is 'price', not 'unit_price'
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    INDEX (site_id),
    INDEX (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Forum Module Boards
CREATE TABLE forum_boards (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT NULL,
    precedence INT NOT NULL DEFAULT 0,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    UNIQUE KEY site_board_slug_unique (site_id, slug),
    INDEX (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Forum Module Threads
CREATE TABLE forum_threads (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NOT NULL,
    board_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'published', -- published, locked, pinned
    views_count INT NOT NULL DEFAULT 0,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    UNIQUE KEY site_thread_slug_unique (site_id, slug),
    INDEX (site_id),
    INDEX (board_id),
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Forum Module Posts
CREATE TABLE forum_posts (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NOT NULL,
    thread_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    content TEXT NOT NULL,
    parent_id VARCHAR(36) NULL, -- For nesting/threading replies
    status VARCHAR(50) NOT NULL DEFAULT 'approved', -- approved, pending, flagged
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    INDEX (site_id),
    INDEX (thread_id),
    INDEX (user_id),
    INDEX (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

See also the `blog-comments-pipeline` skill for `blog_comments`' schema, which is documented there instead of duplicated here.
