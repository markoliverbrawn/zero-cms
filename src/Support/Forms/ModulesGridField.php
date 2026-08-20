<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/ModulesGridField.php
 * Architectural Purpose: Site-level module-toggle checkbox grid -- ports the existing bespoke
 * Site.enabled_modules widget (one checkbox per registered module, with a friendly name/
 * description) into a reusable component. The module id -> name/description map is moved
 * verbatim from the legacy view; sourcing it from each Module's own metadata instead is a
 * reasonable future improvement but a separate, larger, cross-cutting change (touching every
 * Module.php), deliberately out of scope here.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\App;
use Zero\Core\Template;

/**
 * Class ModulesGridField
 *
 * Value is a JSON-encoded array of enabled module id strings.
 */
class ModulesGridField extends AbstractFormField
{
    /**
     * @param array $source
     * @return array
     */
    public function castSubmittedValue(array $source)
    {
        $submitted = $source[$this->name] ?? [];
        return \is_array($submitted) ? \array_values($submitted) : [$submitted];
    }

    /**
     * @return string
     */
    protected function getTemplatePath(): string
    {
        return APPLICATION_ROOT . '/src/Views/components/forms/modules_grid_field.php';
    }

    /**
     * Friendly name/description for a module id, matching the legacy hardcoded map.
     *
     * @param string $id
     * @return array{name: string, desc: string}
     */
    protected function resolveModuleMeta(string $id): array
    {
        switch ($id) {
            case 'shop':
                return ['name' => 'Luxe E-Commerce Store', 'desc' => 'Catalog, Products, Variants, Cart, Checkout'];
            case 'formbuilder':
                return ['name' => 'Form Builder', 'desc' => 'Dynamic Custom Contact Forms'];
            case 'forum':
                return ['name' => 'Community Forum', 'desc' => 'Discussions, Boards, Threads, Replies'];
            case 'site-search':
                return ['name' => 'Search', 'desc' => 'Page and Posts'];
            case 'security':
                return ['name' => 'Security', 'desc' => 'Hardening & AI threat auditing'];
            default:
                return ['name' => \ucwords(\str_replace('-', ' ', $id)), 'desc' => 'Additional addon capability'];
        }
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $activeModules = \json_decode((string)($this->value ?? '[]'), true);
        if (!\is_array($activeModules)) {
            $activeModules = [];
        }

        $modules = [];
        foreach (App::getModules() as $module) {
            $id = $module->getId();
            // Skip system modules from the site-level toggle checklist
            if ($id === 'admin' || $id === 'queue') {
                continue;
            }
            $meta = $this->resolveModuleMeta($id);
            $modules[] = [
                'id' => $id,
                'name' => $meta['name'],
                'desc' => $meta['desc'],
                'checked' => \in_array($id, $activeModules, true),
            ];
        }

        return Template::renderFile($this->getTemplatePath(), [
            'name' => $this->name,
            'label' => $this->label,
            'showLabel' => $this->showLabel,
            'modules' => $modules,
            'helperText' => $this->helperText,
        ]);
    }
}
