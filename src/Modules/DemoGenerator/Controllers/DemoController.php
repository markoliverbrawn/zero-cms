<?php
/**
 * File: src/Modules/DemoGenerator/Controllers/DemoController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\DemoGenerator\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */


// src/Modules/DemoGenerator/Controllers/DemoController.php

namespace Zero\Modules\DemoGenerator\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\Template;
use Zero\Database\DB;
use Zero\Support\Emailer;
use Zero\Support\Security;
use Zero\Support\Logger;
use Exception;

/**
 * Class DemoController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class DemoController implements Controller
{
    /**
     * Create a new fully isolated demo site and administrator user scoped to the new site.
     */
    protected function createDemoSite(string $email, string $preset): array
    {
        $siteId = Security::uuidv7();
        $demoId = substr(md5($siteId), 0, 8);
        $domain = "demo-{$demoId}.d6laptop.zero";
        $password = bin2hex(random_bytes(4)); // Secure temporary 8-char password

        // Build active modules list based on preset
        $enabledModules = ['blog'];
        if ($preset === 'shop') {
            $enabledModules[] = 'shop';
        } elseif ($preset === 'kitchensink') {
            $enabledModules[] = 'shop';
            $enabledModules[] = 'forum';
            $enabledModules[] = 'formbuilder';
        }

        DB::getPDO()->beginTransaction();
        try {
            // 1. Create Site with 24 hours expiry time
            DB::query("
                INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at, expires_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)
            ", [
                $siteId,
                "Demo Site #" . strtoupper($demoId),
                $domain,
                $preset,
                json_encode($enabledModules),
                date('Y-m-d H:i:s', time() + 86400)
            ]);

            // 2. Create Admin User (Scoped strictly to this new site)
            $userId = Security::uuidv7();
            DB::query("
                INSERT INTO users (id, site_id, username, email, password_hash, role, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'super_admin', NOW(), NOW())
            ", [
                $userId,
                $siteId,
                $email, // Set username to email to satisfy global unique constraints
                $email,
                password_hash($password, PASSWORD_BCRYPT)
            ]);

            // 3. Seed from blueprint
            $this->seedFromBlueprint($siteId, $preset);

            DB::getPDO()->commit();

            return [
                'domain' => $domain,
                'password' => $password
            ];
        } catch (Exception $e) {
            DB::getPDO()->rollBack();
            throw $e;
        }
    }

    /**
     * Dispatch sandbox credentials securely using standard, zero-dependency SMTP network streams.
     */
    protected function dispatchCredentialsEmail(string $email, string $domain, string $password): void
    {
        $subject = "Your Zero CMS Sandbox Demo Credentials";
        
        $templatePath = APPLICATION_ROOT . '/src/Views/emails/demo_credentials.php';
        $htmlBody = Template::renderFile($templatePath, [
            'email' => $email,
            'domain' => $domain,
            'password' => $password
        ]);
        
        $textBody = "Your Zero CMS Sandbox Demo is Ready!\n\nSandbox Domain: http://{$domain}\nAdmin URL: http://{$domain}/admin/dashboard\n\nLogin Credentials:\nUsername: {$email}\nPassword: {$password}\n\nNote: This sandbox is temporary and will be permanently deleted automatically in 24 hours.";

        Emailer::send($email, $subject, $htmlBody, $textBody);
    }

    /**
     * Primary entry point to process the sandbox request.
     */
    public function handle($matches): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Security Hardening: Enforce strict IP rate limiting (max 3 demos per 1 hour per IP)
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            if (!Security::checkAuthRateLimit('demo_creation', $ip, 3, 3600)) {
                Logger::log(null, 'demo_creation_failed', 'demo', null, ['ip_address' => $ip, 'error' => 'Rate limit exceeded']);
                http_response_code(429);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Rate limit exceeded. Please wait before creating another sandbox.']);
                exit;
            }

            $preset = $_POST['preset'] ?? 'kitchensink';
            $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

            if (!$email) {
                Logger::log(null, 'demo_creation_failed', 'demo', null, ['ip_address' => $ip, 'error' => 'Invalid email']);
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Please provide a valid email address.']);
                exit;
            }

            if ($preset !== 'kitchensink') {
                Logger::log(null, 'demo_creation_failed', 'demo', null, ['ip_address' => $ip, 'error' => 'Invalid preset']);
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid preset template selected. Only the Kitchen Sink Showroom is available.']);
                exit;
            }

            // Enforce a strict boundary: only one active sandbox demo site per email address at a time
            $existing = DB::query("
                SELECT s.domain 
                FROM users u
                JOIN sites s ON u.site_id = s.id
                WHERE u.email = ? AND s.expires_at > NOW() AND u.deleted_at IS NULL AND s.deleted_at IS NULL
                LIMIT 1
            ", [$email])->fetch();

            if ($existing) {
                Logger::log(null, 'demo_creation_failed', 'demo', null, ['ip_address' => $ip, 'error' => 'Active sandbox already exists', 'email' => $email]);
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => "An active sandbox is already registered to this email address (http://{$existing['domain']}). Please use it or wait for it to expire."
                ]);
                exit;
            }

            try {
                $demo = $this->createDemoSite($email, $preset);
                $this->dispatchCredentialsEmail($email, $demo['domain'], $demo['password']);

                Logger::log(null, 'demo_creation_success', 'demo', null, ['ip_address' => $ip, 'domain' => $demo['domain']]);

                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'domain' => $demo['domain'],
                    // Security Hardening: Do not disclose plain text passwords in public HTTP/API responses
                    'message' => 'Demo site generated successfully! Credentials have been sent to your email.'
                ]);
                exit;
            } catch (Exception $e) {
                Logger::log(null, 'demo_creation_failed', 'demo', null, ['ip_address' => $ip, 'error' => $e->getMessage()]);
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Server Error: ' . $e->getMessage()]);
                exit;
            }
        }

        // Default GET request 404
        http_response_code(404);
        echo "Not Found";
        exit;
    }

    /**
     * Copy seeder blueprints dynamically into database records.
     */
    protected function seedFromBlueprint(string $siteId, string $preset): void
    {
        $blueprintPath = APPLICATION_ROOT . "/src/Modules/DemoGenerator/Seeders/{$preset}.php";
        if (!file_exists($blueprintPath)) {
            return;
        }

        $data = require $blueprintPath;
        if (!is_array($data)) {
            return;
        }

        // Initialize translation maps
        $mediaIdMap = [];

        // 1. Copy Media metadata and physical files FIRST to populate translation map
        if (isset($data['media']) && is_array($data['media'])) {
            $targetDir = APPLICATION_ROOT . '/public/storage/uploads/' . $siteId;
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            foreach ($data['media'] as $media) {
                // Generate a brand-new globally unique ID for this sandbox record to prevent PRIMARY key collisions
                $newMediaId = Security::uuidv7();
                if (isset($media['id'])) {
                    $mediaIdMap[$media['id']] = $newMediaId;
                }

                $filename = $media['filename'] ?? '';
                $mediaPath = '/storage/uploads/' . $siteId . '/' . $filename;

                // Copy physical file from seed images or videos directories
                $seedImgPath = APPLICATION_ROOT . '/seeders/data/images/' . $filename;
                $seedVidPath = APPLICATION_ROOT . '/seeders/data/videos/' . $filename;
                $targetPath = $targetDir . '/' . $filename;

                if (file_exists($seedImgPath)) {
                    copy($seedImgPath, $targetPath);
                } elseif (file_exists($seedVidPath)) {
                    copy($seedVidPath, $targetPath);
                }

                DB::query("
                    INSERT INTO media (id, site_id, filename, path, mime, folder, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ", [
                    $newMediaId,
                    $siteId,
                    $filename,
                    $mediaPath,
                    $media['mime'] ?? '',
                    $media['folder'] ?? ''
                ]);
            }
        }

        // 2. Copy Pages and rewrite any old hardcoded media_id values
        $pagesMap = [];
        if (isset($data['pages']) && is_array($data['pages'])) {
            foreach ($data['pages'] as $page) {
                $pageId = Security::uuidv7();
                $pagesMap[$page['slug']] = $pageId;

                $contentBlocks = $page['content'] ?? [];
                $contentJson = is_array($contentBlocks) ? json_encode($contentBlocks) : ($page['content'] ?? '');

                // Translate all old hardcoded media IDs into the new sandbox-specific unique IDs
                foreach ($mediaIdMap as $oldId => $newId) {
                    $contentJson = str_replace($oldId, $newId, $contentJson);
                }

                DB::query("
                    INSERT INTO pages (id, site_id, title, slug, content, type, status, precedence, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'published', ?, NOW(), NOW())
                ", [
                    $pageId,
                    $siteId,
                    $page['title'],
                    $page['slug'],
                    $contentJson,
                    $page['type'] ?? 'page',
                    $page['precedence'] ?? 0
                ]);
            }
        }

        // Self-healing homepage_id on the new site if available
        if (isset($pagesMap[''])) {
            DB::query("UPDATE sites SET homepage_id = ? WHERE id = ?", [$pagesMap[''], $siteId]);
        }

        // 3. Copy Shop Categories
        if (isset($data['shop_categories']) && is_array($data['shop_categories'])) {
            foreach ($data['shop_categories'] as $cat) {
                $catImage = $cat['image'] ?? null;
                if (!empty($catImage)) {
                    $filename = basename($catImage);
                    $catImage = '/storage/uploads/' . $siteId . '/' . $filename;
                }

                DB::query("
                    INSERT INTO shop_categories (id, site_id, title, slug, description, image, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ", [
                    Security::uuidv7(),
                    $siteId,
                    $cat['title'],
                    $cat['slug'],
                    $cat['description'] ?? null,
                    $catImage
                ]);
            }
        }

        // 4. Copy Shop Products and variants
        if (isset($data['shop_products']) && is_array($data['shop_products'])) {
            foreach ($data['shop_products'] as $prod) {
                $prodId = Security::uuidv7();

                $mainImage = $prod['main_image'] ?? null;
                if (!empty($mainImage)) {
                    $filename = basename($mainImage);
                    $mainImage = '/storage/uploads/' . $siteId . '/' . $filename;
                }

                // Rewrite any media IDs in product gallery
                $mediaIds = $prod['media_ids'] ?? null;
                if (!empty($mediaIds)) {
                    foreach ($mediaIdMap as $oldId => $newId) {
                        $mediaIds = str_replace($oldId, $newId, $mediaIds);
                    }
                }

                DB::query("
                    INSERT INTO shop_products (id, site_id, title, slug, sku, description, price, compare_at_price, main_image, media_ids, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ", [
                    $prodId,
                    $siteId,
                    $prod['title'],
                    $prod['slug'],
                    $prod['sku'] ?? null,
                    $prod['description'] ?? null,
                    $prod['price'] ?? 0.00,
                    $prod['compare_at_price'] ?? null,
                    $mainImage,
                    $mediaIds,
                    $prod['status'] ?? 'published'
                ]);

                // Copy variants if present
                if (isset($prod['variants']) && is_array($prod['variants'])) {
                    foreach ($prod['variants'] as $v) {
                        DB::query("
                            INSERT INTO shop_product_variants (id, site_id, product_id, title, sku, price, stock, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ", [
                            Security::uuidv7(),
                            $siteId,
                            $prodId,
                            $v['title'],
                            $v['sku'],
                            $v['price'] ?? 0.00,
                            $v['stock'] ?? 0
                        ]);
                    }
                }
            }
        }
    }
}
