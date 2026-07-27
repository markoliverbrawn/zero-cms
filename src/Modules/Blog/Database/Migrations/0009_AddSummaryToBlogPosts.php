<?php

namespace Zero\Modules\Blog\Database\Migrations;

use Zero\Database\DB;

class AddSummaryToBlogPosts extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Adding summary column to blog_posts...\n";
        DB::query("ALTER TABLE blog_posts ADD COLUMN summary TEXT NULL AFTER title;");
    }

    public function down(): void
    {
        echo "Removing summary column from blog_posts...\n";
        DB::query("ALTER TABLE blog_posts DROP COLUMN summary;");
    }
}
