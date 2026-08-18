<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0001_CreateCoreTables.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Database\DB;
use Zero\Database\Migration;

/**
 * Class CreateCoreTables
 *
 * Creates the engine's foundational tables: sites, users, pages, media, password_resets, and
 * audit_logs.
 */
class CreateCoreTables extends Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Creating Core CMS Database Tables...\n";

        DB::query("
            CREATE TABLE IF NOT EXISTS sites (
                id VARCHAR(36) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                domain VARCHAR(255) NOT NULL UNIQUE,
                theme VARCHAR(100) NOT NULL,
                enabled_modules TEXT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        DB::query("
            CREATE TABLE IF NOT EXISTS users (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NULL,
                username VARCHAR(255) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'editor',
                api_token VARCHAR(255) NULL,
                preferences TEXT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                INDEX (api_token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        DB::query("
            CREATE TABLE IF NOT EXISTS pages (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                content TEXT,
                type VARCHAR(50) NULL,
                controller VARCHAR(255) NULL,
                view VARCHAR(255) NULL,
                status VARCHAR(20) DEFAULT 'draft',
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL,
                UNIQUE KEY site_slug_unique (site_id, slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        DB::query("
            CREATE TABLE IF NOT EXISTS media (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NULL,
                filename VARCHAR(255) NOT NULL,
                path VARCHAR(255) NOT NULL,
                mime VARCHAR(255) NOT NULL,
                folder VARCHAR(255) NOT NULL DEFAULT '',
                created_at DATETIME,
                updated_at DATETIME,
                deleted_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        DB::query("
            CREATE TABLE IF NOT EXISTS password_resets (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NULL,
                user_id VARCHAR(36) NOT NULL,
                token VARCHAR(128) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                INDEX (site_id),
                INDEX (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        DB::query("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id VARCHAR(36) PRIMARY KEY,
                site_id VARCHAR(36) NULL,
                user_id VARCHAR(36) NULL,
                action VARCHAR(100) NOT NULL,
                object_type VARCHAR(100) NULL,
                object_id VARCHAR(100) NULL,
                meta JSON NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                deleted_at DATETIME NULL,
                INDEX (site_id),
                INDEX (user_id)
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
        echo "Dropping Core CMS Database Tables...\n";
        DB::query("DROP TABLE IF EXISTS audit_logs, password_resets, media, pages, users, sites;");
    }
}
