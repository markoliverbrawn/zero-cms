<?php

namespace Zero\Database;

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
