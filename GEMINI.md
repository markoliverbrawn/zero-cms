# Zero CMS — Expert Architecture & Conventions

This document is a foundational mandate of engineering standards for **Zero CMS** — a zero-dependency, high-contrast, multi-tenant CMS and e-commerce platform. It covers conventions and architecture that apply broadly across the codebase. Deep-dive documentation for individual subsystems (page builder, DB schema, emailer, seeder, OAuth, testing, validator, blog comments, form builder, forum) lives as on-demand Agent Skills under `.agents/skills/` instead — see "Subsystem Deep-Dives" at the bottom of this file.

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
* **Module-Owned Language Dictionaries:** Independent modules must house their own translation dictionaries at `src/Modules/<Name>/Lang/{en,es,hr,mi}.php` and must never add module-specific keys to the core dictionaries in `src/Lang/`. The core dictionaries are reserved strictly for generic, framework-level vocabulary and the helper texts of core's own models (`Page`, `Media`, `User`, `Site`). `Zero\Support\I18n::init()` discovers and merges every `<Module>/Lang/<activeLang>.php` on disk automatically across all `App::getModuleSearchPaths()` — no registration call is required, the file on disk is the wiring. Deleting a module from the workspace must therefore remove its strings with it, leaving no orphaned keys behind in core.
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
* **Ownership:** A `{field}_help` key belongs to whichever package declares the field. Helper texts for fields on core models live in `src/Lang/<code>.php`; helper texts for fields on a module's models live in that module's own `src/Modules/<Name>/Lang/<code>.php` (Rule 2). Because there is no per-key fallback to English, every module must ship all four language files or the missing key renders as the raw key string in the admin UI.

### 7. Zero-Dependency External Handshakes (No Vendor SDKs)
* **Rule:** Integrations with external services (such as Google OAuth 2.0 or SMTP servers) must remain 100% dependency-free.
* **Convention:** Never introduce package managers or external third-party SDK libraries. Execute secure HTTP API handshakes using raw, native PHP `cURL` sessions and core network sockets directly, keeping the codebase extremely fast, transparent, and secure.

### 8. Mandatory Selector Nesting (CSS Nesting Syntax)
* **Rule:** All stylesheets located in `assets/css/` must strictly utilize modern native CSS nesting for all descendant, pseudo-class, and pseudo-element rules.
* **Convention:** Avoid flat, repetitive selector paths (e.g. do not use separate declarations for `header h1` and `header h1 a`). Nest child rules within their respective parents, using `&` to reference the parent context (such as `&:hover`, `&.active`, or `&::after`), preserving modularity and improving file readability.

### 9. Core Declarative Validator (`Zero\Core\Validator`)
* **Rule:** All modular input validations (such as contact forms, user profiles, or e-commerce reviews) must utilize the core `Zero\Core\Validator` engine.
* **Convention:** Always specify validation constraints in a clean, declarative array or pipe format (e.g. `'email' => 'required|email|max:255'`). Developers can extend the validation rules dynamically at runtime using `Validator::registerRule()`. Filter input values down strictly to validated and declared fields using `$validator->getValidatedData()` before database operations to mitigate unauthorized field injection vectors. (See the `input-validator` skill for the full engine reference.)

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
* **Convention:** Never reference fully namespaced class names inline inside the executable logic paths (e.g. do not write `$x = new \Zero\Core\Validator(...)` or call `\Zero\Support\Security::uuidv7()`). Instead, declare `use Zero\Core\Validator;` and `use Zero\Support\Security;` at the top of the file, and refer to them as `Validator` and `Security` directly. This applies universally to all backend and frontend PHP view templates. The one explicit exception is `Zero\Core\Autoloader` itself, which stays fully-qualified inline (`\Zero\Core\Autoloader::init();`) since it must be `require_once`'d and invoked before any `use`-based autoloading exists — there's nothing to `use` yet at that point. Bare PHP built-ins (`\Exception`, `\RuntimeException`, `\ReflectionClass`, etc.) are not covered by this rule and remain fully-qualified inline.

### 14. Alphabetical Method Sorting
* **Rule:** All class methods inside any PHP class (including core bootstrappers, active record models, custom middleware, and helper utilities) MUST be arranged in strict alphabetical order.
* **Convention:** Arrange the methods alphabetically (by method name) from A to Z, ensuring high readability, ease of file traversal, and structural consistency across the entire codebase.

### 15. Mandatory Feature & Module Documentation (Guide Site Seeding)
* **Rule:** Whenever a new feature, module, or system capability is added or substantially updated, it MUST be fully documented in the Guide site's seeder map, and the database MUST be cleanly re-seeded. The documentation must comprehensively cover:
  1. **High-Level Architectural Summary**: An accessible overview of the feature's design, purposes, and systemic role.
  2. **Configuration Information**: Complete description of environmental variable properties (e.g. `.env`) and setup settings.
  3. **Extension Developer How-Tos**: Code snippets, interfaces/contracts, and practical, clean examples demonstrating how to build, register, or extend the feature.
* **Convention:** See the `multitenant-seeder` skill for the exact seeder dataset/sitemap/navigation conventions and how to re-run the seeder.

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
    2. **Database-Free OOP Block Helpers:** Any helper classes (implementing `BlockHelperInterface`) used during indexing or rendering must operate purely in-memory using passed JSON attributes. They are strictly forbidden from executing any database queries or fetching relationships.

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

### 29. Mandatory File, Class, & Method Documentation (DocBlocks)
* **Rule:** Every newly created or modified PHP file, class, method, or function MUST carry extensive, detailed DocBlocks and structural comment headers. 
* **Convention:**
  - **File Header**: Every file must carry a verbose file-level comment block describing its architectural purpose, package, and systemic role.
  - **Class Block**: Every Class must carry a class-level DocBlock detailing its structural responsibilities and parent interfaces.
  - **Method DocBlock**: Every Method and Function must carry a standard JSDoc/PHPDoc style block defining parameter types (`@param`), return types (`@return`), and exceptions thrown (`@throws`).

---

## Part 2: Bootstrapping & Multi-Tenant Routing

This foundational request lifecycle underlies virtually every request Zero CMS handles, so it's kept here rather than as an on-demand skill:

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

## Subsystem Deep-Dives (Agent Skills)

Detailed architecture references for individual subsystems are no longer inlined in this file — they're on-demand Agent Skills under `.agents/skills/`, activated automatically when a task matches their description:

* `page-builder-engine` — dynamic layout page builder, block JSON structure, admin pre-rendering, generic JS serializer
* `db-schema-blueprint` — SQL schema reference for core/module tables, reconstructed from every migration file (update it whenever a new migration is added, or it'll drift again)
* `raw-tcp-emailer` — zero-dependency raw SMTP socket mailer
* `multitenant-seeder` — the `bin/seed`/`SeederRunner` revert/migrate/seed pipeline and documentation-seeding conventions
* `google-oauth-integration` — zero-dependency Google OAuth 2.0 SSO flow and tenant-scoping checks
* `test-suite-architecture` — how the test suite is laid out, discovered, and run (`bin/test`, `TestRunner`, `TestBootstrap`)
* `input-validator` — the declarative `Zero\Core\Validator` engine
* `blog-comments-pipeline` — Blog module commenting/moderation pipeline
* `form-builder-engine` — Form Builder block, dynamic field schemas, submissions archival
* `forum-threaded-replies` — Forum module nested-reply threading model
* `php-file-conventions` — enforcement checklist for creating/rewriting any PHP file
* `view-template-conventions` — how views actually execute (no auto-escaping, theme/prefix resolution) and template-specific rules (inline styles/scripts, back-office tables, UTC display, block preview sanitizer-safety)
* `module-creation` — scaffolding a new module: the `Module.php` contract, auto-discovery, migrations, routes, blocks, sidebar links, per-tenant enable/disable

There is no folder directory map in this file anymore — it went stale faster than it stayed useful. Explore the actual filesystem instead of trusting a hand-maintained snapshot.
