<?php

declare(strict_types=1);

/**
 * File: src/Models/Traits/CascadesDeletes.php
 * Architectural Purpose: Active Record data model or behavioral trait wrapping database schema representation with tenant-scoping.
 * Package: Zero\Models\Traits
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Models\Traits;

use Zero\Database\DB;
use Zero\Support\Security;

/**
 * Trait CascadesDeletes
 *
 * Defines systemic behavioral interface contract mechanisms.
 */
trait CascadesDeletes
{
    /**
     * Recursively cascade soft-deletes to all declared child relationships.
     */
    protected function cascadeDeleteChildren()
    {
        $cascadeList = \method_exists($this, 'getCascadeDeletes') ? $this->getCascadeDeletes() : (static::$cascadeDeletes ?? []);
        if (empty($cascadeList) || !\is_array($cascadeList)) {
            return;
        }

        foreach ($cascadeList as $childClass => $foreignKey) {
            if (\class_exists($childClass)) {
                try {
                    $reflector = new \ReflectionClass($childClass);
                    $prop = $reflector->getProperty('tableName');
                    $prop->setAccessible(true);
                    $tableName = $prop->getValue();

                    // Defense-in-depth: $tableName/$foreignKey come from hardcoded $cascadeDeletes
                    // class metadata, never from request input, but table/column identifiers can't
                    // be bound via PDO placeholders -- so validate both are plain SQL identifiers
                    // AND that they match the live schema's actual table/column allow-list before
                    // interpolating them.
                    if (
                        !Security::isSafeSqlIdentifier($tableName) || !Security::isSafeSqlIdentifier($foreignKey)
                        || !Security::isKnownSqlTable($tableName) || !Security::isKnownSqlColumn($tableName, $foreignKey)
                    ) {
                        continue;
                    }

                    $stmt = DB::query("SELECT id FROM {$tableName} WHERE {$foreignKey} = ? AND deleted_at IS NULL", [$this->id]);
                    $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                    foreach ($ids as $id) {
                        $child = $childClass::find($id);
                        if ($child) {
                            $child->delete();
                        }
                    }
                } catch (\Exception $e) {
                    // Safe fallback if database operations fail during soft deletions
                }
            }
        }
    }

    /**
     * Recursively cascade force-deletes to all declared child relationships (including already soft-deleted ones).
     */
    protected function cascadeForceDeleteChildren()
    {
        $cascadeList = \method_exists($this, 'getCascadeDeletes') ? $this->getCascadeDeletes() : (static::$cascadeDeletes ?? []);
        if (empty($cascadeList) || !\is_array($cascadeList)) {
            return;
        }

        foreach ($cascadeList as $childClass => $foreignKey) {
            if (\class_exists($childClass)) {
                try {
                    $reflector = new \ReflectionClass($childClass);
                    $prop = $reflector->getProperty('tableName');
                    $prop->setAccessible(true);
                    $tableName = $prop->getValue();

                    // Defense-in-depth: $tableName/$foreignKey come from hardcoded $cascadeDeletes
                    // class metadata, never from request input, but table/column identifiers can't
                    // be bound via PDO placeholders -- so validate both are plain SQL identifiers
                    // AND that they match the live schema's actual table/column allow-list before
                    // interpolating them.
                    if (
                        !Security::isSafeSqlIdentifier($tableName) || !Security::isSafeSqlIdentifier($foreignKey)
                        || !Security::isKnownSqlTable($tableName) || !Security::isKnownSqlColumn($tableName, $foreignKey)
                    ) {
                        continue;
                    }

                    $stmt = DB::query("SELECT id FROM {$tableName} WHERE {$foreignKey} = ?", [$this->id]);
                    $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                    foreach ($ids as $id) {
                        $child = $childClass::findTrashed($id);
                        if ($child) {
                            $child->forceDelete();
                        }
                    }
                } catch (\Exception $e) {
                    // Rethrow descriptive file deletion or other failures to bubble out to the controller/user
                    throw $e;
                }
            }
        }
    }

    /**
     * Override standard delete to trigger cascaded soft deletions on declared child models first.
     */
    public function delete()
    {
        $this->cascadeDeleteChildren();
        return $this->traitDelete();
    }

    /**
     * Override standard forceDelete to trigger cascaded permanent deletions on declared child models first.
     */
    public function forceDelete()
    {
        $this->cascadeForceDeleteChildren();
        return $this->traitForceDelete();
    }
}
