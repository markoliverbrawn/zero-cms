<?php

declare(strict_types=1);

/**
 * File: src/Models/Traits/IsModel.php
 * Architectural Purpose: Active Record data model or behavioral trait wrapping database schema representation with tenant-scoping.
 * Package: Zero\Models\Traits
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Models\Traits;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\Security;

/**
 * Trait IsModel
 *
 * Defines systemic behavioral interface contract mechanisms.
 */
trait IsModel
{
    use Paginates;

    public $id;
    
    /**
     * Constructs an active record model instance, hydrating its properties.
     *
     * @param mixed $data Argument descriptor.
     * @return mixed Response output.
     */
    public function __construct($data = [])
    {
        foreach ($data as $key => $value) {
            if (\property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Magic getter method to dynamically access hydrated database attributes.
     *
     * @param mixed $name Argument descriptor.
     * @return mixed Response output.
     */
    public function __get($name)
    {
        if (\str_ends_with($name, '_local')) {
            $baseField = \substr($name, 0, -6);
            if (\property_exists($this, $baseField)) {
                $val = $this->$baseField;
                if (!empty($val)) {
                    return \Zero\Support\I18n::localizeDateTime($val);
                }
            }
        }
        return null;
    }

    /**
     * Magic isset method to check if a dynamic database attribute is populated.
     *
     * @param mixed $name Argument descriptor.
     * @return mixed Response output.
     */
    public function __isset($name)
    {
        if (\str_ends_with($name, '_local')) {
            $baseField = \substr($name, 0, -6);
            if (\property_exists($this, $baseField)) {
                return !empty($this->$baseField);
            }
        }
        return isset($this->$name);
    }

    /**
     * Fetches all active model records, automatically applying multi-tenant isolation filters.
     *
     * @return mixed Response output.
     */
    public static function all()
    {
        $sql = "SELECT * FROM " . static::$tableName;
        $params = [];
        
        $where = ["deleted_at IS NULL"];
        if (static::$tableName !== 'sites') {
            $where[] = "site_id = ?";
            $params[] = App::getCurrentSiteId();
        }

        $sql .= " WHERE " . \implode(' AND ', $where);

        $stmt = DB::query($sql, $params);
        $results = [];
        while ($data = $stmt->fetch()) {
            $results[] = new static($data);
        }
        return $results;
    }

    /**
     * Creates and persists a new model record in the database.
     *
     * @return mixed Response output.
     */
    protected function create()
    {
        if (empty($this->id)) {
            $this->id = Security::uuidv7();
        }

        $fields = ['id'];
        $placeholders = ['?'];
        $values = [$this->id];

        foreach (static::$fillable as $field) {
            if (isset($this->$field)) {
                $fields[] = $field;
                $placeholders[] = '?';
                $values[] = $this->$field;
            }
        }

        $fields[] = 'created_at';
        $placeholders[] = '?';
        $values[] = \gmdate('Y-m-d H:i:s');
        $fields[] = 'updated_at';
        $placeholders[] = '?';
        $values[] = \gmdate('Y-m-d H:i:s');

        // Add site_id if table has site_id column and it is not explicitly in fillable
        if (static::$tableName !== 'sites' && !\in_array('site_id', $fields)) {
            $fields[] = 'site_id';
            $placeholders[] = '?';
            $values[] = $this->site_id ?? App::getCurrentSiteId();
        }

        // Add type if static::$modelType is set and the table is a polymorphic table (pages, blog_posts)
        $modelType = \property_exists(static::class, 'modelType') ? static::$modelType : null;
        if ($modelType !== null && !\in_array('type', $fields) && (static::$tableName === 'pages' || static::$tableName === 'blog_posts')) {
            $fields[] = 'type';
            $placeholders[] = '?';
            $values[] = $modelType;
        }

        $sql = "INSERT INTO " . static::$tableName . " (" . \implode(', ', $fields) . ") VALUES (" . \implode(', ', $placeholders) . ")";
        DB::query($sql, $values);

        // Clear the globally centralized DB identity map cache for this newly created record (force fresh lookup for full hydration)
        DB::setIdentity(static::$tableName, $this->id, null);

        // Synchronize with search index if searchable
        if (\method_exists($this, 'indexInSearch')) {
            $this->indexInSearch();
        }

        return $this->id;
    }

    /**
     * Delete processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function delete()
    {
        DB::query("UPDATE " . static::$tableName . " SET deleted_at = ? WHERE id = ?", [\gmdate('Y-m-d H:i:s'), $this->id]);
        
        // Clear the globally centralized DB identity map cache for this record
        DB::setIdentity(static::$tableName, $this->id, null);

        // Remove from search index if searchable
        if (\method_exists($this, 'removeFromSearch')) {
            $this->removeFromSearch();
        }

        return true;
    }

    /**
     * Find processing implementation helper.
     *
     * @param mixed $id Argument descriptor.
     * @return mixed Response output.
     */
    public static function find($id)
    {
        if ($id === null || $id === '') {
            return null;
        }
        $id = (string)$id;
        $tableName = static::$tableName;
        
        // Read from globally centralized DB identity map cache
        $cached = DB::getIdentity($tableName, $id);
        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        $stmt = DB::query("SELECT * FROM " . $tableName . " WHERE id = ? AND deleted_at IS NULL", [$id]);
        $data = $stmt->fetch();
        if ($data) {
            $record = new static($data);
            DB::setIdentity($tableName, $id, $record);
            return $record;
        }

        // Negative Caching: Cache the non-existence of this record (store false)
        DB::setIdentity($tableName, $id, false);
        return null;
    }

    /**
     * Find trashed processing implementation helper.
     *
     * @param mixed $id Argument descriptor.
     * @return mixed Response output.
     */
    public static function findTrashed($id)
    {
        $tableName = static::$tableName;
        $stmt = DB::query("SELECT * FROM " . $tableName . " WHERE id = ?", [$id]);
        $data = $stmt->fetch();
        if ($data) {
            return new static($data);
        }
        return null;
    }

    /**
     * Force delete processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function forceDelete()
    {
        DB::query("DELETE FROM " . static::$tableName . " WHERE id = ?", [$this->id]);
        
        // Clear the globally centralized DB identity map cache for this record
        DB::setIdentity(static::$tableName, $this->id, null);

        // Remove from search index if searchable
        if (\method_exists($this, 'removeFromSearch')) {
            $this->removeFromSearch();
        }

        return true;
    }

    /**
     * Retrieves the table name attribute value.
     *
     * @return string Response output.
     */
    public static function getTableName(): string
    {
        return static::$tableName;
    }

    /**
     * Restore processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function restore()
    {
        DB::query("UPDATE " . static::$tableName . " SET deleted_at = NULL, updated_at = ? WHERE id = ?", [\gmdate('Y-m-d H:i:s'), $this->id]);
        
        // Clear the globally centralized DB identity map cache for this record
        DB::setIdentity(static::$tableName, $this->id, null);

        // Synchronize with search index if searchable
        if (\method_exists($this, 'indexInSearch')) {
            $this->indexInSearch();
        }

        return true;
    }

    /**
     * Save processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function save()
    {
        if ($this->id) {
            // Verify if this pre-populated UUIDv7 record already exists in the database
            $exists = static::find($this->id);
            if ($exists) {
                return $this->update();
            }
        }
        return $this->create();
    }

    /**
     * Update processing implementation helper.
     *
     * @return mixed Response output.
     */
    protected function update()
    {
        $set = [];
        $values = [];

        foreach (static::$fillable as $field) {
            if (isset($this->$field)) {
                $set[] = "$field = ?";
                $values[] = $this->$field;
            }
        }

        $set[] = 'updated_at = ?';
        $values[] = \gmdate('Y-m-d H:i:s');
        $values[] = $this->id; // for the WHERE clause

        $sql = "UPDATE " . static::$tableName . " SET " . \implode(', ', $set) . " WHERE id = ?";
        DB::query($sql, $values);

        // Clear the globally centralized DB identity map cache for this record
        DB::setIdentity(static::$tableName, $this->id, null);

        // Synchronize with search index if searchable
        if (\method_exists($this, 'indexInSearch')) {
            $this->indexInSearch();
        }

        return $this->id;
    }

    /**
     * Retrieve matching model records by custom column and value.
     */
    public static function where(string $column, $value, string $options = ''): array
    {
        $tableName = static::$tableName;
        $sql = "SELECT * FROM {$tableName} WHERE {$column} = ?";
        $params = [$value];

        // Automatically inject multi-tenant site-isolation constraints!
        if ($tableName !== 'sites') {
            if ($tableName === 'users') {
                $sql .= " AND (site_id = ? OR site_id IS NULL)";
            } else {
                $sql .= " AND site_id = ?";
            }
            $params[] = App::getCurrentSiteId();
        }

        $sql .= " AND deleted_at IS NULL";

        if (!empty($options)) {
            $sql .= " " . $options;
        }

        $stmt = DB::query($sql, $params);
        $results = [];
        while ($data = $stmt->fetch()) {
            $results[] = new static($data);
        }
        return $results;
    }
}
