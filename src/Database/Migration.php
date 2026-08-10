<?php
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
 * Provides structural platform implementation and operational encapsulation.
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
