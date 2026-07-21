# 🗺️ Zero CMS Guide Site Sitemap (Technical Developer Docs)

This document contains a comprehensive, multi-layered sitemap representation of the **Zero CMS Technical Developer Docs** guide site (seeded natively via `seeders/data/documentation.json` and hosted on the `d6laptop.zero.guide` tenant domain). 

It models the precise page structures, parent-child relationships, sub-page branches, and navigation visibility gates.

---

## 📊 Interactive Mermaid Sitemap Diagram

The flowchart below visualizes the site's information architecture. Top-level primary navigation items are highlighted in blue, the Home/Root page in deep green, general information sub-pages in grey, specialized developer How-To guide sheets in gold, modular subsystem specs in purple, and deep-dive technical tutorials in rose.

```mermaid
graph TD
    %% Node Definitions & Hierarchy
    home["🏠 Developer Docs Central<br/><i>(Slug: '')</i>"]
    
    %% Top Level Nav
    contact["✉️ Technical Contact & Feedback Form<br/><i>(Slug: 'contact')</i>"]
    blog["✍️ Blog Index<br/><i>(Slug: 'blog')</i>"]
    docs["📚 Developer Documentation Index<br/><i>(Slug: 'docs')</i>"]
    
    home --> contact
    home --> blog
    home --> docs
    
    %% Documentation Sub-pages
    benchmarks["⚡ Performance & Load Benchmarks<br/><i>(Slug: 'docs/benchmarks')</i>"]
    intro["🚀 Introduction to Zero CMS<br/><i>(Slug: 'docs/intro')</i>"]
    framework["⚖️ Zero vs. The Giants Framework Comparison<br/><i>(Slug: 'docs/framework-comparison')</i>"]
    limitations["⚠️ Limitations of Zero CMS<br/><i>(Slug: 'docs/limitations')</i>"]
    getting_started["⚙️ Installation & Environment Setup<br/><i>(Slug: 'docs/getting-started')</i>"]
    core_components["🧩 Core Components & Helpers<br/><i>(Slug: 'docs/core-components')</i>"]
    bootstrap["🔄 Single-Query Bootstrap Handshake<br/><i>(Slug: 'docs/bootstrap')</i>"]
    
    docs --> benchmarks
    docs --> intro
    docs --> framework
    docs --> limitations
    docs --> getting_started
    docs --> core_components
    docs --> bootstrap
    
    %% How-To Guides Hub
    how_tos["🔧 How-To Guides Index<br/><i>(Slug: 'docs/how-tos')</i>"]
    docs --> how_tos
    
    models["📦 Database Models & Active Record Traits<br/><i>(Slug: 'docs/how-tos/models')</i>"]
    views["🎨 How to Create Views & Fallbacks<br/><i>(Slug: 'docs/how-tos/views')</i>"]
    seeder["🌱 How to Create a Custom Seeder<br/><i>(Slug: 'docs/how-tos/seeder')</i>"]
    middleware["🛡️ How to Create Middleware Filters<br/><i>(Slug: 'docs/how-tos/middleware')</i>"]
    controllers["🎮 How to Create Controllers<br/><i>(Slug: 'docs/how-tos/controllers')</i>"]
    uuidv7["🔑 Understanding Time-Ordered UUIDv7 Keys<br/><i>(Slug: 'docs/how-tos/uuidv7')</i>"]
    emailer["📧 SMTP TCP Socket Emailing<br/><i>(Slug: 'docs/how-tos/emailer')</i>"]
    multitenancy["🔒 Multi-Tenant Data Isolation<br/><i>(Slug: 'docs/how-tos/multitenancy')</i>"]
    security_hard["🛡️ Core Security Hardening & Anti-XSS<br/><i>(Slug: 'docs/how-tos/security')</i>"]
    custom_views["🖼️ Custom Column listView Rendering<br/><i>(Slug: 'docs/how-tos/custom-views')</i>"]
    custom_blocks["🧱 Creating Page Builder Blocks<br/><i>(Slug: 'docs/how-tos/custom-blocks')</i>"]
    supervisor["🔄 Supervisor Queue Daemon Setup<br/><i>(Slug: 'docs/how-tos/supervisor-setup')</i>"]
    gcs["☁️ Configuring Google Cloud Storage<br/><i>(Slug: 'docs/how-tos/gcs-setup')</i>"]
    secure_uploads["🔏 Secure Uploads & Private Storage<br/><i>(Slug: 'docs/how-tos/secure-uploads')</i>"]
    s3["🗄️ Configuring AWS S3 Storage<br/><i>(Slug: 'docs/how-tos/aws-s3-setup')</i>"]
    search_arch["🔍 Search Index & Driver Architecture<br/><i>(Slug: 'docs/how-tos/search-architecture')</i>"]
    deploy["☁️ Serverless Deployments & low-cost Cloud Run<br/><i>(Slug: 'docs/how-tos/deploy-cloud-run')</i>"]
    
    how_tos --> models
    how_tos --> views
    how_tos --> seeder
    how_tos --> middleware
    how_tos --> controllers
    how_tos --> uuidv7
    how_tos --> emailer
    how_tos --> multitenancy
    how_tos --> security_hard
    how_tos --> custom_views
    how_tos --> custom_blocks
    how_tos --> supervisor
    how_tos --> gcs
    how_tos --> secure_uploads
    how_tos --> s3
    how_tos --> search_arch
    how_tos --> deploy
    
    %% Platform Modules Hub
    modules["📦 Platform Modules Directory<br/><i>(Slug: 'docs/modules')</i>"]
    docs --> modules
    
    mod_create["🧱 How to Create Modules<br/><i>(Slug: 'docs/modules/how-to-create')</i>"]
    mod_blog["📝 Blog & Commenting Module<br/><i>(Slug: 'docs/modules/blog')</i>"]
    mod_shop["🛒 Shop & E-Commerce Module<br/><i>(Slug: 'docs/modules/shop')</i>"]
    mod_form["📋 FormBuilder & Submissions Module<br/><i>(Slug: 'docs/modules/formbuilder')</i>"]
    mod_forum["💬 Community Forum Module Guide<br/><i>(Slug: 'docs/modules/forum')</i>"]
    mod_jobs["⏳ Background Jobs & Task Scheduler<br/><i>(Slug: 'docs/modules/jobs')</i>"]
    mod_security["🛡️ Security & Threat Auditing Module<br/><i>(Slug: 'docs/modules/security')</i>"]
    
    modules --> mod_create
    modules --> mod_blog
    modules --> mod_shop
    modules --> mod_form
    modules --> mod_forum
    modules --> mod_jobs
    modules --> mod_security
    
    %% Deep-Dive Advanced Tutorials
    blog_triggers["🔴 Tutorial: Comment Trigger Notifications<br/><i>(Slug: 'docs/modules/blog/comment-triggers')</i>"]
    jobs_tutorials["🔴 Tutorial: Creating & Dispatching Queue Jobs<br/><i>(Slug: 'docs/modules/jobs/tutorials')</i>"]
    
    mod_blog --> blog_triggers
    mod_jobs --> jobs_tutorials
    
    form_advanced["⚙️ FormBuilder Advanced Index<br/><i>(Slug: 'docs/modules/formbuilder/advanced')</i>"]
    form_custom_fields["🔴 Tutorial: Extending Validator Rules<br/><i>(Slug: 'docs/modules/formbuilder/custom-fields')</i>"]
    form_save_submissions["🔴 Tutorial: Capturing Form Submissions<br/><i>(Slug: 'docs/modules/formbuilder/save-submissions')</i>"]
    
    mod_form --> form_advanced
    form_advanced --> form_custom_fields
    form_advanced --> form_save_submissions

    %% Style Classes Mapping
    classDef home fill:#022c22,stroke:#10b981,stroke-width:2px,color:#fff;
    classDef nav fill:#1e3a8a,stroke:#3b82f6,stroke-width:2px,color:#fff;
    classDef page fill:#1f2937,stroke:#4b5563,stroke-width:1px,color:#fff;
    classDef howto fill:#3f2c00,stroke:#f59e0b,stroke-width:1.5px,color:#fff;
    classDef module fill:#3c0764,stroke:#8b5cf6,stroke-width:1.5px,color:#fff;
    classDef tutorial fill:#4c0519,stroke:#f43f5e,stroke-width:1.5px,color:#fff;

    class home home;
    class contact,blog,docs nav;
    class benchmarks,intro,framework,limitations,getting_started,core_components,bootstrap page;
    class how_tos,models,views,seeder,middleware,controllers,uuidv7,emailer,multitenancy,security_hard,custom_views,custom_blocks,supervisor,gcs,secure_uploads,s3,search_arch howto;
    class modules,mod_create,mod_blog,mod_shop,mod_form,mod_forum,mod_jobs,mod_security,form_advanced module;
    class blog_triggers,jobs_tutorials,form_custom_fields,form_save_submissions tutorial;
```

---

## 📂 Detailed Page Architecture & Configurations Directory

The table below catalogs every page record compiled on seed bootstrap, capturing their navigation visibility mapping (`show_in_nav`) and respective path routing rules.

| Page Title | Route Slug | Parent/Branch | Navigation Visible (`show_in_nav`) | Primary Block Element Types |
| :--- | :--- | :--- | :---: | :--- |
| **Developer Documentation Central** | `""` (Root) | Root / Gateway | **Yes (`1`)** | `baseline` (Video), `grid` (Platform Capabilities), `text`, `text_image` |
| **Technical Contact & Feedback Form** | `contact` | Root / Sidebar | **Yes (`1`)** | `form_builder` (Dynamic Contact Form Block) |
| **Blog** | `blog` | Root / Sidebar | **Yes (`1`)** | `latest_articles` (Grid block-renderer) |
| **Developer Documentation** | `docs` | Root / Sidebar | **Yes (`1`)** | `baseline`, `sub_pages` (Dynamic sub-page cards hub) |
| **Performance & Load Benchmarks** | `docs/benchmarks` | Docs | No (`0`) | `baseline`, `text`, `chart` (Throughput/Response), `grid`, `code` (ab log) |
| **Introduction to Zero CMS** | `docs/intro` | Docs | No (`0`) | `text` (Core Philosophy & Abstract Boundaries) |
| **Framework Comparison: Zero vs. The Giants** | `docs/framework-comparison` | Docs | No (`0`) | `text` (Comprehensive architectural metrics grid) |
| **Limitations of Zero CMS** | `docs/limitations` | Docs | No (`0`) | `text` (Dependency trade-offs & AI Paradox explanation) |
| **Installation and Environment Setup** | `docs/getting-started` | Docs | No (`0`) | `text`, `code` (local server & seed commands) |
| **Core Components and Core Helpers** | `docs/core-components` | Docs | No (`0`) | `text` (App Bootstrapper, DB PDO Prepared Wrapper) |
| **Single-Query Bootstrap and Performance** | `docs/bootstrap` | Docs | No (`0`) | `text`, `code` (Consolidated SQL UNION query, static singleton latch) |
| **How To's** | `docs/how-tos` | Docs / Sub-Hub | No (`0`) | `sub_pages` (Filtered Technical Guides list) |
| **Database Models and Active Record Traits** | `docs/how-tos/models` | How-Tos | No (`0`) | `text` (creating model, form configurations) |
| **How to Create Views** | `docs/how-tos/views` | How-Tos | No (`0`) | `text` (Template fallbacks, buffering, layout nesting) |
| **How to Create a Custom Seeder** | `docs/how-tos/seeder` | How-Tos | No (`0`) | `text` (JSON architecture, running imports) |
| **How to Create Middleware** | `docs/how-tos/middleware` | How-Tos | No (`0`) | `text`, `code` (Auth onion-pipeline handler) |
| **How to Create Controllers** | `docs/how-tos/controllers` | How-Tos | No (`0`) | `text` (Interface patterns) |
| **Understanding Time-Ordered UUIDv7 Keys** | `docs/how-tos/uuidv7` | How-Tos | No (`0`) | `text` (B-Tree pages clustering optimization) |
| **SMTP TCP Socket Emailing** | `docs/how-tos/emailer` | How-Tos | No (`0`) | `text` (Raw sockets manual dialogue & fsockopen) |
| **Multi-Tenant Data Isolation** | `docs/how-tos/multitenancy` | How-Tos | No (`0`) | `text` (physical site_id constraints inside IsModel) |
| **Core Security Hardening and Anti-XSS Pipelines** | `docs/how-tos/security` | How-Tos | No (`0`) | `text`, `code` (CsrfVerify, DOMDocument/DOMXPath scrubber) |
| **Custom Column Rendering (listView)** | `docs/how-tos/custom-views` | How-Tos | No (`0`) | `text` (custom badges, site modules list pill views) |
| **Creating Custom Page Builder Blocks** | `docs/how-tos/custom-blocks` | How-Tos | No (`0`) | `text` (View, Admin Edit fields templates, registration hooks) |
| **Setting Up Supervisor for the Job Queue** | `docs/how-tos/supervisor-setup` | How-Tos | No (`0`) | `text` (program configurations, daemon monitoring, log rotate) |
| **Configuring Google Cloud Storage (Zero Dependencies)** | `docs/how-tos/gcs-setup` | How-Tos | No (`0`) | `text` (Uniform vs ACL buckets, .env setup, Storage API) |
| **Secure Frontend Uploads & Private Storage** | `docs/how-tos/secure-uploads` | How-Tos | No (`0`) | `text` (binaries obfuscation, secure download route stream) |
| **Configuring AWS S3 Storage (Zero Dependencies)** | `docs/how-tos/aws-s3-setup` | How-Tos | No (`0`) | `text` (SigV4 cryptographic hmac signature generation) |
| **Search Index & Decoupled Driver Architecture** | `docs/how-tos/search-architecture` | How-Tos | No (`0`) | `text`, `code` (database search driver, block helpers, N+1 preventions) |
| **Serverless Blueprints: Google Cloud Run & Cloud SQL Setup** | `docs/how-tos/deploy-cloud-run` | How-Tos | No (`0`) | `text`, `code` (Stateless architecture, low-cost db-f1-micro instance/bucket creation, DB_SOCKET connection) |
| **Modules** | `docs/modules` | Docs / Sub-Hub | No (`0`) | `sub_pages` (Decoupled system modules list) |
| **How to Create Modules** | `docs/modules/how-to-create` | Modules | No (`0`) | `text` (Hot-swappable toggle structures, widgets) |
| **Blog & Commenting Module** | `docs/modules/blog` | Modules | No (`0`) | `text` (moderation flow), `code` (comment model), `sub_pages` (tutorials) |
| **Shop & E-Commerce Module** | `docs/modules/shop` | Modules | No (`0`) | `text` (capabilities), `code` (product/variant/order models, ACID FOR UPDATE lock) |
| **FormBuilder & Submissions Module** | `docs/modules/formbuilder` | Modules | No (`0`) | `text` (JSON archival storage), `code` (schema model), `sub_pages` (tutorials) |
| **Developer How-To** | `docs/modules/formbuilder/advanced` | Modules / FormBuilder | No (`0`) | `sub_pages` (Extensibility tutorials directory) |
| **Tutorial: Programmatically Extending Validator Rules** | `docs/modules/formbuilder/custom-fields` | Modules / FormBuilder | No (`0`) | `code` (Extending custom lambda rules at runtime) |
| **Tutorial: Programmatically Capturing Form Submissions** | `docs/modules/formbuilder/save-submissions` | Modules / FormBuilder | No (`0`) | `code` (Instantiating and persisting Submission model) |
| **Tutorial: Hooking Comments Notification Callback Triggers** | `docs/modules/blog/comment-triggers` | Modules / Blog | No (`0`) | `code` (Hooking comments API and dispatching TCP emails) |
| **Community Forum Module Guide** | `docs/modules/forum` | Modules | No (`0`) | `text` (physical schemas, cascading fallbacks, markdown toolbar) |
| **Background Jobs & Task Scheduler Module** | `docs/modules/jobs` | Modules | No (`0`) | `text` (race prevention double lock, CLI runners vs Async socket) |
| **Creating and Dispatching Queue Jobs** | `docs/modules/jobs/tutorials` | Modules / Jobs | No (`0`) | `text` (dispatching), `code` (SendOrderReceipt stateless class) |
| **Security & Threat Auditing Module** | `docs/modules/security` | Modules | No (`0`) | `text` (compliance, dynamic score calculation), `code` (schema definition) |
