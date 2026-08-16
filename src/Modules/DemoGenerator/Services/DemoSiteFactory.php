<?php

declare(strict_types=1);

/**
 * File: src/Modules/DemoGenerator/Services/DemoSiteFactory.php
 * Architectural Purpose: Builds a fully isolated demo/sandbox site (site record, scoped
 * super_admin user, and seeded blueprint content) -- the shared logic behind both the public
 * demo-request flow (DemoController) and the admin-triggered "Create Demo Site" list action.
 * Package: Zero\Modules\DemoGenerator\Services
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\DemoGenerator\Services;

use Exception;
use Zero\Core\Storage\Storage;
use Zero\Database\DB;
use Zero\Support\Security;
use Zero\Support\SeederRunner;

/**
 * Class DemoSiteFactory
 *
 * Creates a new isolated demo site and administrator user, seeded from a named blueprint.
 */
class DemoSiteFactory
{
    /**
     * Create a new fully isolated demo site and administrator user scoped to the new site.
     *
     * @param string $email
     * @param string $preset
     * @return array{domain: string, password: string}
     * @throws Exception
     */
    public function createDemoSite(string $email, string $preset): array
    {
        $siteId = Security::uuidv7();
        $demoId = \substr(\md5($siteId), 0, 8);
        $domain = self::buildDemoDomain($demoId);
        $password = \bin2hex(\random_bytes(4)); // Secure temporary 8-char password

        // Build active modules list based on preset
        $enabledModules = ['blog'];
        if ($preset === 'shop') {
            $enabledModules[] = 'shop';
        } elseif ($preset === 'kitchensink') {
            // Mirrors kitchensink.php's own 'enabled_modules' list exactly -- a demo site created
            // from this preset should have parity with the CLI-seeded kitchensink site, not a
            // narrower subset (previously missing security/queue/site-search entirely, which is
            // why e.g. site search 404s on every demo-created kitchensink site).
            $enabledModules[] = 'shop';
            $enabledModules[] = 'forum';
            $enabledModules[] = 'formbuilder';
            $enabledModules[] = 'security';
            $enabledModules[] = 'queue';
            $enabledModules[] = 'site-search';
        }

        DB::getPDO()->beginTransaction();
        try {
            // 1. Create Site with 24 hours expiry time
            DB::query("
                INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at, expires_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)
            ", [
                $siteId,
                "Demo Site #" . \strtoupper($demoId),
                $domain,
                $preset,
                \json_encode($enabledModules),
                \date('Y-m-d H:i:s', \time() + 86400)
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
                \password_hash($password, PASSWORD_BCRYPT)
            ]);

            // 3. Seed from blueprint
            $this->seedFromBlueprint($siteId, $preset, $userId, $enabledModules);

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
     * Build the new demo site's domain. When the CURRENT request itself arrived on a
     * non-standard port (e.g. a local docker-compose dev setup exposed as
     * 'test.localhost:8370'), generate an equally port-addressable '*.localhost' domain so the
     * new sandbox is immediately reachable with no DNS/hosts-file setup -- every modern browser
     * and OS resolver treats any '*.localhost' subdomain as loopback natively. Otherwise (a real
     * domain-based deployment with no port in the request), keep the existing wildcard-subdomain
     * convention.
     *
     * @param string $demoId
     * @return string
     */
    protected static function buildDemoDomain(string $demoId): string
    {
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        if (\strpos($currentHost, ':') !== false) {
            $port = \explode(':', $currentHost)[1];
            return "demo-{$demoId}.localhost:{$port}";
        }

        return "demo-{$demoId}.d6laptop.zero";
    }

    /**
     * Copy seeder blueprints dynamically into database records.
     *
     * @param string $siteId
     * @param string $preset
     * @param string $adminUserId The sandbox's own super_admin user, attributed as the author of
     *   any seeded sample content (e.g. forum threads) that requires a valid user_id.
     * @param string[] $enabledModules
     * @return void
     */
    protected function seedFromBlueprint(string $siteId, string $preset, string $adminUserId, array $enabledModules): void
    {
        $blueprintPath = APPLICATION_ROOT . "/src/Modules/DemoGenerator/Seeders/{$preset}.php";
        if (!\file_exists($blueprintPath)) {
            return;
        }

        $data = require $blueprintPath;
        if (!\is_array($data)) {
            return;
        }

        // Initialize translation maps
        $mediaIdMap = [];

        // 1. Copy Media metadata and physical files FIRST to populate translation map
        if (isset($data['media']) && \is_array($data['media'])) {
            $targetDir = Storage::getUploadsRoot() . '/' . $siteId;
            if (!\file_exists($targetDir)) {
                \mkdir($targetDir, 0755, true);
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
                $seedImgPath = APPLICATION_ROOT . '/src/Modules/DemoGenerator/Seeders/data/images/' . $filename;
                $seedVidPath = APPLICATION_ROOT . '/src/Modules/DemoGenerator/Seeders/data/videos/' . $filename;
                $targetPath = $targetDir . '/' . $filename;

                if (\file_exists($seedImgPath)) {
                    \copy($seedImgPath, $targetPath);
                } elseif (\file_exists($seedVidPath)) {
                    \copy($seedVidPath, $targetPath);
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
        if (isset($data['pages']) && \is_array($data['pages'])) {
            foreach ($data['pages'] as $page) {
                $pageId = Security::uuidv7();
                $pagesMap[$page['slug']] = $pageId;

                $contentBlocks = $page['content'] ?? [];
                $contentJson = \is_array($contentBlocks) ? \json_encode($contentBlocks) : ($page['content'] ?? '');

                // Translate all old hardcoded media IDs into the new sandbox-specific unique IDs
                foreach ($mediaIdMap as $oldId => $newId) {
                    $contentJson = \str_replace($oldId, $newId, $contentJson);
                }

                DB::query("
                    INSERT INTO pages (id, site_id, title, slug, content, type, controller, view, summary, omit_title, show_in_nav, status, precedence, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', ?, NOW(), NOW())
                ", [
                    $pageId,
                    $siteId,
                    $page['title'],
                    $page['slug'],
                    $contentJson,
                    $page['type'] ?? 'page',
                    $page['controller'] ?? null,
                    $page['view'] ?? null,
                    $page['summary'] ?? null,
                    $page['omit_title'] ?? 0,
                    $page['show_in_nav'] ?? 1,
                    $page['precedence'] ?? 0
                ]);
            }
        }

        // Self-healing homepage_id on the new site if available
        if (isset($pagesMap[''])) {
            DB::query("UPDATE sites SET homepage_id = ? WHERE id = ?", [$pagesMap[''], $siteId]);
        }

        // 2b. Copy FormBuilder sample submissions (a leaf table -- no foreign keys to remap,
        // unlike forum/blog content -- so this is safe to clone directly with fresh IDs).
        if (isset($data['form_submissions']) && \is_array($data['form_submissions'])) {
            foreach ($data['form_submissions'] as $submission) {
                DB::query("
                    INSERT INTO form_submissions (id, site_id, name, email, phone, message, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ", [
                    Security::uuidv7(),
                    $siteId,
                    $submission['name'],
                    $submission['email'],
                    $submission['phone'] ?? null,
                    $submission['message'] ?? ''
                ]);
            }
        }

        // 3. Copy Forum boards/threads (if declared by this preset). Each row gets a freshly
        // generated ID -- the blueprint's own hardcoded IDs are shared across every demo instance
        // cloned from it, so reusing them verbatim would collide on a table with a global (not
        // per-site) primary key the second time this preset is seeded. This only lays down the
        // boards/threads shell; ForumPostSeeder (invoked generically below) requires at least one
        // thread to already exist before it will populate any replies.
        $boardIdMap = [];
        if (isset($data['forum_boards']) && \is_array($data['forum_boards'])) {
            foreach ($data['forum_boards'] as $board) {
                $newBoardId = Security::uuidv7();
                if (isset($board['id'])) {
                    $boardIdMap[$board['id']] = $newBoardId;
                }

                DB::query("
                    INSERT INTO forum_boards (id, site_id, title, slug, description, precedence, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ", [
                    $newBoardId,
                    $siteId,
                    $board['title'],
                    $board['slug'],
                    $board['description'] ?? null,
                    $board['precedence'] ?? 0
                ]);
            }
        }

        if (isset($data['forum_threads']) && \is_array($data['forum_threads'])) {
            foreach ($data['forum_threads'] as $thread) {
                $boardId = $boardIdMap[$thread['board_id']] ?? null;
                if (!$boardId) {
                    continue; // Skip threads referencing a board that wasn't seeded above
                }

                DB::query("
                    INSERT INTO forum_threads (id, site_id, board_id, user_id, title, slug, status, views_count, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ", [
                    Security::uuidv7(),
                    $siteId,
                    $boardId,
                    $adminUserId, // Attribute seeded sample threads to the sandbox's own admin user
                    $thread['title'],
                    $thread['slug'],
                    $thread['status'] ?? 'published',
                    $thread['views_count'] ?? 0
                ]);
            }
        }

        // 4. Run every module's dynamic class seeder (e.g. BlogArticleSeeder, ShopSeeder,
        // ForumPostSeeder) whose module is enabled for this site -- the exact same discovery and
        // priority-ordering mechanism bin/seed uses (Zero\Support\SeederRunner), so any future
        // module's Seeders/*Seeder.php class automatically gains demo-site support with no changes
        // needed here.
        //
        // These seeder classes were written for bin/seed's CLI context and echo verbose progress
        // output directly -- fine on a terminal, but this method also runs synchronously inside
        // live HTTP requests (DemoController, AdminCreateDemoSiteController), where that output
        // would otherwise leak into the response body ahead of the controller's own json_encode
        // payload and break JSON parsing on the client. Buffer and discard it here rather than
        // touching every seeder's own CLI-oriented echo calls.
        $classSeeders = SeederRunner::discoverClassSeeders(APPLICATION_ROOT . '/src/Modules');
        \ob_start();
        try {
            foreach ($classSeeders as $oopSeeder) {
                if (\in_array($oopSeeder->getModuleId(), $enabledModules, true)) {
                    $oopSeeder->run($siteId, Storage::getUploadsRoot());
                }
            }
        } finally {
            \ob_end_clean();
        }

        // 5. Index this site's freshly-seeded content into search_index, scoped to just this
        // site. Every insert above used raw DB::query() rather than the ActiveRecord ->save()
        // path, so none of it went through IsModel's automatic indexInSearch() hook -- without
        // this, a demo site with 'site-search' enabled would stop 404ing but silently return zero
        // results for everything.
        if (\in_array('site-search', $enabledModules, true) && \class_exists('\\Zero\\Modules\\Search\\Services\\SearchService')) {
            foreach (\array_keys(\Zero\Modules\Search\Services\SearchService::getSearchables()) as $modelClass) {
                if (\class_exists($modelClass)) {
                    $tableName = $modelClass::getTableName();
                    $rows = DB::query("SELECT * FROM {$tableName} WHERE site_id = ? AND deleted_at IS NULL", [$siteId])->fetchAll();
                    foreach ($rows as $row) {
                        $model = new $modelClass($row);
                        $model->indexInSearch();
                    }
                }
            }
        }
    }
}
