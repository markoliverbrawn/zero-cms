# Zero CMS — Expert Architecture, Conventions, & Reconstruction Blueprint

This document acts as both a foundational mandate of engineering standards and an exhaustive, step-by-step master reconstruction blueprint for **Zero CMS**—a zero-dependency, high-contrast, multi-tenant CMS and e-commerce platform.

---

## Part 1: Core Coding & Development Conventions

To maintain the high-quality, professional, and scalable state of the Zero CMS workspace, any human or AI agent modifying this repository **MUST** adhere strictly to the following mandates:

### 1. Style Separation (No Inline Styles)
* **Rule:** There must be **ZERO inline styles** (`style="..."`) inside any HTML or PHP view templates, **UNLESS** they are explicitly setting CSS custom properties/variables dynamically (e.g. `style="--cols-desktop: 4;"`).
* **Convention:** All layout, spacing, colors, and responsive grids must be handled by external stylesheets.
* **Organization:** Place admin style definitions in the modular CSS files inside `assets/css/admin/` (e.g., `block-builder.css`, `components.css`, `preferences.css`), imported centrally via `assets/css/admin.css`.
* **Utility Classes:** Use utility classes (`.flex-1`, `.width-auto`, `.block-flex-row`, etc.) rather than repeating inline CSS declarations.

### 2. Loose Coupling & Modular Registration (`register*` pattern)
* **Rule:** Core CMS views, controllers, layouts, and system kernel classes must never hardcode module-specific variables, database schemas, templates, or HTML paths. **Modules must remain completely separate from the system Core.**
* **Core Integrity:** Core directories (such as `src/Core/` and `src/Http/`) must contain strictly generic, module-agnostic logic. Under no circumstances should core files contain hardcoded references, fallback mappings, or conditional checks for specific modules (e.g. no hardcoding of `'shop'`, `'blog'`, or similar names inside `App.php` or `Router.php`).
* **Convention:** Maintain a clean, decoupled separation of concerns by utilizing central registry hooks and generic dynamic fallbacks on bootstrap:
  * Modular blocks must be registered dynamically on bootstrap. The core registers standard blocks, while independent modules (like `Shop`) are responsible for registering their custom blocks inside their own `init()` methods.
  * Register route pathways dynamically using `Router::register()`.
  * Register template directory fallbacks dynamically using `App::registerViewDir()`.
  * Register theme fallback inheritance dynamically using `App::registerThemeFallback()`.
* **Module-Owned Block Editors:** Independent modules must house their own back-office admin block editors (e.g. `src/Modules/Shop/Views/blocks/categories.php` or `src/Modules/Blog/Views/blocks/latest_articles.php` rather than centralizing inside Admin views) and pass the path inside their registration configs as `'admin_view'`:
  ```php
  // Inside src/Modules/Shop/Module.php :: init()
  \Zero\Core\App::registerBlock('categories', [
      'label' => 'Product Categories Grid',
      'icon' => 'zap',
      'admin_view' => dirname(__FILE__) . '/Views/blocks/categories.php'
  ]);

  // Inside src/Modules/Blog/Module.php :: init()
  \Zero\Core\App::registerBlock('latest_articles', [
      'label' => 'Latest Blog Articles',
      'icon' => 'edit-3',
      'admin_view' => dirname(__FILE__) . '/Views/blocks/latest_articles.php'
  ]);
  ```
* **Scalability & Hot-Plugging:** The system must compile and scale automatically. Adding a new module or theme fallback must only require registering it dynamically with the core on bootstrap and placing its assets on disk—ensuring modules can be safely enabled, disabled, or permanently deleted from the workspace without causing any core kernel compilation or routing failures.
* **Module Accent Colors:** Every module class implementing `Zero\Interfaces\Module` MUST define a `getAccentColor(): string` method returning its brand-representative hex color code (e.g. `#ef4444` for security, `#9333ea` for demogenerator). This color is then used for rendering the module's administrative pills and widgets consistently under the active admin theme.

### 3. Strict Multi-Tenant Isolation
* **Rule:** Under no circumstances should any tenant be capable of querying, leaking, or modifying data belonging to another tenant.
* **Convention:** 
  * Every module table (including pages, posts, order items, products, categories, media assets) must contain a `site_id` UUID column.
  * Do not write manual database filters. Core queries must leverage the static `IsModel` Active Record trait to automatically append active tenant scoping behind the scenes:
    ```php
    $siteId = \Zero\Core\App::getCurrentSiteId();
    ```

### 4. Sanitizer-Safe Interactive Blocks
* **Rule:** The block builder's preview iframe utilizes a strict HTML sanitizer which strips out standard `<button>` and `<script>` elements.
* **Convention:** Interactive components inside block previews (such as Accordion Q&A triggers or Carousel sliders) must use styled `div` or generic wrapper elements and employ **parent-to-iframe event delegation** (binding listeners dynamically in parent JavaScript onto the loaded iframe object) to prevent browser sandbox blockage.

### 5. Resilient File Modification Strategy
* **Rule:** Avoid using fuzzy regex or standard search-replace engines that might fail due to line ending disparities (LF vs CRLF).
* **Convention:** Prefer using `write_file` for complete file rewrites of small modules, or extremely precise, multi-line context anchors when using `replace` on larger core scripts. Always run syntax checks and seed database tables immediately after file edits.

### 6. Dynamic Localized Field Helpers (No Hardcoded Explanations)
* **Rule:** Form inputs in the admin area must never hardcode static description or explanation texts.
* **Convention:** All input helper texts must be resolved dynamically inside the field renderer. The engine first checks the localized dictionaries for `{field}_help` or `{field}_desc` strings, falling back to optional model definitions (`helper_text`, `description`). This maintains high localization capability across English, Spanish, Croatian, and Māori.

### 7. Zero-Dependency External Handshakes (No Vendor SDKs)
* **Rule:** Integrations with external services (such as Google OAuth 2.0 or SMTP servers) must remain 100% dependency-free.
* **Convention:** Never introduce package managers or external third-party SDK libraries. Execute secure HTTP API handshakes using raw, native PHP `cURL` sessions and core network sockets directly, keeping the codebase extremely fast, transparent, and secure.

### 8. Mandatory Selector Nesting (CSS Nesting Syntax)
* **Rule:** All stylesheets located in `assets/css/` must strictly utilize modern native CSS nesting for all descendant, pseudo-class, and pseudo-element rules.
* **Convention:** Avoid flat, repetitive selector paths (e.g. do not use separate declarations for `header h1` and `header h1 a`). Nest child rules within their respective parents, using `&` to reference the parent context (such as `&:hover`, `&.active`, or `&::after`), preserving modularity and improving file readability.

### 9. Core Declarative Validator (`Zero\Core\Validator`)
* **Rule:** All modular input validations (such as contact forms, user profiles, or e-commerce reviews) must utilize the core `Zero\Core\Validator` engine.
* **Convention:** Always specify validation constraints in a clean, declarative array or pipe format (e.g. `'email' => 'required|email|max:255'`). Developers can extend the validation rules dynamically at runtime using `Validator::registerRule()`. Filter input values down strictly to validated and declared fields using `$validator->getValidatedData()` before database operations to mitigate unauthorized field injection vectors.

### 10. Database-Driven Block ID Resolution
* **Rule:** Configurable block attributes containing sensitive target variables (such as recipient email addresses) must NEVER be exposed in the frontend markup, even if obfuscated (e.g. in Base64).
* **Convention:** Centrally generate and assign a unique block ID (`block_id`) to every block type saved in a page layout (handled automatically by `block_builder.php` and `block_builder.js` via the `.block-id-input` hidden fields). On form submission, transmit only this stateless `block_id`. The backend controller must securely query the active tenant's `pages` and `blog_posts` columns in the database, parsing the layout JSON to extract the secure configuration fully on the server side.

### 11. Hardened Honeypot Spam Protection
* **Rule:** Public forms must employ zero-friction honeypot spam traps. The word `"honeypot"` must NEVER appear in any class names, selectors, or input properties to prevent advanced bot crawlers from detecting the trap.
* **Convention:** Embed a hidden input named `"website_url"` (which automated crawler scripts are highly primed to fill) wrapped inside a generic, deceptively normal class wrapper like `.website-field-wrapper` (styled with `display: none; visibility: hidden;` inside the block's CSS stylesheet). Upon submission, if the bait field is populated, silently drop the submission—returning a simulated success response (`{"success": true}`) to the spam bot without executing any database writes or dispatching any SMTP notifications.

### 12. No Emojis
* **Rule:** Under no circumstances should emojis be used inside backend administrative dashboards, user interfaces, or public view templates.
* **Convention:** All iconography and status markers must be rendered using vector inline SVGs, core SVG helper functions, or high-contrast CSS styled typography to maintain a highly professional, modern, and purist design.

### 13. Explicit Namespace Imports ("use" Statements)
* **Rule:** All PHP class files, helper utilities, controllers, view templates, layouts, and block renderers MUST explicitly import any dependent namespaced classes at the top of the file using standard `use` statements. 
* **Convention:** Never reference fully namespaced class names inline inside the executable logic paths (e.g. do not write `$x = new \Zero\Core\Validator(...)` or call `\Zero\Support\Security::uuidv7()`). Instead, declare `use Zero\Core\Validator;` and `use Zero\Support\Security;` at the top of the file, and refer to them as `Validator` and `Security` directly. This applies universally to all backend and frontend PHP view templates.

### 14. Alphabetical Method Sorting
* **Rule:** All class methods inside any PHP class (including core bootstrappers, active record models, custom middleware, and helper utilities) MUST be arranged in strict alphabetical order.
* **Convention:** Arrange the methods alphabetically (by method name) from A to Z, ensuring high readability, ease of file traversal, and structural consistency across the entire codebase.

### 15. Mandatory Feature & Module Documentation (Guide Site Seeding)
* **Rule:** Whenever a new feature, module, or system capability is added or substantially updated, it MUST be fully documented in the Guide site's seeder map, and the database MUST be cleanly re-seeded. The documentation must comprehensively cover:
  1. **High-Level Architectural Summary**: An accessible overview of the feature's design, purposes, and systemic role.
  2. **Configuration Information**: Complete description of environmental variable properties (e.g. `.env`) and setup settings.
  3. **Extension Developer How-Tos**: Code snippets, interfaces/contracts, and practical, clean examples demonstrating how to build, register, or extend the feature.
* **Convention:**
  - Register dedicated technical page records under the `pages` array inside the central documentation seeder file (`seeders/data/documentation.json`), specifying appropriate descriptive text blocks detailing its architecture, database schemas, multi-tenant boundaries, and administrative workflows.
  - **Sitemap Synchronization:** Additionally, whenever new seeder data is created or the page tree hierarchy is modified, the Mermaid sitemap document (`seeders/data/sitemaps/documentation.md`) MUST be updated to ensure the guide site's interactive sitemap remains completely accurate.
  - **Navigation Scoping:** To prevent main menu clutter on the public site, any sub-pages seeded beneath the parent nodes `modules/` or `how-tos/` (such as `modules/forum` or `how-tos/custom-blocks`) MUST explicitly specify `"show_in_nav": "0"` to keep them hidden from primary navigation menus (accessible strictly as sub-pages from their respective indices).
  - **Execute Seeders:** After editing the seeder JSON configurations, always execute `php seeders/seeder.php` via the docker container command wrapper to persist, compile, and verify the physical presence of the new documentation pages in the SQL database.

### 16. Absolute Full-Width Views (No Container Max-Widths)
* **Rule:** All administrative, backend back-office, and public cascading layout view templates must strictly be designed as completely full-width.
* **Convention:** Never introduce restrictive `max-width` limit barriers (e.g. `max-width: 1200px`) on outer wrapping container elements. Set `width: 100%; max-width: none; margin: 0;` on top-level wrapper classes (such as `.edit-media-wrapper` or `.listrecords`), allowing visual forms, grids, and dashboards to stretch elegantly to fill the entire width of the available layout canvas.

### 17. Script Separation (No Inline Script Tags)
* **Rule:** There must be **ZERO raw script tags** (`<script>...</script>` containing executable JS) inside any frontend HTML or PHP block view templates.
* **Convention:** All interactive dynamic behaviors, transitions, carousels, or event delegation click-handlers must be housed in dedicated, standalone `.js` asset files inside `/assets/js/blocks/` (e.g. `accordion.js`, `testimonials.js`, `gallery.js`) and loaded centrally at the bottom of the page inside the respective layout files (`src/Views/themes/*/layout.php`). This facilitates caching, preserves strict content-security, and keeps the PHP views pristine and script-free.

### 18. Eager Loading & N+1 Query Prevention
* **Rule:** **EVERY database listing query, summary board, catalog page, and list view array across both public frontend views and back-office administrative lists (backend) MUST actively eager load all relational elements to maintain maximum scalability and speed. Under no circumstances is any code permitted to execute N+1 database lookup loops.**
* **Mandates:**
  - **No Lookup Loops in Templates/Views:** Never execute relational query finders, database lookup calls, or model getters (such as individual `User::find()` or `Comment::count()`) inside list view templates, loops, or iteration handlers.
  - **No On-Load Metadata Calculations:** Never pre-calculate complex, row-specific relational metadata (such as counting cascading delete relationships) inside listing renders. All such calculations MUST be lazy-loaded on-demand via AJAX (e.g. `/api/v1/admin/models/{modelName}/{id}/cascade-check`) when actions are triggered, preventing rendering overhead.
  - **Eager Loading in Model listings:** For frontend listings, use optimized `LEFT JOIN`s to fetch all statistics, counts, and string/path associations in a single combined SQL roundtrip, mapping the fields onto model instance properties. For backend listings, override the static `paginate()` and list `all()` methods using custom queries or trait aliasing to pre-hydrate relational caches.
  - **No Dynamic Database Hits in Sequential Layout Elements:** Reject any architectural designs to turn sequential modular templates (such as page layout blocks) into heavy, independent database-querying PHP classes. If blocks render themselves sequentially via individual class methods, each block row will trigger distinct database lookups to resolve assets (e.g., executing 5 separate queries for 5 images in a page layout), introducing a catastrophic N+1 query loop. Instead, developers must enforce:
    1. **Centralized Pre-Batching/Eager-Loading Upfront:** The parent template/controller must parse the raw JSON layout in memory, gather all nested resource IDs (such as media or product IDs), run **exactly ONE batched database query** to fetch those assets upfront, and build an in-memory lookup map to pass down to renderers.
    2. **Database-Free OOP Block Helpers:** Any helper classes (implementing <code>BlockHelperInterface</code>) used during indexing or rendering must operate purely in-memory using passed JSON attributes. They are strictly forbidden from executing any database queries or fetching relationships.

### 19. Standardized Back-Office Table Listings (No Architectural Theme Bleed)
* **Rule:** All back-office backend lists and administrative tables (such as past audits, user lists, files, pages) MUST be enclosed within a standard `.listrecords` wrapper class and leverage clean, unstyled `<table>` elements. Custom frontend-specific layouts and themes (such as `.threads-table` or `.forum-container`) are strictly forbidden inside backend panels.
* **Convention:** Developers must include responsive `data-label` attributes on table cells to support viewport scaling automatically. This ensures design consistency and seamless visual theme adaptability (e.g., vintage-greenscreen or corporate) under core CSS styles, preventing frontend theme leakages.

### 20. Active Record Relational Cascade Deletions (`Zero\Models\Traits\CascadesDeletes`)
* **Rule:** Active Record models with dependent relational children (e.g., Blog Posts with Comments, Forum Boards with Threads, Forum Threads with Posts, Shop Products with Product Variants, and Shop Orders with Order Items) MUST use the core `Zero\Models\Traits\CascadesDeletes` trait to automatically clean up child records.
* **Convention:** 
  - To declare cascading relationships, the parent model class must define a protected static array `$cascadeDeletes` mapping child model FQNs to their foreign key column names (e.g., `Comment::class => 'post_id'`).
  - To prevent accidental deletion of shared or external resources, cascade deletions must NEVER delete media assets, categories, or other shared records that could be referenced elsewhere (e.g., featured images or product categories are kept safe and preserved).
  - Conflict resolution on traits composition must alias `IsModel::delete` and `IsModel::forceDelete` as `traitDelete` and `traitForceDelete` respectively, letting the `CascadesDeletes` trait intercept the lifecycle, cascade deletions, and then safely proceed.

### 21. Avoid Native JavaScript Alerts and Prompts
* **Rule:** Native browser-level JavaScript popups, alerts (`alert()`), and confirmation prompt messages (`confirm()`) are STRICTLY forbidden inside administrative, backend back-office, or core layouts.
* **Convention:** 
  - Developers must exclusively utilize the integrated Promise-based asynchronous modal dialog engine `window.adminConfirm(options)` mapped to `#admin-confirm-modal` centrally in `layout.php`.
  - When prompting for deletions, any cascading deletion relationships computed on the model must be supplied in the `'details'` option to inform users, alongside a `'note'` warning that restoring parent records later will not automatically restore those cascade-deleted related child records.

  ### 22. Standardized UTC Date-Time Management (Strict Separation)
  * **Rule:** Under no circumstances should dates and times be stored in local timezones inside database records. All model timestamps (`created_at`, `updated_at`, `deleted_at`, etc.) MUST be stored in strict, canonical UTC format.
  * **Convention:**
  - **Database Writes:** Generating current timestamps inside controllers or models must utilize PHP's strict `gmdate('Y-m-d H:i:s')` clock, completely bypassing any MySQL container timezone configurations to ensure absolute database UTC storage.
  - **Localized Display (Views):** Timezone localization is strictly a presentational concern. Localized displays must be performed exclusively in the view templates (or display layers) on load using the centralized helper `I18n::localizeDateTime($utcDateTimeString)`. If the logged-in user does not have a customized timezone preference configured, the system gracefully falls back to the active `Site` model's default `timezone` setting (e.g. `Pacific/Auckland`).

  ### 23. Highly Representative Iconography (No Generic Standard Defaults)
  * **Rule:** Whenever creating new icons for a module, function, or sidebar navigation link in the system, always design or select an asset that is highly representative of the specific thing being created. Never default to standard, generic, or mismatched placeholder icons (such as the lightning bolt `'zap'` or plain `'settings'`).
  * **Convention:**
  - Save all custom or newly selected icons as clean, high-contrast, standard-conforming SVG files inside `/assets/svgs/` (e.g. `assets/svgs/shop.svg` or `assets/svgs/package.svg`).
  - Follow the existing SVG styling parameters: use `viewBox="0 0 24 24"`, set `stroke-width="1.5"` (or match neighboring assets), and utilize `fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"`.
  - Avoid inline CSS styles inside vector SVGs to maintain full structural and visual adaptiveness under different system themes.

  ### 24. Dashboard Widget Auto-Activation for Super Admins
  * **Rule:** Whenever a new administrative dashboard widget is created or registered under any module, the system MUST guarantee that it is automatically added and activated on the Super Admin's dashboard layout by default. Super Admins must always possess complete, immediate, and unrestricted visibility over all system metrics, task runners, logs, and security telemetry.
  * **Convention:**
  - Standardize dynamic widget resolution in `src/Modules/Admin/Views/dashboard.php` so that when a Super Admin is logged in, any missing possible widgets (constructed via enabled modules filters) are automatically appended and merged into their `$enabledWidgets` array in real-time.

  ### 25. Error Suppression Prohibition & Explicit Exceptions Handling
  * **Rule:** The error suppression operator `@` (e.g. `@file_put_contents` or `@file_get_contents`) is STRICTLY forbidden inside any codebase controllers, models, bootstrapping, or support utilities. All potential operation failures (including disk reads, disk writes, database queries, and network connections) must be checked explicitly, and descriptive `Exception` instances must be thrown and handled.
  * **Convention:**
  - Verify function return values explicitly (e.g., check if `file_put_contents()` returns `false`) and throw informative `Exception`s describing the exact failure and target file path.
  - Wrap unstable network, socket, or system resource operations in proper `try-catch` blocks, ensuring that caught exceptions are either handled gracefully or logged explicitly in the system audit trail before bubbling up.

  ### 26. Complete Prohibition of CSS !important Declarations
  * **Rule:** Under no circumstances are `!important` declarations permitted in any stylesheet, custom theme style, administrative style, or block-specific CSS file inside the workspace. All style conflicts, specificity battles, and custom overrides must be resolved cleanly using standard, precise CSS selector nesting and specificity structures.
  * **Convention:**
  - To override default styles (like margins on `.block-row`), increase selector specificity by combining classes (e.g., `.block-row.space-before-small`) instead of utilizing broad overrides or warning suppressions.

  ### 27. Mandatory Template Rendering (No Inline HTML in Classes)
  * **Rule:** Multi-line blocks of rendered HTML, email bodies, UI cards, or interactive layouts must always be defined in dedicated PHP template files under `/src/Views/` (or relative views folders) and rendered via the core templating engine (`Template::renderFile`). Under no circumstances is it permitted to hardcode multi-line HTML structures, inline document fragments, or email envelopes directly within controllers, models, or service classes.
  * **Convention:** Always invoke `Template::renderFile($path, $data)` to decouple presentational rendering from business logic, maintaining a high clean separation and keeping classes pristine.

  ### 28. Standardized HTML Escaping (Mandatory Str::escape)
  * **Rule:** The raw PHP `htmlspecialchars` function MUST NOT be used directly in any controllers, models, helper utilities, or view templates across the application. Developers MUST exclusively employ the unified `Zero\Support\Str::escape()` helper class for HTML character escaping to ensure consistent, secure, and type-safe XSS protection.
  * **Convention:**
    - To prevent class-loading and scoping errors, any file calling the escaping helper must declare `use Zero\Support\Str;` at the top of the file as mandated in Rule 13, referencing `Str::escape($value)` directly inside the logic or view templates (e.g. `<?= Str::escape($var) ?>`).
    - The low-level escaping and highlighting implementation files (e.g., `src/Support/Str.php`) are the sole exceptions permitted to execute raw `htmlspecialchars`.

  ---

  ## Part 2: Folder Directory Map

```
/data/misc/zero/
├── GEMINI.md                    # Core developer guidelines, conventions and system blueprint
├── index.php                     # Central front-controller gateway
├── etc/                          # Secure configuration and setup files (Deny from all)
│   └── install.php               # Secure database schema creator & admin setups (CLI-only)
├── assets/                       # Publicly accessible static assets
│   ├── css/                      # Modular style files (admin.css, shop.css, auth.css, etc.)
│   │   ├── admin/                # Decoupled admin views CSS imports (components, core, builder, etc.)
│   │   └── blocks/               # Public layout block styles (accordion, gallery, masonry, etc.)
│   ├── js/                       # Core Javascript features and block interaction models
│   └── svgs/                     # Feather-themed high-contrast vector icons
├── seeders/                      # Database rebuild system
│   ├── seeder.php                # Master seeder executor (re-severs, migrates, and seeds)
│   └── data/                     # Multitenant seeder records (documentation, shop, portfolio, corporate)
├── src/                          # OOP core framework engine and modular controllers
│   ├── Core/                     # Kernel & Bootstrapping (App, Env, Template)
│   ├── Database/                 # Connection, Migrations and schemas (DB, Migration, MigrationManager)
│   │   └── Migrations/           # Database migration files (CreateCoreTables)
│   ├── Http/                     # HTTP infrastructure and routers
│   │   ├── Controllers/          # Controller base classes (ApiController)
│   │   ├── Middleware/           # Request filters (AuthMiddleware, CsrfMiddleware)
│   │   └── Router.php            # Request routing manager
│   ├── Interfaces/               # Strict contracts (Controller, Model, Module)
│   ├── Lang/                     # Core multi-lingual i18n localization vectors
│   │   ├── en.php                # English translations and helper texts
│   │   ├── es.php                # Spanish translations and helper texts
│   │   ├── hr.php                # Croatian translations and helper texts
│   │   └── mi.php                # Māori translations and helper texts
│   ├── Models/                   # Core active record entities (Media, Page, Site, User)
│   ├── Support/                  # Helper utilities and diagnostics (Logger, Security, Emailer, I18n, Seeder)
│   ├── Views/                    # Dynamic cascading layouts and theme view templates
│   └── Modules/                  # Decoupled extensible modules
│       ├── Admin/                # Unified Back-Office dashboard controller & views
│       │   ├── Controllers/      # Admin route processors (Dashboard, List, GoogleAuth, etc.)
│       │   └── Views/            # Backend admin dashboard templates, tabbed forms, and block pre-renderers
│       ├── Blog/                 # Classic Article and publishing module
│       ├── FormBuilder/          # Decoupled drag-and-drop form creation & archival submissions engine
│       ├── Forum/                # Interactive multi-tenant community boards & nested threaded replies
│       ├── Security/             # Unified, decoupled platform security hardening & AI threat auditing
│       └── Shop/                 # Full-scale e-commerce, shopping cart, and transaction engine
├── tests/                        # Zero-dependency Automated Testing Suite
│   ├── bootstrap.php             # Unified test bootstrapping and PSR-4 autoloader
│   ├── run.php                   # Master test runner (subprocesses discovery and execution)
│   └── *Test.php                 # Core and integration test suites
└── storage/                      # Public media uploads and log storage
```

---

## Part 3: Deep-Dive System Architecture & Reconstruction Blueprint

This master reconstruction guide provides the operational blueprints necessary to completely recreate Zero CMS from scratch.

### I. Bootstrapping & Multi-Tenant Routing
Zero CMS solves multi-tenant routing, session management, and authentication check boundaries inside a single, unified bootstrapping workflow:

1. **Front Controller Gateway (`index.php`):**
   * Acts as the single entry point. Captures execution times and delegates all routing to the central middleware pipeline.
2. **Consolidated UNION Bootstrap Query (`src/Core/App.php`):**
   * On boot, the framework identifies the active tenant by stripping port numbers from `$_SERVER['HTTP_HOST']`.
   * It runs a **single-query database roundtrip** using a consolidated SQL `UNION ALL` statement to instantly fetch both the active `Site` configuration and the logged-in `User` profile (if a session `user_id` exists), avoiding multiple database requests:
     ```sql
     SELECT
         'site' AS record_type, id, name, domain, theme, enabled_modules,
         NULL AS email, NULL AS password_hash, NULL AS role, NULL AS site_id, NULL AS preferences,
         created_at, updated_at
     FROM sites WHERE domain = ?
     UNION ALL
     SELECT
         'user' AS record_type, id, username AS name, NULL AS domain, NULL AS theme, NULL AS enabled_modules,
         email, password_hash, role, site_id, preferences,
         created_at, updated_at
     FROM users WHERE id = ?
     ```
3. **Decoupled View Theme Resolution:**
   * View renders are triggered using `App::render($view, $data)`.
   * If rendering an administrative or modular back-office view (prefixed with a prefix e.g., `admin/`), the resolver pulls templates from registered module folders (e.g. `src/Modules/Admin/Views/`).
   * If rendering a frontend page, it dynamically checks the active site's `theme` parameter (stored in the DB site record), resolving views from `src/Views/themes/{activeTheme}/`. If a file is missing, it gracefully falls back to `src/Views/themes/default/`.
4. **Dynamic Tenant Favicon Resolution:**
   * The system dynamically resolves backend favicons to prevent broken assets under customizable themes. If the site theme is mapped as `'default'`, the engine maps it to `/assets/favicons/corporate.svg`.
   * In lists and within `layout.php`, it verifies physical asset file existence (`file_exists()`), defaulting securely to prevent client-side broken placeholders.

---

### II. Dynamic Layout Page Builder Engine
Pages are stored in the database under a text field named `content` containing serialized JSON payloads of multiple visual blocks:

```json
[
  {"type": "text", "title": "Welcome", "content": "<p>Raw HTML</p>"},
  {"type": "accordion", "title": "FAQs", "items": [{"title": "Question?", "content": "Answer"}]}
]
```

1. **Server-Side Pre-Rendering (`src/Modules/Admin/Views/block_builder.php`):**
   * The page builder retrieves all dynamically registered block configs from `App::getRegisteredBlocks()`.
   * For each block type, it mocks an empty context block and buffers its admin template view (dynamically resolved from the block's `'admin_view'` config option, falling back to `src/Modules/Admin/Views/blocks/{type}.php`) into an output buffer to compile a dictionary:
     ```php
     const REGISTERED_BLOCK_TEMPLATES = <?php echo json_encode($preRenderedTemplates); ?>;
     ```
2. **Client-Side Instant Insertion:**
   * When an editor opens the sliding block inserter modal drawer and adds a block, JavaScript instantly fetches its raw HTML fields layout from `REGISTERED_BLOCK_TEMPLATES[type]` and appends it to the DOM—ensuring zero latency and absolute modular decoupling.
3. **Convention-Based Generic JS Serializer:**
   * Instead of utilizing separate JavaScript code blocks to parse and serialize different fields, a single, fully generic JavaScript function compiles the page state upon save by analyzing DOM class structures:
     * Values of elements matching `.block-title-input` are assigned to `blockData.title`.
     * Content of editors matching `.editor-area` are assigned to `blockData.content`.
     * Inputs with classes matching `.block-{field}-input` (e.g. `.block-image_path-input`) are automatically parsed into `blockData.{field}`.
     * Nested child item rows matching `.{type}-item-row` (e.g. `.accordion-item-row`) are scanned, automatically collecting child fields into `blockData.items = [...]`.

---

### III. System Database Schema Blueprint

Reconstruct the entire database model layout using these actual schema rules:

```sql
-- 0. Migrations Tracking
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

-- 3. Media Assets
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

-- 4. Multi-Tenant Pages (Content blocks container with display precedence)
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
    precedence INT DEFAULT 0, -- Order / priority of display (Added in Migration 0004)
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

-- 7. Blog Module Posts
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

---

### IV. Raw TCP Socket Emailer (`src/Support/Emailer.php`)
To maintain complete framework decoupling and remove any third-party dependencies, Zero CMS utilizes a manual **SMTP TCP socket transceiver** directly on raw network streams to dispatch transaction updates and account credentials:

1. Opens a socket connection to the target mail server using `fsockopen()`:
   ```php
   $socket = fsockopen($host, $port, $errno, $errstr, 5);
   ```
2. Manually loops, listens, and pushes SMTP protocol commands strictly, verifying server return codes on each dialogue step:
   * Expects code `220` on initial handshakes.
   * Dispatches `EHLO {domain}` $\rightarrow$ expects `250`.
   * Dispatches `MAIL FROM: <...>` $\rightarrow$ expects `250`.
   * Dispatches `RCPT TO: <...>` $\rightarrow$ expects `250`.
   * Dispatches `DATA` $\rightarrow$ expects `354`.
   * Sends custom boundaries, mime envelopes, headers, and the encoded UTF-8 body.
   * Sends the termination dot on an empty line `\r\n.\r\n` $\rightarrow$ expects `250`.
   * Dispatches `QUIT` to close connection.

---

### V. Multi-Tenant Database Seeder System (`seeders/seeder.php`)
Our seeder architecture is fully structured, self-healing, and reproducible:
1. Reverts all tables sequentially from bottom to top (Shop, Blog, then Core CMS).
2. Re-runs database migrations sequentially from top to bottom.
3. Decodes tenant JSON seeder maps inside `seeders/data/` (e.g. `shop.json`, `documentation.json`) and loops over row matrices.
4. Generates time-ordered UUIDv7 identifiers if they are not explicitly specified inside rows (preserving structural ordering of items during query display).
5. Automatically bundles and compiles zipped package distributions of site data for production environments, concluding with 100% success states.

---

### VI. Zero-Dependency Google OAuth 2.0 Integration & Scoping
To provide modern Single-Sign-On authentication without relying on bloated libraries, Zero CMS implements raw OAuth 2.0 flows:

1. **Anti-CSRF State Tokens:**
   * Before redirecting users to the Google Authorization page, the engine registers a random hex state token inside `$_SESSION['_csrf_token']` using `Security::csrfToken()`.
   * Google redirects the callback, passing this token inside the `state` parameter. The Callback controller verifies this immediately using `Security::csrfVerify($state)`, mitigating CSRF hijacking.
2. **Standard Exchange Handshake (`src/Modules/Admin/Controllers/GoogleAuthController.php`):**
   * Uses native PHP `cURL` POST session to exchange Google's temporary authorization code for a secure Access Token:
     ```php
     $ch = curl_init('https://oauth2.googleapis.com/token');
     // POST payload with client_id, client_secret, redirect_uri, and code
     ```
   * Employs a secure GET session with authorization bearer headers to retrieve the user's validated profile email from `https://www.googleapis.com/oauth2/v3/userinfo`.
3. **Scrict Multi-Tenant Scoping Boundaries:**
   * After resolving the Google user email, the database is queried.
   * To prevent data leaks and crossing multi-tenant boundaries, the resolved user must be a global platform `super_admin` OR their assigned `site_id` must match `App::getCurrentSiteId()`.
   * Failing this check redirects users back with a specific isolation mismatch security warning.

---

### VII. Zero-Dependency Automated Testing Suite
To ensure absolute system stability, security, and multi-tenant isolation without introducing bloated third-party frameworks (such as PHPUnit or Pest), Zero CMS features a custom **Zero-Dependency Automated Testing Suite** nested inside the `/tests/` directory at the project root:

1. **Unified Test Bootstrap (`tests/bootstrap.php`):**
   * Configures PHP session structures safely for CLI execution.
   * Registers a local PSR-4 namespace autoloader pointing to the `/src/` kernel directory.
   * Loads the project environment (`.env`) via the native `Env` loader helper.
   * Exposes a unified colorized terminal assertion function `assert_test(bool $condition, string $message)` that prints clean visual checkmarks (`✅ PASS`) or failure crossmarks (`❌ FAIL`), terminating execution with exit status code `1` upon any failure to signal the runner.
2. **Subprocess Master Test Runner (`tests/run.php`):**
   * Dynamically scans the `/tests/` directory for any test suites matching `*Test.php`.
   * Sorts and runs each suite sequentially in an **isolated PHP subprocess** (`exec("php {testFile}")`), ensuring that static variables, mock session variables, and database connections do not bleed between runs.
   * Captures and displays real-time execution outputs indented for clear structure.
   * Aggregates result statuses based on subprocess exit codes and outputs a high-contrast terminal report summary.
3. **Core Regression Verification Suites:**
   * `CascadesDeletesTest.php`: Tests parent-to-child soft and force deletion cascading capabilities, ensuring shared assets (like media) are kept untouched.
   * `DBTest.php`: Tests database connections, transactional trackers, query logs, schema column checks, and identity mapping caches.
   * `EnvTest.php`: Tests environment file parsing, comment/quote cleaning, and default fallbacks.
   * `HasSlugTest.php`: Tests slug lookup routines, tenant boundaries, and public draft visibility restrictions.
   * `I18nTest.php`: Tests localized translations, variable placeholder merges, and resets.
   * `IsOrderableTest.php`: Tests traits checking, reordering, and model precedence updates.
   * `LoggerTest.php`: Tests db audit logger entries and JSON metadata.
   * `ModelTest.php`: Tests full CRUD active-record operations, site isolation, soft deletes, and force deletes.
   * `PostPrecedenceTest.php` & `PrecedenceTest.php`: Tests list precedence reordering and ensures non-orderable model bypass overrides are fully protected.
   * `RouterTest.php`: Tests route patterns registration, dynamic parameter matching, and active module controllers.
   * `SecurityAuditTest.php`: Tests controller instantiation, system telemetry collection, fallback Markdown report compiler, and Router integrations mapping.
   * `SecurityTest.php`: Tests CSRF tokens, recursive inputs sanitization, UUIDv7 compliance, and XSS HTML protection.
   * `StorageTest.php`: Tests files writing, URLs resolving, directory cleaning, and deletes.
   * `TemplateTest.php`: Tests view rendering, extraction, and warning suppression.

#### ⚠️ THE CONTINUOUS INTEGRATION MANDATE
To maintain the technical integrity of Zero CMS:
* **Rule:** **Whenever ANY changes are made to the codebase (controllers, models, styles, views, or database tables), the master test runner MUST be executed to verify no regressions have been introduced:**
  ```bash
  docker exec -w /data/misc/zero php83 php tests/run.php
  ```
* All suites **MUST** return a clean `100% SUCCESS` state before any work is considered complete or ready for staging/deployment.

---

### VIII. Extensible Declarative OOP Input Validator (`src/Core/Validator.php`)
To provide robust, enterprise-grade input verification and error compilation across core and dynamic modules with 0% library overhead, Zero CMS implements an extensible, fully object-oriented declarative validator engine:

1. **Declarative Rule Configuration:**
   * Validations are defined using pipeline-separated syntax mapping input database fields to specific verification rules:
     ```php
     $rules = [
         'email' => 'required|email|max:255',
         'age' => 'required|integer|min:18'
     ];
     ```
2. **Standard & Custom Constraints Verification:**
   * `required`: Verifies fields are not null, empty strings, or empty arrays.
   * `email`: Enforces strict RFC 5322 compliance on mail addresses.
   * `phone`: Validates international/local telephone formats securely.
   * `numeric` & `integer`: Verifies numbers and forces index typecasting.
   * `min` & `max`: Restricts character lengths on strings, sizes on arrays, and boundaries on integers.
3. **Extendable Custom Validations:**
   * Easily register anonymous lambda validation hooks dynamically using `addRule()` at runtime to execute specialized, custom business metrics:
     ```php
     $validator->addRule('even', function($field, $value) {
         return is_numeric($value) && (intval($value) % 2 === 0);
     }, "The {field} field must be an even number.");
     ```

---

### IX. Relational Blog Commenting & Moderation Pipeline
To support user engagement without sacrificing multi-tenant isolation or backend performance, the Blog module features a robust relational commenting and administrative moderation pipeline:

1. **Multi-Tenant Database Archiving (`blog_comments`):**
   ```sql
   CREATE TABLE blog_comments (
       id VARCHAR(36) PRIMARY KEY,
       site_id VARCHAR(36) NOT NULL,
       post_id VARCHAR(36) NOT NULL,
       author_name VARCHAR(255) NOT NULL,
       author_email VARCHAR(255) NOT NULL,
       content TEXT NOT NULL,
       status VARCHAR(20) DEFAULT 'pending', -- pending, approved
       created_at DATETIME,
       updated_at DATETIME,
       deleted_at DATETIME NULL,
       INDEX (site_id),
       INDEX (post_id)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ```
2. **Comment Status Moderation by Default:**
   * For spam prevention and content protection, newly submitted public comments default strictly to `'pending'`.
   * Public blog list views exclusively load approved comments using left-join pagination queries.
   * Administrators moderate pending comment submissions inside a read-only list widget, switching them to `'approved'` inside the panel to publish.
3. **Decoupled Comment Notifiers & TCP Mail dispatching:**
   * Blog articles support multiple-select boxes inside model edit sheets allowing publishers to select multiple administrative users.
   * Stored as a serialized JSON array of database **User ID UUIDv7 strings** in the `blog_posts` table's `comment_notifiers` column.
   * Upon public comment submissions, the server resolves selected users' email addresses dynamically and pushes responsive HTML mail notification headers via raw SMTP TCP sockets.

---

### X. Decoupled Form Builder & Archival Submissions Engine
Zero CMS features a fully dynamic, fully featured, and 100% styled-separated **Form Builder block** that enables designers to construct custom web forms dynamically inside the page builder:

1. **Dynamic Field Schemes (`items` array):**
   * Form blocks are saved as serialized JSON blocks containing an array of dynamic field specifications:
     ```json
     {
       "type": "form_builder",
       "id": "cf_custom_recruitment",
       "recipient_email": "careers@zero.cms",
       "items": [
         {"name": "full_name", "label": "Full Name", "type": "text", "required": "1", "validation": "none"},
         {"name": "subject", "label": "Area of Interest", "type": "select", "required": "0", "options": "Engineering, Design, Marketing", "validation": "none"}
       ]
     }
     ```
2. **Dynamic Server-Side Validation Compilation:**
   * Upon submission, the API Controller loaded by `/api/v1/contact/submit` extracts the block's fields layout from the database.
   * It dynamically compiles a matching verification rules matrix on-the-fly, running inputs through the core `Validator` engine.
3. **Self-Contained JSON Database Archival (`message`):**
   * Submissions pass validation and are persisted in the `form_submissions` table.
   * All submitted inputs are mapped as `label => value` and archived as a **beautifully structured JSON string inside the `message` TEXT column**.
   * It dynamically resolves and appends the source **Page Title** and **Form Title** inside the JSON string as metadata, preserving the form's state historically even if the page is later modified or deleted.
4. **Custom Submissions Viewer Dashboard:**
   * Registered as the `'submissions'` model in the admin core to render full search, pagination, and soft-delete lists out of the box.
   * Features a custom, read-only display card inside `edit.php` (no inline styles, nested in `components.css`) that cleanly maps out the meta header, source pages, sender details, and displays every dynamic field's label and value in structured high-contrast list cards.

---

### XI. Interactive Community Forum & Nested Threaded Replies
To support collaborative communities without sacrificing security and multi-tenant isolation, the Forum module implements a highly interactive, style-separated nested reply engine:

1. **Relational Threading Schema (`forum_posts`):**
   * Reply postings utilize a self-referential `parent_id` column inside the `forum_posts` table to model deep conversation trees.
   * Thread replies are automatically grouped and resolved relative to their root parent post, completely avoiding flat-list clutter.
2. **Modular Reply Overlay Modals:**
   * Thread reply dialogs are nested completely within the native `.forum-container` DOM element wrapper inside `src/Views/themes/forum/forum_thread.php` to prevent theme style-bleed.
   * Features a fully native Markdown cursor-formatting helper toolbar bound dynamically in parent JavaScript onto post reply forms.
3. **Admin Moderation & Relational SHORTCUTS:**
   * Back-office lists convert raw foreign UUIDv7 keys into direct clickable relational shortcuts (e.g. converting `board_id` to clickable Board model edit form, or `thread_id` to its parent Thread sheet).
   * Restricts sensitive relationship associations (such as user, board, and thread mappings) to read-only status widgets inside the edit panel, preventing unauthorized privilege escalation or context cross-pollution.
