<?php
/**
 * File: src/Modules/Queue/Database/Migrations/0023_CreateQueueScheduledTasksTable.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Queue\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Queue\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration;

/**
 * Class CreateQueueScheduledTasksTable
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class CreateQueueScheduledTasksTable extends Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Creating Queue Module Scheduled Tasks Database Table...\n";

        DB::query("
            CREATE TABLE IF NOT EXISTS queue_scheduled_tasks (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NOT NULL,
                task_key VARCHAR(255) NOT NULL,
                payload JSON NOT NULL,
                expression VARCHAR(100) NOT NULL,
                last_run_at DATETIME NULL,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                UNIQUE KEY site_task_unique (site_id, task_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Dropping Queue Module Scheduled Tasks Database Table...\n";
        DB::query("DROP TABLE IF EXISTS queue_scheduled_tasks;");
    }
}
