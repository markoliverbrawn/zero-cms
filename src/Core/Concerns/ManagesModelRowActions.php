<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/ManagesModelRowActions.php
 * Architectural Purpose: Registry for extra per-row actions a module can contribute to a specific
 * model's admin listing page (e.g. an extra item in each row's actions menu), sharing the same
 * module-dependency/permission visibility gating already used by ManagesModelListActions.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

/**
 * Trait ManagesModelRowActions
 */
trait ManagesModelRowActions
{
    protected static $modelRowActions = [];

    /**
     * Get every registered row action for a model, sorted by precedence. Visibility is NOT
     * filtered here -- callers should check each action against isModelRowActionVisible() before
     * rendering, mirroring getModelListActions()/isModelListActionVisible().
     *
     * @param string $modelName
     * @return array
     */
    public static function getModelRowActions(string $modelName): array
    {
        $actions = self::$modelRowActions[$modelName] ?? [];

        \usort($actions, function ($a, $b) {
            return ($a['precedence'] ?? 100) <=> ($b['precedence'] ?? 100);
        });

        return $actions;
    }

    /**
     * Helper to resolve if a registered row action is visible to the current logged-in user under
     * the active tenant site. Deliberately does NOT reuse isSidebarItemVisible()'s super_admin
     * bypass on module_dependency, for the same reason isModelListActionVisible() doesn't: a row
     * action gated to a module should only ever appear when that module is genuinely active for
     * the current site.
     *
     * @param array $action
     * @param \Zero\Models\Site|null $site
     * @return bool
     */
    public static function isModelRowActionVisible(array $action, ?\Zero\Models\Site $site): bool
    {
        if (!empty($action['permission']) && !self::authorize($action['permission'])) {
            return false;
        }

        if (!empty($action['module_dependency'])) {
            return $site !== null && $site->isModuleEnabled($action['module_dependency']);
        }

        return true;
    }

    /**
     * Register an extra action for a model's admin listing page, appended to each row's actions
     * menu alongside the built-in Edit/Delete items.
     *
     * @param string $modelName The registered model name (e.g. 'pages').
     * @param array $actionConfig {
     *     @var string $label Menu item text.
     *     @var string $url Target URL. May contain a literal '{id}' placeholder, which is replaced
     *         with the row's record id before rendering.
     *     @var string $method 'get' renders a plain link; 'post' renders a confirm+fetch menu item.
     *     @var string $confirm Confirmation message shown before a 'post' action runs.
     *     @var string|null $module_dependency Module ID gating visibility, mirroring list actions.
     *     @var string|null $permission RBAC permission key required to see this action.
     *     @var int $precedence
     * }
     * @return void
     */
    public static function registerModelRowAction(string $modelName, array $actionConfig): void
    {
        self::$modelRowActions[$modelName][] = \array_merge([
            'label' => '',
            'url' => '',
            'method' => 'get',
            'confirm' => '',
            'module_dependency' => null,
            'permission' => null,
            'precedence' => 100,
        ], $actionConfig);
    }
}
