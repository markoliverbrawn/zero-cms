---
name: db-schema-blueprint
description: Provides an accurate SQL schema reference for Zero CMS's core and module database tables, reconstructed directly from every migration file. Use when you need a table's current shape — columns, indexes, and known field-naming quirks.
---

# System Database Schema Blueprint

This was reconstructed directly from every migration file under `src/Database/Migrations/` and each module's own `Database/Migrations/`, applied in filename order (`0001` → `0029` as of this writing). **If a new migration is added after this, this file goes stale — update it, or at minimum re-derive it from the migrations before trusting it blindly.** The migrations themselves remain the actual source of truth; this is a convenience snapshot, not a replacement for reading them.

```sql
-- 0. Migrations Tracking (created directly by MigrationManager, not a numbered migration file)
CREATE TABLE migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    batch INT NOT NULL,
    run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1. Sites (Tenant definitions)
CREATE TABLE sites (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) NOT NULL UNIQUE,
    theme VARCHAR(100) NOT NULL,
    enabled_modules TEXT NULL,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    timezone VARCHAR(100) NOT NULL DEFAULT 'UTC',        -- added 0021
    default_language VARCHAR(50) NOT NULL DEFAULT 'en',  -- added 0021
    homepage_id VARCHAR(36) NULL,                        -- added 0025 -- FK-by-convention to pages.id
    expires_at DATETIME NULL                             -- added 0029
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

-- 3. Media Assets
CREATE TABLE media (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NULL,
    title VARCHAR(255) NULL,                    -- added 0016
    focus_x INT NOT NULL DEFAULT 50,             -- added 0017 -- crop/focal-point %, 0-100
    focus_y INT NOT NULL DEFAULT 50,             -- added 0017
    filename VARCHAR(255) NOT NULL,
    path VARCHAR(255) NOT NULL,
    mime VARCHAR(255) NOT NULL, -- Core uses 'mime' column
    folder VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    visibility VARCHAR(20) DEFAULT 'public',     -- added 0024 -- 'public' | private
    submission_id VARCHAR(36) NULL,              -- added 0024 -- links a private upload back to its form_submissions row
    original_name VARCHAR(255) NULL,             -- added 0024
    file_size INT NOT NULL DEFAULT 0,            -- added 0024
    INDEX (visibility),
    INDEX (submission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Multi-Tenant Pages (Content blocks container with display precedence)
CREATE TABLE pages (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NULL,
    title VARCHAR(255) NOT NULL,
    omit_title TINYINT(1) NOT NULL DEFAULT 0,          -- added 0019 -- hide the H1 title on render
    slug VARCHAR(255) NOT NULL,
    content TEXT, -- Contains the serialized JSON array of block-builder components
    summary TEXT NULL,                                 -- added 0012
    type VARCHAR(50) NULL,
    controller VARCHAR(255) NULL, -- Custom Controller routing override
    view VARCHAR(255) NULL, -- Custom View template override
    status VARCHAR(20) DEFAULT 'draft', -- draft, published
    precedence INT DEFAULT 0,                          -- added 0004 -- Order / priority of display
    show_in_nav TINYINT(1) NOT NULL DEFAULT 1,         -- added 0010
    exclude_from_search TINYINT(1) NOT NULL DEFAULT 0, -- added 0026
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    UNIQUE KEY site_slug_unique (site_id, slug),
    INDEX idx_pages_site_deleted_precedence (site_id, deleted_at, precedence ASC) -- added 0013
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
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    INDEX (site_id),
    INDEX (user_id)
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

-- 9. Shop Module Products
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
    exclude_from_search TINYINT(1) NOT NULL DEFAULT 0, -- added 0026
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    UNIQUE KEY site_product_slug_unique (site_id, slug),
    INDEX (site_id),
    INDEX (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9b. Shop Product <-> Category many-to-many links (added 0028 -- a product's primary category is still
-- shop_products.category_id; this junction table is for *additional* category associations)
CREATE TABLE shop_product_category_links (
    product_id VARCHAR(36) NOT NULL,
    category_id VARCHAR(36) NOT NULL,
    site_id VARCHAR(36) NOT NULL,
    PRIMARY KEY (product_id, category_id),
    INDEX site_id_index (site_id)
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

-- 13. Form Builder Submissions (see also the form-builder-engine skill)
CREATE TABLE form_submissions (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NULL,
    message TEXT NOT NULL, -- Archived JSON string of every submitted field -- see form-builder-engine skill
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    INDEX (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. Security Audits (added 0020)
CREATE TABLE security_audits (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NULL,
    score INT NOT NULL,
    environment VARCHAR(50) NOT NULL,
    telemetry TEXT NOT NULL,
    report LONGTEXT NOT NULL,
    created_at DATETIME,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    INDEX (site_id),
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. Queue Jobs (added 0022)
CREATE TABLE queue_jobs (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NOT NULL,
    job_class VARCHAR(255) NOT NULL,
    payload JSON NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    attempts INT DEFAULT 0,
    reserved_at DATETIME NULL,
    failed_at DATETIME NULL,
    error_message TEXT NULL,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    INDEX (site_id),
    INDEX (status, reserved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. Queue Scheduled Tasks (added 0023)
CREATE TABLE queue_scheduled_tasks (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NOT NULL,
    task_key VARCHAR(255) NOT NULL,
    payload JSON NOT NULL,
    expression VARCHAR(100) NOT NULL, -- cron-style schedule expression
    last_run_at DATETIME NULL,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    UNIQUE KEY site_task_unique (site_id, task_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. Search Index (added 0027 -- a flattened, cross-model index rebuilt from indexInSearch(); see the
-- test-suite-architecture skill's Searchable-trait note and the page-builder-engine skill's BlockHelper note)
CREATE TABLE search_index (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NOT NULL,
    model_type VARCHAR(100) NOT NULL,
    model_id VARCHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NULL,
    url VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY model_unique (model_type, model_id),
    INDEX site_id_index (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Notes on tables intentionally not detailed here

* **`form_submissions`** — full schema is above, but see the `form-builder-engine` skill for how `message` is structured as JSON.
* Every module's migrations live at `src/Modules/<Module>/Database/Migrations/*.php`; core's own live at `src/Database/Migrations/*.php`. `MigrationManager` discovers and runs both sets by filename glob, in a single flat numeric order — always check the highest existing number across *all* of them before adding a new migration (see the `module-creation` skill).
