<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0034_WidenSecurityAuditsTelemetryColumn.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration;

/**
 * Class WidenSecurityAuditsTelemetryColumn
 *
 * The raw OSV advisory payloads collected per audit routinely exceed TEXT's 64KB limit, which
 * silently truncated every save under strict SQL mode. Widen telemetry to LONGTEXT to match report.
 */
class WidenSecurityAuditsTelemetryColumn extends Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Widening 'telemetry' column on security_audits to LONGTEXT...\n";
        DB::query("ALTER TABLE security_audits MODIFY COLUMN telemetry LONGTEXT NOT NULL");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Reverting 'telemetry' column on security_audits to TEXT...\n";
        DB::query("ALTER TABLE security_audits MODIFY COLUMN telemetry TEXT NOT NULL");
    }
}
