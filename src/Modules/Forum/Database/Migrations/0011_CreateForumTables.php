<?php

namespace Zero\Modules\Forum\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration as BaseMigration;

class CreateForumTables extends BaseMigration
{
    public function down(): void
    {
        echo "Dropping Forum Module Database Tables...\n";

        DB::query("DROP TABLE IF EXISTS forum_posts;");
        DB::query("DROP TABLE IF EXISTS forum_threads;");
        DB::query("DROP TABLE IF EXISTS forum_boards;");
    }

    public function up(): void
    {
        echo "Creating Forum Module Database Tables...\n";

        DB::query("
            CREATE TABLE IF NOT EXISTS forum_boards (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NOT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT NULL,
                precedence INT NOT NULL DEFAULT 0,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                UNIQUE KEY site_board_slug_unique (site_id, slug),
                INDEX (site_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        DB::query("
            CREATE TABLE IF NOT EXISTS forum_threads (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NOT NULL,
                board_id VARCHAR(36) NOT NULL,
                user_id VARCHAR(36) NOT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'published', -- published, locked, pinned
                views_count INT NOT NULL DEFAULT 0,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                UNIQUE KEY site_thread_slug_unique (site_id, slug),
                INDEX (site_id),
                INDEX (board_id),
                INDEX (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        DB::query("
            CREATE TABLE IF NOT EXISTS forum_posts (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NOT NULL,
                thread_id VARCHAR(36) NOT NULL,
                user_id VARCHAR(36) NOT NULL,
                content TEXT NOT NULL,
                parent_id VARCHAR(36) NULL, -- For nesting/threading replies
                status VARCHAR(50) NOT NULL DEFAULT 'approved', -- approved, pending, flagged
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                INDEX (site_id),
                INDEX (thread_id),
                INDEX (user_id),
                INDEX (parent_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}
