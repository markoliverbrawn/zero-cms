<?php
/**
 * File: src/Modules/Search/Traits/Searchable.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Search\Traits
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Search\Traits;

use Zero\Core\App;
use Zero\Modules\Search\Services\SearchService;

/**
 * Trait Searchable
 *
 * Defines systemic behavioral interface contract mechanisms.
 */
trait Searchable
{
    /**
     * Recursively traverses any arbitrary block array structure to extract indexable strings,
     * while explicitly skipping structural configuration, routing, and media keys.
     *
     * @param array $array Raw block array data
     * @return array Array of cleaned text parts
     */
    private function extractTextRecursively(array $array): array
    {
        $extracted = [];
        $metaBlacklist = [
            'background', 'background_color', 'color', 'controller', 'css_class',
            'font', 'id', 'image', 'image_id', 'layout', 'link', 'logo', 'media_id',
            'path', 'size', 'status', 'style', 'theme', 'type', 'url', 'view'
        ];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $extracted = array_merge($extracted, $this->extractTextRecursively($value));
            } elseif (is_string($value) && !is_numeric($key) && !in_array(strtolower($key), $metaBlacklist)) {
                $extracted[] = strip_tags($value);
            }
        }

        return $extracted;
    }

    /**
     * Compile a clean plain-text body of the model's searchable fields.
     * Checks if a specialized OOP BlockHelper exists for each layout block to delegate
     * parsing cleanly, falling back to a recursive in-memory string extractor.
     *
     * @return string
     */
    public function getSearchableContent(): string
    {
        $content = $this->content ?? $this->description ?? '';
        $textParts = [];

        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach ($decoded as $block) {
                $type = $block['type'] ?? '';
                // Resolve StudlyCase class name from snake_case block type, e.g. text_image -> TextImageBlock
                $className = '\\Zero\\Blocks\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $type))) . 'Block';

                if (class_exists($className) && is_subclass_of($className, '\\Zero\\Interfaces\\BlockHelperInterface')) {
                    $helper = new $className($block);
                    $textParts[] = $helper->getSearchableContent();
                } else {
                    // Graceful fallback to the recursive string extractor if no specialized helper class is found
                    $textParts = array_merge($textParts, $this->extractTextRecursively([$block]));
                }
            }
        } else {
            $textParts[] = strip_tags($content);
        }

        // Prepend summary if available to increase keyword richness
        if (!empty($this->summary)) {
            array_unshift($textParts, strip_tags($this->summary));
        }

        // Include sku if available (mostly for Products)
        if (!empty($this->sku)) {
            $textParts[] = $this->sku;
        }

        return trim(implode(' ', array_filter($textParts)));
    }

    /**
     * Retrieve the searchable title of the model (falls back to title field).
     *
     * @return string
     */
    public function getSearchableTitle(): string
    {
        return $this->title ?? '';
    }

    /**
     * Retrieve the frontend URL of the model.
     *
     * @return string
     */
    public function getSearchableUrl(): string
    {
        if (method_exists($this, 'getFrontendUrl')) {
            return $this->getFrontendUrl();
        }
        return '/' . ltrim($this->slug ?? '', '/');
    }

    /**
     * Synchronize this model record with the search index.
     * Automatically de-indexes if the model is not published or is manually excluded.
     *
     * @return void
     */
    public function indexInSearch(): void
    {
        $siteId = $this->site_id ?? App::getCurrentSiteId();
        if (empty($siteId) || empty($this->id)) {
            return;
        }

        $status = $this->status ?? 'published';
        $exclude = (int)($this->exclude_from_search ?? 0);

        // Auto de-index if not active/published or manually excluded
        if ($status !== 'published' || $exclude === 1) {
            $this->removeFromSearch();
            return;
        }

        $modelType = property_exists(static::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class, 'modelType') ? static::$modelType : null;
        if (empty($modelType)) {
            $parts = explode('\\', static::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);
            $modelType = strtolower(end($parts));
        }

        $title = $this->getSearchableTitle();
        $content = $this->getSearchableContent();
        $url = $this->getSearchableUrl();

        SearchService::index($siteId, $modelType, $this->id, $title, $content, $url);
    }

    /**
     * Remove this model record from the search index.
     *
     * @return void
     */
    public function removeFromSearch(): void
    {
        if (empty($this->id)) {
            return;
        }

        $modelType = property_exists(static::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class, 'modelType') ? static::$modelType : null;
        if (empty($modelType)) {
            $parts = explode('\\', static::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);
            $modelType = strtolower(end($parts));
        }

        SearchService::delete($modelType, $this->id);
    }
}
