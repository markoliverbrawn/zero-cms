<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/ManagesFormFields.php
 * Architectural Purpose: The form-control component type registry -- lets core and modules
 * register a `type` string against a FormField class, and construct field instances generically
 * from the same array-config schema shape already used by getConfig()/registerModuleSettings().
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

use Zero\Interfaces\FormField;
use Zero\Support\Forms\BlockBuilderField;
use Zero\Support\Forms\Checkbox;
use Zero\Support\Forms\CheckboxGroup;
use Zero\Support\Forms\DateTimeInput;
use Zero\Support\Forms\EmailInput;
use Zero\Support\Forms\FileInput;
use Zero\Support\Forms\GalleryPickerField;
use Zero\Support\Forms\Hidden;
use Zero\Support\Forms\ImagePickerField;
use Zero\Support\Forms\ModulesGridField;
use Zero\Support\Forms\NumberInput;
use Zero\Support\Forms\PasswordInput;
use Zero\Support\Forms\RadioGroup;
use Zero\Support\Forms\ReadonlyField;
use Zero\Support\Forms\RichTextEditorField;
use Zero\Support\Forms\Select;
use Zero\Support\Forms\TelInput;
use Zero\Support\Forms\Textarea;
use Zero\Support\Forms\TextInput;

/**
 * Trait ManagesFormFields
 */
trait ManagesFormFields
{
    protected static array $registeredFormFieldTypes = [];

    /**
     * Retrieve every registered form-field type, keyed by type string.
     *
     * @return array<string, string>
     */
    public static function getRegisteredFormFieldTypes(): array
    {
        return self::$registeredFormFieldTypes;
    }

    /**
     * Construct a FormField instance for the given type string, falling back to a plain text
     * input if the type was never registered (e.g. a typo or a not-yet-supported legacy type).
     *
     * @param string $type
     * @param string $name
     * @param array $config
     * @return FormField
     */
    public static function makeFormField(string $type, string $name, array $config): FormField
    {
        $class = self::$registeredFormFieldTypes[$type] ?? self::$registeredFormFieldTypes['text'];
        $config['type'] = $config['type'] ?? $type;
        return new $class($name, $config);
    }

    /**
     * Register the core, built-in form-field types every module can rely on being available.
     * Called once from ResolvesTenantContext::bootstrapInitialize(), before any module's own
     * init() runs, so a module can safely register additional custom types in its own init()
     * without any ordering hazard.
     *
     * @return void
     */
    public static function registerCoreFormFieldTypes(): void
    {
        self::registerFormFieldType('block_builder', BlockBuilderField::class);
        self::registerFormFieldType('checkbox', Checkbox::class);
        self::registerFormFieldType('checkbox_group', CheckboxGroup::class);
        self::registerFormFieldType('datetime', DateTimeInput::class);
        self::registerFormFieldType('email', EmailInput::class);
        self::registerFormFieldType('file', FileInput::class);
        self::registerFormFieldType('gallery_picker', GalleryPickerField::class);
        self::registerFormFieldType('hidden', Hidden::class);
        self::registerFormFieldType('image', ImagePickerField::class);
        self::registerFormFieldType('int', NumberInput::class);
        self::registerFormFieldType('modules', ModulesGridField::class);
        self::registerFormFieldType('number', NumberInput::class);
        self::registerFormFieldType('password', PasswordInput::class);
        self::registerFormFieldType('radio_group', RadioGroup::class);
        self::registerFormFieldType('readonly', ReadonlyField::class);
        self::registerFormFieldType('rich_text_editor', RichTextEditorField::class);
        self::registerFormFieldType('select', Select::class);
        self::registerFormFieldType('tel', TelInput::class);
        self::registerFormFieldType('text', TextInput::class);
        self::registerFormFieldType('textarea', Textarea::class);
    }

    /**
     * Register a form-field class against a type string.
     *
     * @param string $type
     * @param string $class Fully-qualified FormField-implementing class name.
     * @return void
     */
    public static function registerFormFieldType(string $type, string $class): void
    {
        self::$registeredFormFieldTypes[$type] = $class;
    }

    /**
     * Resolve a 'listView' config value (used by admin list columns and by ReadonlyField's edit
     * view) to an absolute template path, or null if it doesn't exist.
     *
     * A bare relative string (e.g. 'fields/status', the form every core model uses today) is
     * resolved inside the Admin module's own Views folder, for backward compatibility. A module
     * that doesn't want to reach into Admin's folder can instead pass an absolute path to its own
     * template -- e.g. `dirname(__DIR__) . '/Views/admin/fields/my_field.php'` -- which is used
     * as-is; this is the extension point that lets any module render its own admin list columns
     * (or readonly edit-form fields) as HTML via a template, without owning code inside Admin/.
     *
     * @param string $listView
     * @return string|null
     */
    public static function resolveListView(string $listView): ?string
    {
        $path = (\strpos($listView, '/') === 0)
            ? $listView . '.php'
            : APPLICATION_ROOT . '/src/Modules/Admin/Views/' . $listView . '.php';

        return \file_exists($path) ? $path : null;
    }
}
