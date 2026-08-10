<?php
/**
 * File: src/Database/Migrations/0020_CreateSecurityAuditsTable.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

// src/Database/Migrations/0020_CreateSecurityAuditsTable.php

namespace Zero\Database\Migrations;

use Zero\Database\DB;

/**
 * Class CreateSecurityAuditsTable
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class CreateSecurityAuditsTable extends \Zero\Database\Migration
{
    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Dropping security_audits database table...\n";
        DB::query("DROP TABLE IF EXISTS security_audits");
    }

    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Creating security_audits database table...\n";
        DB::query("
            CREATE TABLE IF NOT EXISTS security_audits (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NOT NULL,
                user_id VARCHAR(36) NULL,
                score INT NOT NULL,
                environment VARCHAR(50) NOT NULL,
                telemetry TEXT NOT NULL,
                report LONGTEXT NOT NULL,
                created_at DATETIME,
                updated_at DATETIME NULL,
                deleted_at DATETIME NULL,
                INDEX (site_id),
                INDEX (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}
