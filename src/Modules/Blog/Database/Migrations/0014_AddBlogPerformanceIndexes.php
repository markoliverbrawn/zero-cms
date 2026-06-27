<?php

namespace Zero\Modules\Blog\Database\Migrations;

use Zero\Database\DB;

class AddBlogPerformanceIndexes extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Adding performance indexing to blog_posts table...\n";
        
        try {
            DB::query("ALTER TABLE blog_posts DROP INDEX idx_blog_site_status_deleted_created;");
        } catch (\PDOException $e) {}
        
        DB::query("ALTER TABLE blog_posts ADD INDEX idx_blog_site_status_deleted_created (site_id, status, deleted_at, created_at DESC);");
    }

    public function down(): void
    {
        echo "Removing performance indexing from blog_posts table...\n";
        
        try {
            DB::query("ALTER TABLE blog_posts DROP INDEX idx_blog_site_status_deleted_created;");
        } catch (\PDOException $e) {}
    }
}
