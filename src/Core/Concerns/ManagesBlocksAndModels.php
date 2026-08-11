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

use Zero\Database\DB;

/**
 * Trait ManagesBlocksAndModels
 */
trait ManagesBlocksAndModels
{
    protected static $registeredBlocks = [];
    protected static $registeredModels = [];

    /**
     * Dynamically and recursively collect and eager-load all media assets referenced inside page builder blocks
     * by scanning for standardized 'media_id' and 'media_ids' fields. Prevents any N+1 query loops!
     * Returns an associative array of [media_id => physical_path].
     */
    public static function eagerLoadBlockMedia(array $blocks): array
    {
        $mediaIds = [];
        
        $collectIds = function($data) use (&$collectIds, &$mediaIds) {
            if (!\is_array($data)) {
                return;
            }
            
            foreach ($data as $key => $val) {
                if ($key === 'media_id') {
                    if (\is_string($val) && \strlen($val) === 36 && \strpos($val, '/') === false) {
                        $mediaIds[] = $val;
                    }
                } elseif ($key === 'media_ids' && \is_array($val)) {
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
                $sql = "SELECT id, path FROM media WHERE id IN ($placeholders) AND deleted_at IS NULL";
                $stmt = DB::query($sql, \array_values($filteredIds));
                while ($row = $stmt->fetch()) {
                    $mediaIdMap[$row['id']] = $row['path'];
                }
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
