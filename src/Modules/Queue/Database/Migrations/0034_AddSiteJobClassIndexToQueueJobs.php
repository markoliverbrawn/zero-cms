<?php

declare(strict_types=1);

/**
 * File: src/Modules/Queue/Database/Migrations/0034_AddSiteJobClassIndexToQueueJobs.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Modules\Queue\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Queue\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration;

/**
 * Class AddSiteJobClassIndexToQueueJobs
 *
 * Adds a composite (site_id, job_class) index to queue_jobs. The admin queue listing search
 * matches job_class with a leading-wildcard LIKE, which can never seek via an index, but this
 * index still lets the tenant-scoped LIKE scan run entirely against the (much smaller) index
 * rather than the full row — including the JSON payload column — cutting the I/O cost of both
 * the COUNT(*) and paginated SELECT queries issued per search.
 */
class AddSiteJobClassIndexToQueueJobs extends Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding site_id/job_class search index to queue_jobs...\n";

        try {
            DB::query("ALTER TABLE queue_jobs DROP INDEX idx_queue_jobs_site_job_class;");
        } catch (\PDOException $e) {}

        DB::query("ALTER TABLE queue_jobs ADD INDEX idx_queue_jobs_site_job_class (site_id, job_class);");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing site_id/job_class search index from queue_jobs...\n";

        try {
            DB::query("ALTER TABLE queue_jobs DROP INDEX idx_queue_jobs_site_job_class;");
        } catch (\PDOException $e) {}
    }
}
