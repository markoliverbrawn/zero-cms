<?php

namespace Zero\Models\Traits;

use Zero\Database\DB;
use Zero\Core\App;

trait IsOrderable
{
    public static function isOrderable(): bool
    {
        return true;
    }

    /**
     * Reorders the records by setting the precedence of each ID in the provided order.
     * Starts at 10 and increments by 10 for each record.
     *
     * @param array $ids Array of record IDs in the new order.
     * @return bool True on success.
     */
    public static function reorder(array $ids): bool
    {
        $tableName = static::$tableName;
        $siteId = App::getCurrentSiteId();

        $precedence = 10;
        foreach ($ids as $id) {
            $id = trim($id);
            if (empty($id)) continue;

            // Scope by site_id for tenant isolation (except sites table)
            if ($tableName === 'sites') {
                DB::query("UPDATE {$tableName} SET precedence = ?, updated_at = NOW() WHERE id = ?", [$precedence, $id]);
            } else {
                DB::query("UPDATE {$tableName} SET precedence = ?, updated_at = NOW() WHERE id = ? AND site_id = ?", [$precedence, $id, $siteId]);
            }

            // Clear globally centralized DB identity map cache for this record so
            // subsequent Page::find() queries fetch the updated precedence from the database.
            DB::setIdentity($tableName, $id, null);

            $precedence += 10;
        }

        return true;
    }
}
