<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/ManagesBlocksAndModels.php
 * Architectural Purpose: The page-builder block type registry, the core model-name registry, and
 * the N+1-avoiding block media eager-loader that depends on the block registry. Extracted out of
 * App.php.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

use Zero\Core\Storage\Storage;
use Zero\Database\DB;
use Zero\Support\Assets;

/**
 * Trait ManagesBlocksAndModels
 */
trait ManagesBlocksAndModels
{
    protected static $registeredBlocks = [];
    protected static $registeredModels = [];
    protected static $registeredCascadeDeletes = [];

    /**
     * Registers a cascade-deletion target class and foreign key dynamically under a parent class.
     * Prevents core parent classes (like Site) from needing hardcoded references to modular children models.
     *
     * @param string $parentClass Fully qualified parent class name (e.g. Zero\Models\Site)
     * @param string $childClass Fully qualified child class name (e.g. Zero\Modules\Shop\Models\Product)
     * @param string $foreignKey Foreign key column name mapping (e.g. site_id)
     * @return void
     */
    public static function registerCascadeDelete(string $parentClass, string $childClass, string $foreignKey): void
    {
        self::$registeredCascadeDeletes[$parentClass][$childClass] = $foreignKey;
    }

    /**
     * Retrieve all dynamically registered child cascade-deletion mappings for a given parent class.
     *
     * @param string $parentClass Fully qualified parent class name.
     * @return array Map of [childClass => foreignKey] registered cascade mappings.
     */
    public static function getCascadeDeletesFor(string $parentClass): array
    {
        return self::$registeredCascadeDeletes[$parentClass] ?? [];
    }

    /**
     * Dynamically and recursively collect and eager-load all media assets referenced inside page builder blocks
     * by scanning for standardized 'media_id' and 'media_ids' fields. Prevents any N+1 query loops!
     * Returns an associative array of [media_id => physical_path].
     *
     * The single batched query deliberately selects more than the path: the extra columns are
     * handed to Assets::prime(), which is what allows Assets::url() to mint focal-point-aware
     * variant URLs during rendering without performing any I/O of its own. The query count is
     * unchanged -- the same one round trip now serves both purposes.
     *
     * @param array $blocks Decoded page-builder block data.
     * @return array Map of [media_id => stored path].
     */
    public static function eagerLoadBlockMedia(array $blocks): array
    {
        $mediaIds = [];
        
        $collectIds = function($data) use (&$collectIds, &$mediaIds) {
            if (!\is_array($data)) {
                return;
            }
            
            foreach ($data as $key => $val) {
                // 'image_path' and 'images' are the legacy spellings of 'media_id'/'media_ids'
                // still present in older block payloads and seeded content. They are collected
                // here so every render path resolves them from the one batched query rather than
                // each theme growing its own collector for the keys it happens to know about.
                if ($key === 'media_id' || $key === 'image_path') {
                    if (\is_string($val) && \strlen($val) === 36 && \strpos($val, '/') === false) {
                        $mediaIds[] = $val;
                    }
                } elseif (($key === 'media_ids' || $key === 'images') && \is_array($val)) {
                    foreach ($val as $v) {
                        if (\is_string($v) && \strlen($v) === 36 && \strpos($v, '/') === false) {
                            $mediaIds[] = $v;
                        }
                    }
                }
                
                // Recursively check child arrays (such as 'items' inside accordion or masonry)
                if (\is_array($val)) {
                    $collectIds($val);
                }
            }
        };

        $collectIds($blocks);
        
        $mediaIdMap = [];
        if (!empty($mediaIds)) {
            $filteredIds = \array_filter(\array_unique($mediaIds));
            if (!empty($filteredIds)) {
                $placeholders = \implode(',', \array_fill(0, \count($filteredIds), '?'));
                $sql = "SELECT id, site_id, path, mime, title, filename, focus_x, focus_y, visibility, created_at, updated_at
                        FROM media
                        WHERE id IN ($placeholders) AND site_id = ? AND deleted_at IS NULL";
                $params = \array_values($filteredIds);
                $params[] = self::getCurrentSiteId();
                $stmt = DB::query($sql, $params);

                $rows = [];
                while ($row = $stmt->fetch()) {
                    $mediaIdMap[$row['id']] = $row['path'];
                    $rows[] = $row;
                }

                Assets::prime($rows);
            }
        }
        return $mediaIdMap;
    }

    /**
     * Retrieves the model class attribute value.
     *
     * @param string $name Argument descriptor.
     * @return string Response output.
     */
    public static function getModelClass(string $name): ?string
    {
        return self::$registeredModels[$name] ?? null;
    }

    /**
     * Retrieves the registered blocks attribute value.
     *
     * @return mixed Response output.
     */
    public static function getRegisteredBlocks(): array
    {
        return self::$registeredBlocks;
    }

    /**
     * Retrieves the registered models attribute value.
     *
     * @return mixed Response output.
     */
    public static function getRegisteredModels(): array
    {
        return self::$registeredModels;
    }

    /**
     * Build the canonical media resolver closure for a set of page-builder blocks.
     *
     * Every block template receives a $resolveMedia callable that turns a media id (or an
     * already-resolved path) into a public URL. This is the single implementation of that
     * contract: it eager-loads every media record the blocks reference in one query, primes the
     * variant URL registry from the same rows, and hands back a closure that only ever reads
     * from the resulting in-memory map -- so a template can resolve a hundred images without
     * issuing a hundred queries.
     *
     * Previously each render path grew its own copy of this closure, and they had already
     * drifted apart in what they returned; funnelling them all through here removes both the
     * duplication and the drift.
     *
     * @param array $blocks Decoded page-builder block data.
     * @return callable A closure accepting a media id or path and returning a public URL.
     */
    public static function mediaResolver(array $blocks): callable
    {
        $mediaIdMap = self::eagerLoadBlockMedia($blocks);

        return function ($idOrPath) use ($mediaIdMap) {
            if (empty($idOrPath)) {
                return '';
            }

            // An already-absolute path is passed straight through the storage driver, which is
            // what lets a template reference a file that has no media record behind it.
            $path = \strpos($idOrPath, '/') === 0 ? $idOrPath : ($mediaIdMap[$idOrPath] ?? '');
            if (empty($path)) {
                return '';
            }

            return Storage::getUrl($path);
        };
    }

    /**
     * Registers the block component definition dynamically.
     *
     * @param string $type Argument descriptor.
     * @param array $config Argument descriptor.
     * @return mixed Response output.
     */
    public static function registerBlock(string $type, array $config)
    {
        self::$registeredBlocks[$type] = $config;
    }

    /**
     * Registers the model component definition dynamically.
     *
     * @param string $name Argument descriptor.
     * @param string $class Argument descriptor.
     * @return mixed Response output.
     */
    public static function registerModel(string $name, string $class)
    {
        self::$registeredModels[$name] = $class;
    }

}
