<?php

declare(strict_types=1);

/**
 * File: src/Models/Traits/HasSlug.php
 * Architectural Purpose: Active Record data model or behavioral trait wrapping database schema representation with tenant-scoping.
 * Package: Zero\Models\Traits
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Models\Traits;

use Zero\Core\App;
use Zero\Database\DB;

/**
 * Trait HasSlug
 *
 * Defines systemic behavioral interface contract mechanisms.
 */
trait HasSlug
{
    public $slug;

    // This method will be used by controllers to get config for views
    /**
     * Find by slug processing implementation helper.
     *
     * @param mixed $slug Argument descriptor.
     * @return mixed Response output.
     */
    public static function findBySlug($slug)
    {
        $sql = "SELECT * FROM " . static::$tableName . " WHERE slug = ?";
        $params = [$slug];

        // Assume site_id is inherently present on all tables except 'sites'
        if (static::$tableName !== 'sites') {
            $sql .= " AND site_id = ?";
            $params[] = App::getCurrentSiteId();
        }

        // Restrict guests to 'published' items if the model specifies it
        $isAdmin = isset($_SESSION['user_id']);
        $restrictGuests = static::$restrictGuests ?? false;
        if ($restrictGuests && !$isAdmin && DB::hasColumn(static::$tableName, 'status')) {
            $sql .= " AND status = 'published'";
        }

        // Enforce polymorphic type restrictions if configured and column exists
        $modelType = \property_exists(static::class, 'modelType') ? static::$modelType : null;
        if ($modelType !== null && DB::hasColumn(static::$tableName, 'type')) {
            $sql .= " AND `type` = ?";
            $params[] = $modelType;
        }
        $sql .= " LIMIT 1";

        $stmt = DB::query($sql, $params);
        $data = $stmt->fetch();
        if ($data) {
            return new static($data);
        }
        return null;
    }
}
