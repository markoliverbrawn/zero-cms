<?php

declare(strict_types=1);

/**
 * File: src/Database/Migration.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database;

/**
 * Class Migration
 *
 * Base class every migration extends, fixing the two-method contract the migration runner drives:
 * up() to apply a schema change and down() to revert it.
 */
abstract class Migration
{
    /**
     * Revert the database migrations (Drop tables, remove columns, etc.).
     */
    abstract public function down(): void;
/**
     * Run the database migrations (Create tables, add columns, etc.).
     */
    abstract public function up(): void;

    }
