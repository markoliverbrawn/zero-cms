---
name: test-suite-architecture
description: Explains Zero CMS's zero-dependency automated test suite — how tests are discovered, run in parallel isolated subprocesses, and where new tests should be added. Use when adding a new *Test.php file, modifying bin/test or src/Support/TestRunner.php/TestBootstrap.php, or investigating a test failure/CI run.
---

# Zero-Dependency Automated Testing Suite

To ensure absolute system stability, security, and multi-tenant isolation without introducing bloated third-party frameworks (such as PHPUnit or Pest), Zero CMS features a custom zero-dependency test suite. There is no root `tests/` directory — every test file lives directly alongside the code it tests.

## Layout

* **Component tests**: `src/<Component>/Tests/*Test.php` (e.g. `src/Core/Tests/`, `src/Models/Tests/`, `src/Support/Tests/`, `src/Database/Tests/`, `src/Services/Tests/`). Cross-cutting tests that don't belong to one component live in `src/Integration/Tests/`.
* **Module tests**: `src/Modules/<Module>/Tests/*Test.php` (e.g. `src/Modules/Blog/Tests/`).
* **Shared bootstrap**: `src/Support/TestBootstrap.php` — the one file every test requires directly. It starts the session, defines `APPLICATION_ROOT`/`TEST_SUITE_RUNNING`, initializes `Zero\Core\Autoloader`, loads `.env` via `Env::load()`, overrides `DB_NAME` to an isolated `_test` database (suffixed with the `TEST_TOKEN` worker slot if set), calls `Emailer::enableTestMode()`, and defines the global `assert_test(bool $condition, string $message)` and `assert_critical(bool $condition, string $message)` helpers (thin wrappers delegating to `TestRunner::assertTest()`).
* **No per-folder relay**: every test file requires `TestBootstrap.php` directly with a `dirname()` call sized to its own depth under `src/` — `dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php'` (2 levels) for component-level `Tests/` folders like `src/Core/Tests/`, or `dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php'` (3 levels) for module-level `Tests/` folders like `src/Modules/Blog/Tests/`. There used to be a thin `bootstrap.php` relay file in every `Tests/` folder forwarding to this same file, but it was collapsed as unnecessary indirection — one line inlined per test file was judged neater than a two-hop require through a same-purpose file in every folder.
* **Runner class**: `src/Support/TestRunner.php` (`Zero\Support\TestRunner`) — discovers every `*Test.php` file recursively under a given root (`src/`), runs them, and reports results. Exposes `TestRunner::run(string $root, bool $collectCoverage = false): int` and `TestRunner::assertTest(bool $condition, string $message, bool $halt = false): void`.
* **Entry point**: `bin/test` — a thin executable that boots the autoloader and calls `TestRunner::run(APPLICATION_ROOT . '/src', $collectCoverage)`, passing `true` when invoked with `--coverage`.
* **Coverage collector**: `src/Support/CoverageRecorder.php` (`Zero\Support\CoverageRecorder`) plus the procedural `src/Support/CoveragePrepend.php` shim it auto-prepends. Only used by `bin/test --coverage`.

## Execution model

* `bin/test` recursively scans `src/` for every file ending in `Test.php` (this single scan naturally covers both component-level and module-level `Tests/` folders).
* Each discovered test file is run in its own **isolated PHP subprocess** via `proc_open`, not sequential `exec()`. A small pool of `TEST_TOKEN` worker slots (sized to detected CPU cores, capped at 8) is reused across the queue — several subprocesses run **concurrently**, each getting its own isolated test database (`{db}_test_{token}`) so parallel runs never collide.
* `[PASS]`/`[FAIL]` progress lines print the instant each subprocess completes, in completion order (not queue order) — this is expected, not a bug, when comparing two runs' output.
* A failing suite's full captured stdout+stderr is dumped immediately to assist debugging, followed by a final summary box: suite counts, total assertion count (counted by scanning subprocess output for `PASS:`/`FAIL:` substrings), and a `GRAND STATUS: SUCCESS`/`FAILURE` banner.
* `assert_test()` (and the underlying `TestRunner::assertTest()`) **accumulates** failures: a failing assertion is recorded and printed, and the file keeps running, so one run reports every broken assertion rather than only the first. The subprocess still exits with status code `1` if anything failed — set by a shutdown handler registered on the first failure, which also prints an `N of M assertions failed in this suite` recap listing each one. Because shutdown handlers run on fatal errors too, that recap and the non-zero exit survive a crash triggered by a later assertion.
* `assert_critical()` is the opt-out: it reports the failure and aborts the file immediately. Reserve it for preconditions whose failure would make every later assertion meaningless (no database connection, a fixture that never got created).

## Measuring coverage

```bash
docker exec -w /data/misc/zero php83 php bin/test --coverage
```

Prints the usual test summary, then a line-coverage summary (overall, per component, least-covered files), and writes the full per-file report to `storage/coverage/coverage.json`. Requires the Xdebug extension; the runner refuses to proceed rather than reporting a misleading 0% if it's missing.

Coverage is recorded across the **entire process tree**, not just the test process. This matters because the suite is subprocess-based and several tests spawn subprocesses of their own — `ApiControllerTest.php` pipes code to a fresh `php` over stdin, `SeederScriptTest.php` `shell_exec`s `bin/seed`. A single-process collector misses all of that: it reported `ApiController.php` at 3% (really 100%), `SeederRunner.php` at 13% (really 97.5%), and all 30 migration files as never loaded (really 98.2% covered, since their `up()`/`down()` runs inside `bin/seed`). `CoverageRecorder::prepare()` writes a scratch php.ini fragment into `storage/coverage/ini` that sets `auto_prepend_file`, and exposes it through `PHP_INI_SCAN_DIR` — which is inherited by every descendant process, so each one records and dumps its own hit map for the parent to merge.

Test scaffolding is excluded from the figures, so the number describes the product rather than the harness: `*Test.php` plus the basenames listed in `CoverageRecorder::EXCLUDED_BASENAMES`. **Add new test infrastructure to that constant when it lands under `src/`**, or it quietly dilutes the percentage.

## Testing a controller through the real request pipeline

Use `Zero\Support\TestRequest` (`src/Support/TestRequest.php`) rather than hand-building a subprocess. It drives a genuine routed request — `App::handleRequest()` → `Router` → `HandlesRequests` → the controller → `RendersViews` — together with the tenant fixtures the request needs:

```php
$response = TestRequest::get('/admin/login')
    ->onSite(['enabled_modules' => ['security']])
    ->send();

assert_test(str_contains($response['stdout'], '<form'), "Sign-in form renders");
```

`send()` returns `['stdout', 'stderr', 'exit_code']`. Available builders:

* `onSite([...])` — create the tenant and target the request at it. Invents a unique domain (and matching `Host`) unless you pass `domain`, so parallel worker slots sharing a database cannot collide.
* `withPage([...])` / `withHomepage([...])` — create a page owned by that tenant; the latter also sets `sites.homepage_id`.
* `asUser([...])` — create a user **and sign them in**, for routes behind `AuthMiddleware`.
* `withUser([...])` — create the user but leave the request anonymous. This is what a sign-in test wants: an authenticated session makes `LoginController` redirect to the dashboard before it ever evaluates the submitted credentials.
* `withCsrf()` — issue a valid CSRF token into both session and body, so a `POST` behind `CsrfMiddleware` is reachable. Omit it deliberately to assert the rejection branch.
* `withQuery([...])` / `withServer([...])` — `$_GET` entries and `$_SERVER` headers (e.g. `HTTP_AUTHORIZATION`, `HTTP_X_API_KEY`).

Two things worth knowing. The request runs in a subprocess because the pipeline reads superglobals, resolves the tenant from `HTTP_HOST` during bootstrap, and ends in output plus an `exit` — but `$_ENV` is forwarded, so coverage still sees it. And user fixtures hard-delete any row sharing the username or email first, because `users.username`/`users.email` are globally unique (not per-tenant), which would otherwise make any sign-in test fail on its second run.

See `src/Modules/Admin/Tests/LoginControllerTest.php` for a worked example covering render, CSRF rejection, each credential-failure branch, and success.

## Adding a new test

1. Create `src/<Component>/Tests/YourThingTest.php` (or `src/Modules/<Module>/Tests/YourThingTest.php`), starting with a direct require to the shared bootstrap — copy the exact `dirname()` depth from a sibling test file in the same folder (2 levels for component-level `Tests/`, 3 for module-level `Tests/`).
2. Call `assert_test($condition, $message)` for each check. Reach for `assert_critical($condition, $message)` only for a precondition that makes the rest of the file pointless if it fails. There is no other assertion API.
3. Run `docker exec -w /data/misc/zero php83 bin/test` to confirm the new suite is discovered and passes alongside everything else.

## The Continuous Integration Mandate

Whenever ANY changes are made to the codebase (controllers, models, styles, views, or database tables), the test runner MUST be executed to verify no regressions have been introduced:

```bash
docker exec -w /data/misc/zero php83 bin/test
```

All suites **MUST** return a clean `GRAND STATUS: SUCCESS` state before any work is considered complete or ready for staging/deployment. This same command runs in CI (`.github/workflows/run-tests.yml`).
