<?php
/**
 * File: src/Modules/Search/Interfaces/SearchDriverInterface.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Search\Interfaces
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Search\Interfaces;

/**
 * Interface SearchDriverInterface
 *
 * Defines systemic behavioral interface contract mechanisms.
 */
interface SearchDriverInterface
{
    /**
     * Clear the search index for a specific site/tenant.
     *
     * @param string $siteId
     * @return void
     */
    public function clear(string $siteId): void;

    /**
     * Delete an entry from the search index.
     *
     * @param string $modelType
     * @param string $modelId
     * @return void
     */
    public function delete(string $modelType, string $modelId): void;

    /**
     * Index a model's searchable fields.
     *
     * @param string $siteId
     * @param string $modelType
     * @param string $modelId
     * @param string $title
     * @param string $content
     * @param string $url
     * @return void
     */
    public function index(
        string $siteId,
        string $modelType,
        string $modelId,
        string $title,
        string $content,
        string $url
    ): void;

    /**
     * Search the index with pagination support.
     * Returns an array with structure:
     * [
     *   'results' => Array of normalized result arrays,
     *   'total' => Total matching count across the entire index for pagination
     * ]
     *
     * @param string $query
     * @param array $options (limit, offset)
     * @return array
     */
    public function search(string $query, array $options = []): array;
}
