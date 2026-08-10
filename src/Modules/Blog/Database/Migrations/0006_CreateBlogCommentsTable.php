<?php
/**
 * File: src/Modules/Blog/Database/Migrations/0006_CreateBlogCommentsTable.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Blog\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Blog\Database\Migrations;

use Zero\Database\DB;

/**
 * Class CreateBlogCommentsTable
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class CreateBlogCommentsTable extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Creating Blog Comments Database Tables...\n";

        DB::query("
            CREATE TABLE IF NOT EXISTS blog_comments (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NULL,
                post_id VARCHAR(36) NOT NULL,
                author_name VARCHAR(255) NOT NULL,
                author_email VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                status VARCHAR(50) DEFAULT 'approved',
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                INDEX (site_id),
                INDEX (post_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Dropping Blog Comments Database Tables...\n";
        DB::query("DROP TABLE IF EXISTS blog_comments;");
    }
}
