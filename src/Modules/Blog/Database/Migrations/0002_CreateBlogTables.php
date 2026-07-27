<?php

namespace Zero\Modules\Blog\Database\Migrations;

use Zero\Database\DB;

class CreateBlogTables extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Creating Blog Module Database Tables...\n";

        DB::query("
            CREATE TABLE IF NOT EXISTS blog_posts (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                content TEXT,
                type VARCHAR(50) NULL,
                status VARCHAR(20) DEFAULT 'draft',
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                UNIQUE KEY site_slug_unique (site_id, slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function down(): void
    {
        echo "Dropping Blog Module Database Tables...\n";
        DB::query("DROP TABLE IF EXISTS blog_posts;");
    }
}
