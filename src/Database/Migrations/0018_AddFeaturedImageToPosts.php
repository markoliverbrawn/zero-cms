<?php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

class AddFeaturedImageToPosts extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Adding featured_image column to blog_posts table...\n";
        
        // Defensively check if column exists first before adding
        if (!DB::hasColumn('blog_posts', 'featured_image')) {
            DB::query("ALTER TABLE blog_posts ADD COLUMN featured_image VARCHAR(36) NULL AFTER status;");
        }
    }

    public function down(): void
    {
        echo "Removing featured_image column from blog_posts table...\n";
        
        if (DB::hasColumn('blog_posts', 'featured_image')) {
            DB::query("ALTER TABLE blog_posts DROP COLUMN featured_image;");
        }
    }
}
