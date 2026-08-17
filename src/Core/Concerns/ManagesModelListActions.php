<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/ManagesModelListActions.php
 * Architectural Purpose: Registry for extra action buttons a module can contribute to a specific
 * model's admin listing page (e.g. an extra button next to "New"), sharing the exact same
 * module-dependency/role visibility gating already used by the admin sidebar registry.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

/**
 * Trait ManagesModelListActions
 */
trait ManagesModelListActions
{
    protected static $modelListActions = [];

    /**
     * Get every registered list action for a model, sorted by precedence. Visibility is NOT
     * filtered here -- callers should check each action against isModelListActionVisible()
     * before rendering.
     *
     * @param string $modelName
     * @return array
     */
    public static function getModelListActions(string $modelName): array
    {
        $actions = self::$modelListActions[$modelName] ?? [];

        \usort($actions, function ($a, $b) {
            return ($a['precedence'] ?? 100) <=> ($b['precedence'] ?? 100);
        });

        return $actions;
    }

    /**
     * Helper to resolve if a registered list action is visible to the current logged-in user
     * under the active tenant site. Deliberately does NOT reuse isSidebarItemVisible()'s
     * super_admin bypass on module_dependency: a list action gated to a module should only ever
     * appear when that module is genuinely active for the current site. Reusing the sidebar's
     * bypass would make the gate a no-op on any model whose listing page is itself
     * super_admin-only (e.g. 'sites'), since only super admins could ever reach it.
     *
     * @param array $action
     * @param \Zero\Models\Site|null $site
     * @return bool
     */
    public static function isModelListActionVisible(array $action, ?\Zero\Models\Site $site): bool
    {
        $role = self::getCurrentUserRole();

        if (!empty($action['super_admin_only']) && $role !== 'super_admin') {
            return false;
        }

        if (!empty($action['module_dependency'])) {
            return $site !== null && $site->isModuleEnabled($action['module_dependency']);
        }

        return true;
    }

    /**
     * Register an extra action button for a model's admin listing page (e.g. next to "New").
     *
     * @param string $modelName The registered model name (e.g. 'sites').
     * @param array $actionConfig {
     *     @var string $label Button text.
     *     @var string $url Target URL.
     *     @var string $method 'get' renders a plain link; 'post' renders a confirm+fetch button.
     *     @var string $confirm Confirmation message shown before a 'post' action runs.
     *     @var string|null $module_dependency Module ID gating visibility, mirroring sidebar links.
     *     @var bool $super_admin_only
     *     @var int $precedence
     * }
     * @return void
     */
    public static function registerModelListAction(string $modelName, array $actionConfig): void
    {
        self::$modelListActions[$modelName][] = \array_merge([
            'label' => '',
            'url' => '',
            'method' => 'get',
            'confirm' => '',
            'module_dependency' => null,
            'super_admin_only' => false,
            'precedence' => 100,
        ], $actionConfig);
    }
}
