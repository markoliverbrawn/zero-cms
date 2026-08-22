<?php

declare(strict_types=1);

/**
 * File: src/Support/Session/DatabaseSessionHandler.php
 * Architectural Purpose: Zero-dependency PHP session persistence backend.
 * Package: Zero\Support\Session
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Session;

use Zero\Database\DB;

/**
 * Class DatabaseSessionHandler
 *
 * A SessionHandlerInterface implementation backed by the `sessions` table instead of PHP's default
 * local-disk file store, so a session survives being served by a different app server instance on
 * every request (there is no shared or persistent local disk to rely on across instances).
 *
 * Every method degrades to a harmless no-op/failure rather than throwing: this handler is installed
 * during App::bootstrap(), which can run before the `sessions` table exists yet (a fresh install's
 * first request, or the CLI migration/seed runner bootstrapping ahead of its own migration).
 */
class DatabaseSessionHandler implements \SessionHandlerInterface
{
    /**
     * Close the session storage backend. Nothing to release for a stateless PDO-backed handler.
     *
     * @return bool Always true.
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Delete a destroyed session's stored data.
     *
     * @param string $id The session identifier.
     * @return bool Always true.
     */
    public function destroy($id): bool
    {
        try {
            DB::query("DELETE FROM sessions WHERE id = ?", [$id]);
        } catch (\Throwable $exception) {
            \error_log('DatabaseSessionHandler::destroy failed: ' . $exception->getMessage());
        }

        return true;
    }

    /**
     * Purge sessions that have been inactive for longer than the configured max lifetime.
     *
     * @param int $max_lifetime Seconds of inactivity after which a session is considered expired.
     * @return int|false The number of rows purged, or false on failure.
     */
    public function gc($max_lifetime): int|false
    {
        try {
            $cutoff = \time() - $max_lifetime;
            $stmt = DB::query("DELETE FROM sessions WHERE last_activity < ?", [$cutoff]);

            return $stmt->rowCount();
        } catch (\Throwable $exception) {
            \error_log('DatabaseSessionHandler::gc failed: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Open the session storage backend. The shared PDO connection is already available via DB.
     *
     * @param string $path Save path (unused).
     * @param string $name Session name (unused).
     * @return bool Always true.
     */
    public function open($path, $name): bool
    {
        return true;
    }

    /**
     * Read a session's stored serialized data.
     *
     * @param string $id The session identifier.
     * @return string The stored data, or an empty string when absent/unreadable.
     */
    public function read($id): string
    {
        try {
            $row = DB::query("SELECT data FROM sessions WHERE id = ?", [$id])->fetch();

            return $row ? (string)$row['data'] : '';
        } catch (\Throwable $exception) {
            \error_log('DatabaseSessionHandler::read failed: ' . $exception->getMessage());

            return '';
        }
    }

    /**
     * Persist a session's serialized data, upserting on the primary key.
     *
     * @param string $id   The session identifier.
     * @param string $data The serialized session payload.
     * @return bool True on success, false on failure.
     */
    public function write($id, $data): bool
    {
        try {
            DB::query("
                INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)
            ", [$id, $data, \time()]);

            return true;
        } catch (\Throwable $exception) {
            \error_log('DatabaseSessionHandler::write failed: ' . $exception->getMessage());

            return false;
        }
    }
}
