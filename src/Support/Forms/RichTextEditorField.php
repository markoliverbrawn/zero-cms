<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/RichTextEditorField.php
 * Architectural Purpose: WYSIWYG rich-text editor field -- wraps the existing
 * src/Modules/Admin/Views/editor.php partial (contenteditable toolbar + hidden mirror input),
 * which reads $record/$field from its rendering scope (Template::renderFile()'s extract()
 * reproduces that scope, exactly as the legacy raw `include` did).
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;
use Zero\Support\Str;

/**
 * Class RichTextEditorField
 *
 * Value is a raw HTML string as submitted; casting returns it unchanged (sanitization, if any,
 * happens wherever it already does today -- out of scope for this field). The toolbar button
 * registry below controls only which buttons a mode shows in the editor UI -- it has no bearing
 * on what HTML a submitted value may contain (contenteditable + paste bypasses the toolbar
 * entirely), so it is deliberately not wired to any sanitizer's allow-list.
 */
class RichTextEditorField extends AbstractFormField
{
    /**
     * Button key => full <button> markup, in registration order. Any module can add to this via
     * registerToolbarButton() from its own init() without forking this class or its view.
     *
     * @var array<string, string>
     */
    protected static array $buttons = [
        'bold'                => '<button type="button" data-cmd="bold"><strong>B</strong></button>',
        'italic'              => '<button type="button" data-cmd="italic"><em>I</em></button>',
        'underline'           => '<button type="button" data-cmd="underline"><u>U</u></button>',
        'insertUnorderedList' => '<button type="button" data-cmd="insertUnorderedList">UL</button>',
        'insertOrderedList'   => '<button type="button" data-cmd="insertOrderedList">OL</button>',
        'createLink'          => '<button type="button" data-cmd="createLink">A</button>',
        'insertTable'         => '<button type="button" data-cmd="insertTable" title="Insert Table">Table</button>',
        'removeFormat'        => '<button type="button" data-cmd="removeFormat">Clear</button>',
    ];

    /**
     * Named toolbar mode => ordered list of button keys. 'full' is deliberately not stored here --
     * it always means every currently-registered button (see resolveToolbarButtons()), so a newly
     * registered button appears in 'full' automatically, with no extra registration step.
     *
     * @var array<string, string[]>
     */
    protected static array $toolbarSets = [
        'basic' => ['bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList', 'createLink', 'removeFormat'],
    ];

    /**
     * Register a new toolbar button (or override an existing one, by reusing its key), optionally
     * adding it to one or more named toolbar sets in the same call.
     *
     * @param string $key Unique identifier, matching the data-cmd the editor's JS handles for it.
     * @param string $html Full <button> markup.
     * @param string[] $addToSets Named sets (besides 'full', which always includes every
     *                            registered button) to also append this button to.
     */
    public static function registerToolbarButton(string $key, string $html, array $addToSets = []): void
    {
        self::$buttons[$key] = $html;
        foreach ($addToSets as $set) {
            self::$toolbarSets[$set][] = $key;
        }
    }

    /**
     * Register (or replace) a named toolbar set as an ordered list of button keys. A key that
     * isn't registered (yet, or ever) is silently skipped at render time rather than erroring, so
     * a set can be declared without caring about module init() ordering.
     *
     * @param string $name
     * @param string[] $buttonKeys
     */
    public static function registerToolbarSet(string $name, array $buttonKeys): void
    {
        self::$toolbarSets[$name] = $buttonKeys;
    }

    /**
     * Resolve a toolbar mode to its ordered list of button HTML, falling back to 'full' (every
     * registered button) for an unset or unrecognized mode.
     *
     * @param string|null $mode
     * @return string[]
     */
    public static function resolveToolbarButtons(?string $mode): array
    {
        $keys = ($mode !== null && $mode !== 'full' && isset(self::$toolbarSets[$mode]))
            ? self::$toolbarSets[$mode]
            : \array_keys(self::$buttons);

        return \array_values(\array_filter(\array_map(
            fn($key) => self::$buttons[$key] ?? null,
            $keys
        )));
    }

    /**
     * @param array $source
     * @return string
     */
    public function castSubmittedValue(array $source)
    {
        return (string)($source[$this->name] ?? ($this->value ?? ''));
    }

    /**
     * @return string
     */
    protected function getTemplatePath(): string
    {
        return APPLICATION_ROOT . '/src/Modules/Admin/Views/editor.php';
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $labelHtml = $this->showLabel ? '<label>' . Str::escape($this->label) . '</label>' : '';
        return $labelHtml . Template::renderFile($this->getTemplatePath(), [
            'record' => $this->config['record'] ?? null,
            'field' => $this->name,
            'toolbarButtonsHtml' => self::resolveToolbarButtons($this->config['toolbar'] ?? null),
        ]);
    }
}
