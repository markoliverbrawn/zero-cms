<?php

declare(strict_types=1);

/**
 * Zero CMS System Setup Wizard Controller
 *
 * This controller intercepts incoming HTTP requests on empty/unconfigured development
 * environments and guides the developer through initializing schemas and creating
 * their super administrator account.
 *
 * @package Zero\Modules\Admin\Controllers
 */

namespace Zero\Modules\Admin\Controllers;

use Exception;
use Zero\Core\App;
use Zero\Core\Env;
use Zero\Core\Validator;
use Zero\Database\DB;
use Zero\Database\MigrationManager;
use Zero\Support\Security;

/**
 * Class SetupWizardController
 *
 * Manages the multi-tenant setup wizard logic, validating input schemas,
 * executing database table migrations, and registering initial site and user records.
 */
class SetupWizardController
{
    /**
     * Intercept and route the incoming Setup Wizard request.
     *
     * @return void
     */
    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST') {
            $inputs = [
                'username' => \trim($_POST['username'] ?? ''),
                'email' => \trim($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'password_confirmation' => $_POST['password_confirmation'] ?? '',
                'site_name' => \trim($_POST['site_name'] ?? ''),
                'site_domain' => \trim($_POST['site_domain'] ?? ''),
                'site_timezone' => \trim($_POST['site_timezone'] ?? 'Pacific/Auckland'),
                'site_language' => \trim($_POST['site_language'] ?? 'en'),
                'site_theme' => \trim($_POST['site_theme'] ?? 'default')
            ];

            try {
                $this->runSetup($inputs);
            } catch (Exception $e) {
                $this->renderWizard([$e->getMessage()], $inputs);
            }
        } else {
            $this->renderWizard();
        }
    }

    /**
     * Render the self-contained widescreen Setup Wizard HTML template.
     *
     * @param array $errors Validation or migration errors to display
     * @param array $inputs Form values to preserve/re-populate
     * @return void
     */
    public function renderWizard(array $errors = [], array $inputs = []): void
    {
        // Define default fallback values
        $inputs['site_timezone'] = $inputs['site_timezone'] ?? 'Pacific/Auckland';
        $inputs['site_language'] = $inputs['site_language'] ?? 'en';
        $inputs['site_theme'] = $inputs['site_theme'] ?? 'default';

        // Include the view template directly to make the Setup Wizard 100% self-contained
        $viewPath = APPLICATION_ROOT . '/src/Modules/Admin/Views/setup-wizard.php';
        if (\file_exists($viewPath)) {
            require $viewPath;
        } else {
            \http_response_code(500);
            echo "Setup Wizard View file not found.";
        }
        exit;
    }

    /**
     * Perform the procedural tables-handshake, site-tenancy initialization, and admin user registration.
     *
     * @param array $inputs Sanity filtered form values
     * @return void
     * @throws Exception If input validation or database execution fails
     */
    public function runSetup(array $inputs): void
    {
        // 1. Core Declarative Validation
        $rules = [
            'username' => 'required|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|min:8',
            'password_confirmation' => 'required',
            'site_name' => 'required|max:255',
            'site_domain' => 'required|max:255'
        ];

        $validator = new Validator($inputs, $rules);
        if (!$validator->validate()) {
            $compiledErrors = [];
            foreach ($validator->getErrors() as $field => $fieldErrors) {
                foreach ($fieldErrors as $err) {
                    $compiledErrors[] = $err;
                }
            }
            throw new Exception(\implode(' | ', $compiledErrors));
        }

        if ($inputs['password'] !== $inputs['password_confirmation']) {
            throw new Exception("Password confirmation does not match password.");
        }

        // 2. Programmatically Run Migrations Upward to establish the database schema
        try {
            \ob_start();
            MigrationManager::up();
            \ob_end_clean();
        } catch (Exception $e) {
            if (\ob_get_level() > 0) {
                \ob_end_clean();
            }
            throw new Exception("Database Handshake Failure: " . $e->getMessage());
        }

        // Double check schema presence before writing records
        $sitesTableExists = DB::query("SHOW TABLES LIKE 'sites'")->fetch();
        if (!$sitesTableExists) {
            throw new Exception("Database error: Sites schema table is missing after migration execution.");
        }

        // 3. Insert default multi-tenant Site
        $siteId = Security::uuidv7();
        $siteDomain = $inputs['site_domain'];
        $siteName = $inputs['site_name'];
        $siteTheme = $inputs['site_theme'];
        $siteTimezone = $inputs['site_timezone'];
        $siteLanguage = $inputs['site_language'];

        DB::query("
            INSERT INTO sites (id, name, domain, theme, timezone, default_language, enabled_modules, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ", [
            $siteId,
            $siteName,
            $siteDomain,
            $siteTheme,
            $siteTimezone,
            $siteLanguage,
            \json_encode(['formbuilder', 'security', 'queue', 'site-search'])
        ]);

        // 4. Create and hash the Super Admin credentials
        $userId = Security::uuidv7();
        $passwordHash = \password_hash($inputs['password'], PASSWORD_BCRYPT);

        DB::query("
            INSERT INTO users (id, site_id, username, email, password_hash, role, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'super_admin', NOW(), NOW())
        ", [
            $userId,
            $siteId,
            $inputs['username'],
            $inputs['email'],
            $passwordHash
        ]);

        // 5. Seed a basic dynamic home page record with standard layout content
        $pageId = Security::uuidv7();
        $defaultContent = \json_encode([
            [
                'type' => 'text',
                'title' => 'Welcome to Zero CMS!',
                'content' => '<p>Your new multi-tenant portal has been successfully bootstrapped using the interactive Setup Wizard! Zero CMS has run database schema handshakes, registered your super admin credentials, and initialized this website dynamically.</p><p>You can now log into your <a href="/admin/login">Admin Dashboard</a> to configure pages, manage media, and analyze AI security threat audits.</p>'
            ]
        ]);

        DB::query("
            INSERT INTO pages (id, site_id, title, slug, content, status, created_at, updated_at)
            VALUES (?, ?, 'Home', '', ?, 'published', NOW(), NOW())
        ", [
            $pageId,
            $siteId,
            $defaultContent
        ]);

        // Self-heal site homepage reference
        DB::query("UPDATE sites SET homepage_id = ? WHERE id = ?", [$pageId, $siteId]);

        // 6. Log the newly registered admin in and redirect to dashboard
        if (\session_status() === PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $inputs['username'];
        $_SESSION['role'] = 'super_admin';

        // Redirect directly to the back office
        \header('Location: /admin/dashboard');
        exit;
    }
}
