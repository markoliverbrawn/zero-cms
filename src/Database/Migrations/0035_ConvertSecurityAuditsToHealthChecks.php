<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0035_ConvertSecurityAuditsToHealthChecks.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration;

/**
 * Class ConvertSecurityAuditsToHealthChecks
 *
 * The security audit's 0-100 score and telemetry blob are replaced by deployment health checks:
 * each run stores an overall status and its per-check results as JSON. Existing rows are kept,
 * marked status 'legacy', with their Markdown report still readable.
 */
class ConvertSecurityAuditsToHealthChecks extends Migration
{
    /**
     * Restore the score/telemetry columns. Health check rows have no score, so they get 0.
     *
     * @return void
     */
    public function down(): void
    {
        echo "Restoring score/telemetry columns on security_audits...\n";
        DB::query("ALTER TABLE security_audits ADD COLUMN score INT NOT NULL DEFAULT 0 AFTER user_id, ADD COLUMN telemetry LONGTEXT NULL AFTER environment");
        DB::query("UPDATE security_audits SET telemetry = COALESCE(results, ''), report = COALESCE(report, '')");
        DB::query("ALTER TABLE security_audits MODIFY COLUMN telemetry LONGTEXT NOT NULL, MODIFY COLUMN report LONGTEXT NOT NULL, DROP COLUMN status, DROP COLUMN results");
    }

    /**
     * Add status/results, make report optional, and drop score/telemetry.
     *
     * @return void
     */
    public function up(): void
    {
        echo "Converting security_audits to health check runs...\n";
        DB::query("ALTER TABLE security_audits ADD COLUMN status VARCHAR(10) NOT NULL DEFAULT 'legacy' AFTER user_id, ADD COLUMN results LONGTEXT NULL AFTER environment, MODIFY COLUMN report LONGTEXT NULL");
        DB::query("ALTER TABLE security_audits DROP COLUMN score, DROP COLUMN telemetry");
    }
}
