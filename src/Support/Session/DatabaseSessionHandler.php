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
 *
 * PHP's default file handler serializes concurrent requests for the same session via flock() -
 * session_start() blocks until the previous request's session_write_close() releases the lock. A
 * plain SELECT/INSERT..ON DUPLICATE KEY UPDATE pair doesn't have that property, so two overlapping
 * requests for the same session (multiple tabs, or an admin page's concurrent AJAX calls) can race:
 * whichever request's write() lands last wins outright and silently reverts the other's session
 * data. read()/write()/destroy() take out a MySQL advisory lock keyed on the session id to restore
 * that serialization.
 */
class DatabaseSessionHandler implements \SessionHandlerInterface
{
    /**
     * The session id currently holding this handler's advisory lock, or null if none is held.
     *
     * @var string|null
     */
    private ?string $lockedId = null;

    /**
     * Close the session storage backend, releasing any advisory lock still held defensively.
     *
     * @return bool Always true.
     */
    public function close(): bool
    {
        $this->releaseLock();

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

        $this->releaseLock();

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
        $this->acquireLock($id);

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
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Acquire the MySQL advisory lock guarding this session id, blocking briefly for any concurrent
     * request currently holding it. Degrades to a no-op (and logs) on timeout or failure rather than
     * blocking the request indefinitely - losing the race guard occasionally beats a hung page.
     *
     * @param string $id The session identifier.
     * @return void
     */
    private function acquireLock(string $id): void
    {
        try {
            $lockName = self::lockName($id);
            $acquired = DB::query("SELECT GET_LOCK(?, 5) AS acquired", [$lockName])->fetchColumn();
            if ((int)$acquired === 1) {
                $this->lockedId = $id;
            } else {
                // Log the derived lock name, not the raw session id - the id is a bearer credential
                // and shouldn't be written to logs in cleartext.
                \error_log("DatabaseSessionHandler: timed out waiting for session lock ({$lockName})");
            }
        } catch (\Throwable $exception) {
            \error_log('DatabaseSessionHandler: failed to acquire session lock: ' . $exception->getMessage());
        }
    }

    /**
     * Release this handler's advisory lock, if one is currently held.
     *
     * @return void
     */
    private function releaseLock(): void
    {
        if ($this->lockedId === null) {
            return;
        }

        try {
            DB::query("SELECT RELEASE_LOCK(?)", [self::lockName($this->lockedId)]);
        } catch (\Throwable $exception) {
            \error_log('DatabaseSessionHandler: failed to release session lock: ' . $exception->getMessage());
        }

        $this->lockedId = null;
    }

    /**
     * Derive a MySQL GET_LOCK name for a session id, hashed to fit within MySQL's lock name length
     * limit regardless of how long the session id itself is.
     *
     * @param string $id The session identifier.
     * @return string The advisory lock name.
     */
    private static function lockName(string $id): string
    {
        return 'zero_session_' . \md5($id);
    }
}
