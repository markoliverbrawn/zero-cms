<?php
/**
 * File: src/Modules/Blog/Database/Migrations/0014_AddBlogPerformanceIndexes.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Blog\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */



namespace Zero\Modules\Blog\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddBlogPerformanceIndexes
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AddBlogPerformanceIndexes extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding performance indexing to blog_posts table...\n";
        
        try {
            DB::query("ALTER TABLE blog_posts DROP INDEX idx_blog_site_status_deleted_created;");
        } catch (\PDOException $e) {}
        
        DB::query("ALTER TABLE blog_posts ADD INDEX idx_blog_site_status_deleted_created (site_id, status, deleted_at, created_at DESC);");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing performance indexing from blog_posts table...\n";
        
        try {
            DB::query("ALTER TABLE blog_posts DROP INDEX idx_blog_site_status_deleted_created;");
        } catch (\PDOException $e) {}
    }
}
