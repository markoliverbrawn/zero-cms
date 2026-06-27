<?php

namespace Zero\Modules\Queue\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration;

class CreateQueueJobsTable extends Migration
{
    public function up(): void
    {
        echo "Creating Queue Module Database Tables...\n";

        DB::query("
            CREATE TABLE IF NOT EXISTS queue_jobs (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NOT NULL,
                job_class VARCHAR(255) NOT NULL,
                payload JSON NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                attempts INT DEFAULT 0,
                reserved_at DATETIME NULL,
                failed_at DATETIME NULL,
                error_message TEXT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                INDEX (site_id),
                INDEX (status, reserved_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function down(): void
    {
        echo "Dropping Queue Module Database Tables...\n";
        DB::query("DROP TABLE IF EXISTS queue_jobs;");
    }
}
