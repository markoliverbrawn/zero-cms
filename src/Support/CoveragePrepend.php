<?php
// src/Support/CoveragePrepend.php
// Auto-prepended into every PHP process spawned while `bin/test --coverage` is running, via the
// php.ini fragment that Zero\Support\CoverageRecorder::prepare() generates into storage/coverage/ini
// and points PHP at with PHP_INI_SCAN_DIR. Its only job is to start coverage recording for whatever
// process it has just been loaded into -- including subprocesses a test file spawns itself, which is
// the whole reason the suite instruments the process tree rather than one process.
//
// Three constraints shape this file, and all three are why it stays this small:
//   1. It must never emit output. Several suites assert against a subprocess's exact stdout, so a
//      stray notice here would fail tests that have nothing to do with coverage.
//   2. It must never fatal. It is loaded ahead of every script, including bin/seed and short
//      `php -r` invocations, so a failure here would look like a failure in unrelated tooling.
//   3. It must not rely on the autoloader, which has not necessarily run yet at prepend time -- so
//      the recorder class is required directly by path instead of resolved by namespace.

$coverageRecorderPath = __DIR__ . '/CoverageRecorder.php';

if (\is_file($coverageRecorderPath)) {
    require_once $coverageRecorderPath;

    if (\class_exists(\Zero\Support\CoverageRecorder::class, false)) {
        \Zero\Support\CoverageRecorder::record();
    }
}
