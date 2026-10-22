<?php

declare(strict_types=1);

/**
 * File: src/Support/RenderSlots.php
 * Architectural Purpose: Per-instance concurrency cap for image renders.
 * Package: Zero\Support
 * Systemic Role: Keeps a burst of first-time variant requests from exhausting container memory.
 */

namespace Zero\Support;

use Zero\Core\Env;

/**
 * Class RenderSlots
 *
 * Caps how many image renders run at once in this container, so a burst of first-time variant
 * requests queues briefly instead of exhausting memory. Each render decodes the full source into
 * memory (a 1200x1600 photo is ~8MB raw before GD's working copies), so twenty at once on one
 * instance is enough to hit its limit. Slots are flock()ed files in the system temp dir, which is
 * per-instance on Cloud Run and per-host locally; a crashed process's lock dies with it.
 *
 * MediaVariantController::generate() already holds a per-variant lock so one image is never
 * rendered twice at once; this caps how many *different* images render together.
 */
class RenderSlots
{
    /**
     * Default number of seconds to wait for a free slot before rendering anyway.
     */
    public const DEFAULT_WAIT_SECONDS = 20.0;

    /**
     * Slot directory override, for tests; null uses the system temp dir.
     *
     * @var string|null
     */
    protected static ?string $directory = null;

    /**
     * Block until a render slot is free (or the wait times out) and return its handle.
     *
     * @param float|null $waitSeconds How long to wait for a slot, or null for the default.
     * @return resource|null The slot handle to pass to release(), or null if no slot came free in
     *                       time (or the slot files are unusable); the caller renders anyway.
     */
    public static function acquire(?float $waitSeconds = null)
    {
        $slots = \max(1, (int)Env::get('IMAGE_RENDER_SLOTS', '3'));
        $deadline = \microtime(true) + \max(0.0, $waitSeconds ?? self::DEFAULT_WAIT_SECONDS);
        $directory = self::directory();

        if (!\is_dir($directory) && !self::quietly(static fn() => \mkdir($directory, 0775, true)) && !\is_dir($directory)) {
            return null;
        }

        while (true) {
            for ($i = 0; $i < $slots; $i++) {
                $handle = self::quietly(static fn() => \fopen("{$directory}/slot-{$i}.lock", 'c'));
                if ($handle === false) {
                    continue;
                }
                if (\flock($handle, LOCK_EX | LOCK_NB)) {
                    return $handle;
                }
                \fclose($handle);
            }

            if (\microtime(true) >= $deadline) {
                return null;
            }
            \usleep(50000);
        }
    }

    /**
     * Free a slot taken by acquire().
     *
     * @param resource|null $handle The handle returned by acquire().
     * @return void
     */
    public static function release($handle): void
    {
        if (\is_resource($handle)) {
            \flock($handle, LOCK_UN);
            \fclose($handle);
        }
    }

    /**
     * Point the slots at a different directory, or back at the default with null. For tests.
     *
     * @param string|null $directory Absolute directory path, or null for the default.
     * @return void
     */
    public static function useDirectory(?string $directory): void
    {
        self::$directory = $directory;
    }

    /**
     * Resolve the directory holding the slot lock files.
     *
     * @return string
     */
    protected static function directory(): string
    {
        return self::$directory ?? \sys_get_temp_dir() . '/zero-render-slots';
    }

    /**
     * Run a filesystem call with its warnings swallowed, returning false if it warned.
     *
     * A concurrent mkdir() or an unwritable temp dir is an expected, handled condition here (the
     * caller just renders without a slot), so the warning must not leak into an image response.
     *
     * @param callable $operation The filesystem call.
     * @return mixed The call's result, or false if it raised a warning.
     */
    protected static function quietly(callable $operation)
    {
        \set_error_handler(static fn() => true);
        try {
            return $operation();
        } finally {
            \restore_error_handler();
        }
    }
}
