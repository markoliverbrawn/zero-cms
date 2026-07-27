<?php

namespace Zero\Modules\FormBuilder\Database\Migrations;

use Zero\Database\DB;

class CreateFormBuilderTables extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Creating Form Builder Module Database Tables...\n";

        // Form Builder Submissions (saves dynamic custom form inputs as JSON payload)
        DB::query("
            CREATE TABLE IF NOT EXISTS form_submissions (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NOT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(50) NULL,
                message TEXT NOT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                INDEX (site_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function down(): void
    {
        echo "Dropping Form Builder Module Database Tables...\n";
        DB::query("DROP TABLE IF EXISTS form_submissions;");
    }
}
