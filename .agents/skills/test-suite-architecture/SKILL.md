---
name: test-suite-architecture
description: Explains Zero CMS's zero-dependency automated test suite — how tests are discovered, run in parallel isolated subprocesses, and where new tests should be added. Use when adding a new *Test.php file, modifying bin/test or src/Support/TestRunner.php/TestBootstrap.php, or investigating a test failure/CI run.
---

# Zero-Dependency Automated Testing Suite

To ensure absolute system stability, security, and multi-tenant isolation without introducing bloated third-party frameworks (such as PHPUnit or Pest), Zero CMS features a custom zero-dependency test suite. There is no root `tests/` directory — every test file lives directly alongside the code it tests.

## Layout

* **Component tests**: `src/<Component>/Tests/*Test.php` (e.g. `src/Core/Tests/`, `src/Models/Tests/`, `src/Support/Tests/`, `src/Database/Tests/`, `src/Services/Tests/`). Cross-cutting tests that don't belong to one component live in `src/Integration/Tests/`.
* **Module tests**: `src/Modules/<Module>/Tests/*Test.php` (e.g. `src/Modules/Blog/Tests/`).
* **Shared bootstrap**: `src/Support/TestBootstrap.php` — the one file every test requires directly. It starts the session, defines `APPLICATION_ROOT`/`TEST_SUITE_RUNNING`, initializes `Zero\Core\Autoloader`, loads `.env` via `Env::load()`, overrides `DB_NAME` to an isolated `_test` database (suffixed with the `TEST_TOKEN` worker slot if set), calls `Emailer::enableTestMode()`, and defines the global `assert_test(bool $condition, string $message)` helper (a thin wrapper delegating to `TestRunner::assertTest()`).
* **No per-folder relay**: every test file requires `TestBootstrap.php` directly with a `dirname()` call sized to its own depth under `src/` — `dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php'` (2 levels) for component-level `Tests/` folders like `src/Core/Tests/`, or `dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php'` (3 levels) for module-level `Tests/` folders like `src/Modules/Blog/Tests/`. There used to be a thin `bootstrap.php` relay file in every `Tests/` folder forwarding to this same file, but it was collapsed as unnecessary indirection — one line inlined per test file was judged neater than a two-hop require through a same-purpose file in every folder.
* **Runner class**: `src/Support/TestRunner.php` (`Zero\Support\TestRunner`) — discovers every `*Test.php` file recursively under a given root (`src/`), runs them, and reports results. Exposes `TestRunner::run(string $root): int` and `TestRunner::assertTest(bool $condition, string $message): void`.
* **Entry point**: `bin/test` — a thin executable that boots the autoloader and calls `TestRunner::run(APPLICATION_ROOT . '/src', $collectCoverage)`, passing `true` when invoked with `--coverage`.
* **Coverage collector**: `src/Support/CoverageRecorder.php` (`Zero\Support\CoverageRecorder`) plus the procedural `src/Support/CoveragePrepend.php` shim it auto-prepends. Only used by `bin/test --coverage`.

## Execution model

* `bin/test` recursively scans `src/` for every file ending in `Test.php` (this single scan naturally covers both component-level and module-level `Tests/` folders).
* Each discovered test file is run in its own **isolated PHP subprocess** via `proc_open`, not sequential `exec()`. A small pool of `TEST_TOKEN` worker slots (sized to detected CPU cores, capped at 8) is reused across the queue — several subprocesses run **concurrently**, each getting its own isolated test database (`{db}_test_{token}`) so parallel runs never collide.
* `[PASS]`/`[FAIL]` progress lines print the instant each subprocess completes, in completion order (not queue order) — this is expected, not a bug, when comparing two runs' output.
* A failing suite's full captured stdout+stderr is dumped immediately to assist debugging, followed by a final summary box: suite counts, total assertion count (counted by scanning subprocess output for `PASS:`/`FAIL:` substrings), and a `GRAND STATUS: SUCCESS`/`FAILURE` banner.
* `assert_test()` (and the underlying `TestRunner::assertTest()`) exits the current subprocess with status code `1` on the first failing assertion — it does not accumulate multiple failures within one test file.

## Measuring coverage

```bash
docker exec -w /data/misc/zero php83 php bin/test --coverage
```

Prints the usual test summary, then a line-coverage summary (overall, per component, least-covered files), and writes the full per-file report to `storage/coverage/coverage.json`. Requires the Xdebug extension; the runner refuses to proceed rather than reporting a misleading 0% if it's missing.

Coverage is recorded across the **entire process tree**, not just the test process. This matters because the suite is subprocess-based and several tests spawn subprocesses of their own — `ApiControllerTest.php` pipes code to a fresh `php` over stdin, `SeederScriptTest.php` `shell_exec`s `bin/seed`. A single-process collector misses all of that: it reported `ApiController.php` at 3% (really 100%), `SeederRunner.php` at 13% (really 97.5%), and all 30 migration files as never loaded (really 98.2% covered, since their `up()`/`down()` runs inside `bin/seed`). `CoverageRecorder::prepare()` writes a scratch php.ini fragment into `storage/coverage/ini` that sets `auto_prepend_file`, and exposes it through `PHP_INI_SCAN_DIR` — which is inherited by every descendant process, so each one records and dumps its own hit map for the parent to merge.

Test scaffolding (`*Test.php`, `TestBootstrap.php`, `TestRunner.php`, and the two coverage files) is excluded from the figures, so the number describes the product rather than the harness.

## Adding a new test

1. Create `src/<Component>/Tests/YourThingTest.php` (or `src/Modules/<Module>/Tests/YourThingTest.php`), starting with a direct require to the shared bootstrap — copy the exact `dirname()` depth from a sibling test file in the same folder (2 levels for component-level `Tests/`, 3 for module-level `Tests/`).
2. Call `assert_test($condition, $message)` for each check — no other assertion API exists.
3. Run `docker exec -w /data/misc/zero php83 bin/test` to confirm the new suite is discovered and passes alongside everything else.

## The Continuous Integration Mandate

Whenever ANY changes are made to the codebase (controllers, models, styles, views, or database tables), the test runner MUST be executed to verify no regressions have been introduced:

```bash
docker exec -w /data/misc/zero php83 bin/test
```

All suites **MUST** return a clean `GRAND STATUS: SUCCESS` state before any work is considered complete or ready for staging/deployment. This same command runs in CI (`.github/workflows/run-tests.yml`).
