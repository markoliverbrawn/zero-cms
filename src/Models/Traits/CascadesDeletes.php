<?php

namespace Zero\Models\Traits;

use Zero\Database\DB;

trait CascadesDeletes
{
    /**
     * Recursively cascade soft-deletes to all declared child relationships.
     */
    protected function cascadeDeleteChildren()
    {
        $cascadeList = method_exists($this, 'getCascadeDeletes') ? $this->getCascadeDeletes() : (static::$cascadeDeletes ?? []);
        if (empty($cascadeList) || !is_array($cascadeList)) {
            return;
        }

        foreach ($cascadeList as $childClass => $foreignKey) {
            if (class_exists($childClass)) {
                try {
                    $reflector = new \ReflectionClass($childClass);
                    $prop = $reflector->getProperty('tableName');
                    $prop->setAccessible(true);
                    $tableName = $prop->getValue();

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
        $cascadeList = method_exists($this, 'getCascadeDeletes') ? $this->getCascadeDeletes() : (static::$cascadeDeletes ?? []);
        if (empty($cascadeList) || !is_array($cascadeList)) {
            return;
        }

        foreach ($cascadeList as $childClass => $foreignKey) {
            if (class_exists($childClass)) {
                try {
                    $reflector = new \ReflectionClass($childClass);
                    $prop = $reflector->getProperty('tableName');
                    $prop->setAccessible(true);
                    $tableName = $prop->getValue();

                    $stmt = DB::query("SELECT id FROM {$tableName} WHERE {$foreignKey} = ?", [$this->id]);
                    $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                    foreach ($ids as $id) {
                        $child = $childClass::findTrashed($id);
                        if ($child) {
                            $child->forceDelete();
                        }
                    }
                } catch (\Exception $e) {
                    // Safe fallback if database operations fail during teardowns
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
