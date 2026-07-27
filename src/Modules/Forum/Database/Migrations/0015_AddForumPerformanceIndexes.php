<?php

namespace Zero\Modules\Forum\Database\Migrations;

use Zero\Database\DB;

class AddForumPerformanceIndexes extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Adding performance indexing to forum boards, threads, and posts tables...\n";
        
        try {
            DB::query("ALTER TABLE forum_boards DROP INDEX idx_forum_boards_site_deleted_precedence;");
        } catch (\PDOException $e) {}
        try {
            DB::query("ALTER TABLE forum_threads DROP INDEX idx_forum_threads_site_board_deleted_status;");
        } catch (\PDOException $e) {}
        try {
            DB::query("ALTER TABLE forum_posts DROP INDEX idx_forum_posts_site_thread_parent_deleted_status;");
        } catch (\PDOException $e) {}
        
        DB::query("ALTER TABLE forum_boards ADD INDEX idx_forum_boards_site_deleted_precedence (site_id, deleted_at, precedence ASC);");
        DB::query("ALTER TABLE forum_threads ADD INDEX idx_forum_threads_site_board_deleted_status (site_id, board_id, deleted_at, status);");
        DB::query("ALTER TABLE forum_posts ADD INDEX idx_forum_posts_site_thread_parent_deleted_status (site_id, thread_id, parent_id, deleted_at, status);");
    }

    public function down(): void
    {
        echo "Removing performance indexing from forum boards, threads, and posts tables...\n";
        
        try {
            DB::query("ALTER TABLE forum_boards DROP INDEX idx_forum_boards_site_deleted_precedence;");
        } catch (\PDOException $e) {}
        try {
            DB::query("ALTER TABLE forum_threads DROP INDEX idx_forum_threads_site_board_deleted_status;");
        } catch (\PDOException $e) {}
        try {
            DB::query("ALTER TABLE forum_posts DROP INDEX idx_forum_posts_site_thread_parent_deleted_status;");
        } catch (\PDOException $e) {}
    }
}
