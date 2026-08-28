<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/ManagesModelColumnRenderers.php
 * Architectural Purpose: Registry letting a module override how a specific model's admin-list
 * column renders, purely via a registration call from its own Module::init() -- no editing of
 * core admin templates, and no need to own (or fork) the target model's getConfig() method. This
 * is what makes a sub-project's own module able to customize a CORE model's (Page, Site, User,
 * ...) column display while staying entirely separate from core.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

/**
 * Trait ManagesModelColumnRenderers
 */
trait ManagesModelColumnRenderers
{
    protected static $modelColumnRenderers = [];

    /**
     * Register (or override) the renderer for one field/column on a model's admin listing page.
     * Last registration for a given model+field pair wins, matching registerModelPermission()'s
     * plain-overwrite semantics -- there is exactly one active renderer per column, not a stacked
     * list, so a sub-project's own module can deliberately replace a default registered elsewhere.
     *
     * @param string $modelName The registered model name (e.g. 'pages').
     * @param string $field The column/field name as it appears in the model's getConfig().
     * @param callable $renderer Receives ($value, $record) and returns an HTML string. Responsible
     *     for escaping any dynamic data it embeds -- like a 'listView' template, its return value
     *     is trusted output, echoed as-is.
     * @return void
     */
    public static function registerModelColumnRenderer(string $modelName, string $field, callable $renderer): void
    {
        self::$modelColumnRenderers[$modelName][$field] = $renderer;
    }

    /**
     * Look up a registered column renderer for a model+field pair, or null if none was
     * registered (the caller falls back to the field's own 'renderWith'/'listView' config, or the
     * generic default rendering).
     *
     * @param string $modelName
     * @param string $field
     * @return callable|null
     */
    public static function getModelColumnRenderer(string $modelName, string $field): ?callable
    {
        return self::$modelColumnRenderers[$modelName][$field] ?? null;
    }
}
