<?php

namespace Zero\Modules\Search\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration;

class CreateSearchIndexTable extends Migration
{
    /**
     * Rollback this sequential migration.
     */
    public function down(): void
    {
        echo "Dropping search_index table...\n";
        DB::query("DROP TABLE IF EXISTS search_index;");
    }

    /**
     * Run this sequential migration.
     */
    public function up(): void
    {
        echo "Creating search_index table...\n";
        
        DB::query("
            CREATE TABLE IF NOT EXISTS search_index (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NOT NULL,
                model_type VARCHAR(100) NOT NULL,
                model_id VARCHAR(36) NOT NULL,
                title VARCHAR(255) NOT NULL,
                content LONGTEXT NULL,
                url VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY model_unique (model_type, model_id),
                INDEX site_id_index (site_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}
