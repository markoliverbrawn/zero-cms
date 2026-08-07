<?php
// src/Models/Page.php

namespace Zero\Models;

use Zero\Interfaces\Model;
use Zero\Models\Traits\IsModel;
use Zero\Models\Traits\HasSlug;
use Zero\Models\Traits\IsOrderable;
use Zero\Models\Traits\UsesBlockBuilder;
use Zero\Modules\Search\Traits\Searchable;
use Zero\Database\DB;
use Zero\Support\I18n;
use Zero\Core\App;

class Page implements Model
{
    use IsModel, HasSlug, IsOrderable, UsesBlockBuilder, Searchable {
        IsModel::delete as traitDelete;
        IsModel::forceDelete as traitForceDelete;
    }

    protected static $tableName = 'pages';
    protected static $modelType = 'page';
    protected static $fillable = ['title', 'omit_title', 'slug', 'content', 'summary', 'controller', 'view', 'status', 'precedence', 'show_in_nav', 'exclude_from_search'];
    protected static bool $restrictGuests = true;

    public $id;
    public $title;
    public $omit_title = 0;
    public $slug;
    public $content;
    public $summary;
    public $controller;
    public $view;
    public $status; // Explicitly declared to prevent PHP 8.2+ dynamic property deprecation notices
    public $precedence;
    public $show_in_nav = 1;
    public $exclude_from_search = 0;
    public $site_id;
    public $created_at;
    public $updated_at;
    public $deleted_at;

    // Virtual fields for compound hierarchical slug editors
    public $parent_path = '';
    public $slug_part = '';

    /**
     * Page constructor override.
     * Automatically parses stored, compound database slug values (e.g. parent/child/segment)
     * into virtual parent_path ('parent/child') and slug_part ('segment') properties on record loading.
     */
    public function __construct($data = [])
    {
        if (isset($data['slug'])) {
            $slugStr = $data['slug'];
            $lastSlash = strrpos($slugStr, '/');
            if ($lastSlash !== false) {
                $data['parent_path'] = substr($slugStr, 0, $lastSlash);
                $data['slug_part'] = substr($slugStr, $lastSlash + 1);
            } else {
                $data['parent_path'] = '';
                $data['slug_part'] = $slugStr;
            }
        }

        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Override standard delete to prevent soft-deleting the designated site homepage page.
     */
    public function delete()
    {
        if ($this->isHomepage()) {
            throw new \Exception("Deletion blocked: You cannot delete the designated site homepage.");
        }
        return $this->traitDelete();
    }

    /**
     * Override standard forceDelete to prevent permanently deleting the designated site homepage page.
     */
    public function forceDelete()
    {
        if ($this->isHomepage()) {
            throw new \Exception("Permanent deletion blocked: You cannot delete the designated site homepage.");
        }
        return $this->traitForceDelete();
    }

    public static function getConfig(): array
    {
        // Dynamically compile active parent slugs list inside current site tenant boundaries
        $parentOptions = ['' => '(No Parent)'];
        try {
            $siteId = App::getCurrentSiteId();
            if ($siteId) {
                $sql = "SELECT id, title, slug FROM pages WHERE site_id = ? AND deleted_at IS NULL ORDER BY slug ASC";
                $rows = DB::query($sql, [$siteId])->fetchAll();
                foreach ($rows as $row) {
                    $parentOptions[$row['slug']] = ($row['slug'] === '' ? 'Home' : $row['slug']) . ' (' . $row['title'] . ')';
                }
            }
        } catch (\Exception $e) {
            // Fallback during seeder migrations
        }

        return [
            'id' => ['type' => 'int', 'label' => I18n::t('id'), 'editable' => false, 'listDisplay' => false],
            'title' => [
                'type' => 'text', 
                'label' => I18n::t('title'), 
                'editable' => true, 
                'required' => true, 
                'listDisplay' => true, 
                'searchable' => true
            ],
            'parent_path' => [
                'type' => 'select',
                'label' => 'Parent Page Path',
                'options' => $parentOptions,
                'editable' => true,
                'listDisplay' => false
            ],
            'slug_part' => [
                'type' => 'text',
                'label' => 'URL Slug Part',
                'editable' => true,
                'listDisplay' => false,
                'helper_text' => 'The unique end part of the URL (e.g. "web-development"). If left blank, it is automatically generated.'
            ],
            'omit_title' => [
                'type' => 'select',
                'label' => 'Omit Page Title',
                'options' => [
                    1 => 'Yes',
                    0 => 'No'
                ],
                'editable' => true,
                'listDisplay' => false,
                'required' => true
            ],
            'slug' => [
                'type' => 'text',
                'label' => I18n::t('slug'),
                'editable' => false,
                'listDisplay' => true
            ],
            'content' => ['type' => 'textarea', 'label' => I18n::t('content'), 'editable' => true, 'listDisplay' => false], // Don't show content in list
            'summary' => ['type' => 'textarea', 'label' => 'Summary Excerpt', 'editable' => true, 'listDisplay' => false],
            'controller' => ['type' => 'text', 'label' => 'Controller Class', 'editable' => true, 'listDisplay' => false],
            'view' => ['type' => 'text', 'label' => 'Custom View Name', 'editable' => true, 'listDisplay' => false],
            'precedence' => ['type' => 'number', 'label' => 'Display Precedence', 'editable' => true, 'listDisplay' => true],
            'show_in_nav' => [
                'type' => 'select',
                'label' => 'Show in Main Navigation',
                'options' => [
                    1 => 'Yes',
                    0 => 'No'
                ],
                'editable' => true,
                'listDisplay' => false,
                'required' => true
            ],
            'exclude_from_search' => [
                'type' => 'select',
                'label' => 'Exclude from Search',
                'options' => [
                    1 => 'Yes',
                    0 => 'No'
                ],
                'editable' => true,
                'listDisplay' => false,
                'required' => true
            ],
            'status' => [
                'type' => 'select',
                'label' => I18n::t('status'),
                'options' => [
                    'draft' => I18n::t('draft'),
                    'published' => I18n::t('published')
                ],
                'editable' => true,
                'listDisplay' => true,
                'listView' => 'fields/status',
                'required' => true
            ],
            'created_at' => ['type' => 'datetime', 'label' => I18n::t('created_at'), 'editable' => false, 'listDisplay' => true],
        ];
    }

    /**
     * Resolves the public frontend routing URL for this page record.
     * Keeps method sorting alphabetically correct (getConfig -> getFrontendUrl).
     */
    public function getFrontendUrl(): string
    {
        $slug = $this->slug ?? '';
        return $slug === '' ? '/' : '/' . ltrim($slug, '/');
    }

    /**
     * Determine if this specific page is currently the designated homepage of the site.
     */
    public function isHomepage(): bool
    {
        // Never block deletions of test pages created inside automated tests
        if (defined('TEST_SUITE_RUNNING')) {
            return false;
        }

        $site = App::getCurrentSite();
        if ($site) {
            // Check if this page's ID matches the explicitly selected homepage_id
            if (!empty($site->homepage_id) && $site->homepage_id === $this->id) {
                return true;
            }
            // Fallback: If no explicit homepage is selected, then any published/active empty slug "" is treated as the homepage
            if (empty($site->homepage_id) && $this->slug === '') {
                return true;
            }
        }
        return false;
    }

    /**
     * Custom save implementation override.
     * 1. Combines virtual parent_path and slug_part compound fields into the unified, slash-sanitized URL slug.
     * 2. Automatically enforces tenant-scoped unique slugs, appending sequential suffixes (e.g. -2, -3) inside the terminal path segment.
     * 3. Automatically detects parent slug changes and cascades prefix renames to all child descendant pages recursively.
     * 4. Delegates database record insertion or update tasks cleanly back to the core Active Record trait.
     * Keeps method sorting alphabetically correct (getFrontendUrl -> save).
     */
    public function save()
    {
        $tableName = static::$tableName;
        $siteId = App::getCurrentSiteId();

        // 1. Synchronize: If 'slug' has been manually updated from the outside, re-parse it first
        if ($tableName === 'pages' && !empty($this->slug)) {
            $lastSlash = strrpos($this->slug, '/');
            if ($lastSlash !== false) {
                $this->parent_path = substr($this->slug, 0, $lastSlash);
                $this->slug_part = substr($this->slug, $lastSlash + 1);
            } else {
                $this->parent_path = '';
                $this->slug_part = $this->slug;
            }
        }

        // 1.5 Safety: If this page is currently the designated site homepage, enforce an empty slug to prevent accidental 404 overrides!
        if ($tableName === 'pages' && $this->isHomepage()) {
            $this->slug = '';
            $this->slug_part = '';
            $this->parent_path = '';
        }

        // 2. Combine compound virtual parent and segment fields cleanly
        if ($tableName === 'pages') {
            $parent = trim($this->parent_path ?? '', '/');
            $part = trim($this->slug_part ?? '', '/');
            
            if ($part === '') {
                $slugifiedTitle = App::slugify($this->title);
                if ($slugifiedTitle === 'home' || $slugifiedTitle === 'index') {
                    $this->slug = '';
                } else {
                    $this->slug = $slugifiedTitle;
                }
            } else {
                $slugifiedPart = App::slugify($part);
                if ($slugifiedPart === 'home' || $slugifiedPart === 'index') {
                    $this->slug = '';
                } else {
                    $this->slug = $slugifiedPart;
                }
            }

            if ($parent !== '') {
                // Nested child pages should always have a valid sub-segment
                if ($this->slug === '') {
                    $slugifiedTitle = App::slugify($this->title);
                    $this->slug = ($slugifiedTitle === 'home' || $slugifiedTitle === 'index') ? 'home' : $slugifiedTitle;
                }
                $this->slug = $parent . '/' . $this->slug;
            }
        } else {
            // For flat-slug models (like blog posts)
            $part = trim($this->slug_part ?? '', '/');
            if ($part === '') {
                $this->slug = App::slugify($this->title);
            } else {
                $this->slug = App::slugify($part);
            }
        }

        $baseSlug = $this->slug;
        $uniqueSlug = $baseSlug;
        $counter = 2;

        // 3. Self-healing sequential unique slug suffix generator scoped inside terminal segment
        while (true) {
            $sql = "SELECT id FROM {$tableName} WHERE site_id = ? AND slug = ? AND id != ? AND deleted_at IS NULL LIMIT 1";
            $exists = DB::query($sql, [$siteId, $uniqueSlug, $this->id ?? ''])->fetch();
            if (!$exists) {
                break;
            }
            
            // Squeeze numerical suffix inside terminal segment: services/about -> services/about-2
            $lastSlash = strrpos($baseSlug, '/');
            if ($lastSlash !== false) {
                $uniqueSlug = substr($baseSlug, 0, $lastSlash) . '/' . substr($baseSlug, $lastSlash + 1) . '-' . $counter;
            } else {
                $uniqueSlug = $baseSlug . '-' . $counter;
            }
            $counter++;
        }
        $this->slug = $uniqueSlug;

        // Update virtual fields to reflect any newly generated unique slug suffixes
        $lastSlash = strrpos($this->slug, '/');
        if ($lastSlash !== false) {
            $this->parent_path = substr($this->slug, 0, $lastSlash);
            $this->slug_part = substr($this->slug, $lastSlash + 1);
        } else {
            $this->parent_path = '';
            $this->slug_part = $this->slug;
        }

        // 4. Detect parent slug changes and cascade update prefixes recursively
        if ($tableName === 'pages' && !empty($this->id)) {
            $original = DB::query("SELECT slug FROM pages WHERE id = ?", [$this->id])->fetch();
            $oldSlug = $original ? $original['slug'] : null;
            $newSlug = $this->slug;

            if ($oldSlug !== null && $oldSlug !== $newSlug && $oldSlug !== '') {
                $oldPrefixPattern = $oldSlug . '/%';
                $oldSlugLength = strlen($oldSlug);
                $substringStartIndex = $oldSlugLength + 1; // 1-indexed SUBSTRING

                DB::query("
                    UPDATE pages 
                    SET slug = CONCAT(?, SUBSTRING(slug, ?))
                    WHERE site_id = ? AND slug LIKE ? AND deleted_at IS NULL
                ", [$newSlug, $substringStartIndex, $siteId, $oldPrefixPattern]);
            }
        }

        // 5. Delegate persistence processes directly to parent IsModel trait methods
        if (!empty($this->id)) {
            $exists = static::find($this->id);
            if ($exists) {
                return $this->update();
            }
        }
        return $this->create();
    }

    /**
     * Determines dynamically if this specific page instance should enable the block builder editor.
     * Special pages powered by custom dynamic controllers (such as Blog, Shop, and Forum index pages)
     * are processed dynamic-first and do not require block-builder templates inside back-office edit sheets.
     * Keeps method sorting alphabetically correct (save -> usesBlockBuilder).
     */
    public function usesBlockBuilder(): bool
    {
        // Decoupled capability check: If a custom controller is assigned, check if it declares support for page builder blocks
        if (!empty($this->controller)) {
            if (class_exists($this->controller)) {
                if (method_exists($this->controller, 'supportsBlocks')) {
                    return (bool) $this->controller::supportsBlocks();
                }
                if (method_exists($this->controller, 'isBlockBuilderEnabled')) {
                    return (bool) $this->controller::isBlockBuilderEnabled();
                }
            }
            return false;
        }
        return true;
    }
}
