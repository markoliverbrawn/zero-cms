<?php

declare(strict_types=1);

/**
 * File: src/Modules/DemoGenerator/Controllers/AdminCreateDemoSiteController.php
 * Architectural Purpose: Admin-triggered "Create Demo Site" action, registered as a list action
 * on the Sites admin listing page via App::registerModelListAction(). Unlike the public
 * demo-request flow (DemoController), this runs behind an already-authenticated super_admin
 * session, so the generated credentials are returned directly in the JSON response instead of
 * being emailed.
 * Package: Zero\Modules\DemoGenerator\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\DemoGenerator\Controllers;

use Exception;
use Zero\Core\App;
use Zero\Interfaces\Controller;
use Zero\Modules\DemoGenerator\Services\DemoSiteFactory;
use Zero\Support\Logger;
use Zero\Support\Security;

/**
 * Class AdminCreateDemoSiteController
 *
 * Creates a kitchensink demo site on behalf of the currently logged-in super admin.
 */
class AdminCreateDemoSiteController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $matches
     * @return void
     */
    public function handle($matches): void
    {
        App::applyAuthMiddleware();
        App::applyRoleMiddleware('super_admin');
        App::applyCsrfMiddleware();

        \header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? null;

        // The new site's admin account needs its own email (users.email is globally unique
        // across every tenant) -- it's never emailed anywhere, only shown once in this response,
        // so a synthetic address scoped to this demo is safer than reusing the triggering admin's
        // real one (which would collide with their existing account on every other tenant).
        $demoIdSeed = \substr(\str_replace('-', '', Security::uuidv7()), 0, 8);
        $syntheticEmail = "demo-admin-{$demoIdSeed}@d6laptop.zero";

        try {
            $demo = (new DemoSiteFactory())->createDemoSite($syntheticEmail, 'kitchensink');

            Logger::log($userId, 'admin_demo_site_created', 'sites', null, ['domain' => $demo['domain']]);

            echo \json_encode([
                'success' => true,
                'message' => "Demo site created: http://{$demo['domain']} (login: {$syntheticEmail} / password: {$demo['password']}). It expires automatically in 24 hours."
            ]);
        } catch (Exception $e) {
            Logger::log($userId, 'admin_demo_site_failed', 'sites', null, ['error' => $e->getMessage()]);
            echo \json_encode(['success' => false, 'error' => 'Server Error: ' . $e->getMessage()]);
        }

        exit;
    }
}
