<?php

namespace Zero\Modules\Blog\Database\Migrations;

use Zero\Database\DB;

class CreateBlogCommentsTable extends \Zero\Database\Migration
{
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

    public function down(): void
    {
        echo "Dropping Blog Comments Database Tables...\n";
        DB::query("DROP TABLE IF EXISTS blog_comments;");
    }
}
