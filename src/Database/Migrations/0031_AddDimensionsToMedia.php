<?php

declare(strict_types=1);

/**
 * File: src/Database/Migrations/0031_AddDimensionsToMedia.php
 * Architectural Purpose: Database schema definition, transactional migration tracking, or seed data loader.
 * Package: Zero\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Database\Migrations;

use Zero\Core\Storage\Storage;
use Zero\Database\DB;
use Zero\Support\ImageProcessor;

/**
 * Class AddDimensionsToMedia
 *
 * Adds width and height to media, recording each image's intrinsic pixel size.
 *
 * Two things depend on knowing this without touching the file. Markup can carry accurate
 * width/height attributes, which lets a browser reserve the right space before an image arrives
 * instead of reflowing the page as each one loads. And the resized-variant URL builder can refuse
 * to upscale past the source without inspecting the file on every render, which is what keeps it
 * free of I/O.
 *
 * Existing rows are backfilled in place. A row whose file cannot be measured is left at zero,
 * which every consumer already treats as "unknown" and degrades gracefully around.
 */
class AddDimensionsToMedia extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding intrinsic dimension columns to media table...\n";

        // Defensively check if columns exist first before adding
        if (!DB::hasColumn('media', 'width')) {
            DB::query("ALTER TABLE media ADD COLUMN width INT NOT NULL DEFAULT 0 AFTER focus_y;");
        }
        if (!DB::hasColumn('media', 'height')) {
            DB::query("ALTER TABLE media ADD COLUMN height INT NOT NULL DEFAULT 0 AFTER width;");
        }

        $this->backfill();
    }

    /**
     * Measure every existing image row that has no recorded dimensions yet.
     *
     * @return void
     */
    protected function backfill(): void
    {
        // SVG is excluded rather than merely failing to measure: it is a vector format with no
        // intrinsic pixel size, so reading every one off disk only to have the probe return
        // nothing is wasted I/O on installations with a large icon library.
        $rows = DB::query(
            "SELECT id, path, mime FROM media
             WHERE mime LIKE 'image/%' AND mime <> 'image/svg+xml'
               AND (width = 0 OR height = 0) AND deleted_at IS NULL"
        )->fetchAll();

        if (empty($rows)) {
            return;
        }

        echo '  Backfilling dimensions for ' . \count($rows) . " existing image records...\n";

        $measured = 0;
        foreach ($rows as $row) {
            if (empty($row['path'])) {
                continue;
            }

            $bytes = $this->readSource((string)$row['path']);
            if ($bytes === null || $bytes === '') {
                continue;
            }

            $probe = ImageProcessor::probe($bytes);
            if ($probe === null) {
                continue;
            }

            DB::query(
                "UPDATE media SET width = ?, height = ? WHERE id = ?",
                [$probe['width'], $probe['height'], $row['id']]
            );
            $measured++;
        }

        echo "  Measured {$measured} image records.\n";
    }

    /**
     * Read a stored original's bytes from a migration's context.
     *
     * Migrations run from the CLI, where no tenant has been resolved from a request host, so the
     * local storage driver -- which derives its paths from the active site -- cannot be used.
     * Stored media paths are already tenant-prefixed, so the local case is read straight off disk
     * relative to the web root instead. The cloud drivers key objects by path alone and need no
     * such special casing.
     *
     * @param string $storedPath The media row's stored path.
     * @return string|null The file bytes, or null when it cannot be read.
     */
    protected function readSource(string $storedPath): ?string
    {
        if ($storedPath === '' || \strpos($storedPath, '..') !== false) {
            return null;
        }

        try {
            if (!Storage::isLocalDriver()) {
                return Storage::read($storedPath);
            }

            $absolute = Storage::getRoot() . '/public/' . \ltrim($storedPath, '/');
            if (!\is_file($absolute)) {
                return null;
            }

            $bytes = \file_get_contents($absolute);

            return $bytes === false ? null : $bytes;
        } catch (\Exception $exception) {
            // A missing or unreadable original must not abort the schema change; the row simply
            // keeps its zeroes and is treated as unmeasured by every consumer.
            return null;
        }
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing intrinsic dimension columns from media table...\n";

        if (DB::hasColumn('media', 'width')) {
            DB::query("ALTER TABLE media DROP COLUMN width;");
        }
        if (DB::hasColumn('media', 'height')) {
            DB::query("ALTER TABLE media DROP COLUMN height;");
        }
    }
}
