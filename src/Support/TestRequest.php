<?php

declare(strict_types=1);

/**
 * File: src/Support/TestRequest.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support;

use Zero\Core\App;
use Zero\Database\DB;

/**
 * Class TestRequest
 *
 * Fluent helper for driving a real HTTP request end to end through the routing spine
 * (App::handleRequest() -> Router -> HandlesRequests -> a controller -> RendersViews) from inside a
 * test, together with the tenant fixtures that request needs to resolve against.
 *
 * Why this exists: the request pipeline can only be exercised honestly in a fresh process, because
 * it reads superglobals, resolves the tenant from HTTP_HOST during App::bootstrap(), and ends in
 * output plus an exit. Tests were therefore hand-rolling the same ~50 lines each time -- building a
 * PHP source string, proc_open()ing `php`, piping the script over stdin, and draining the pipes --
 * and hand-escaping SQL nested two string levels deep to seed fixtures inside it, which is how a
 * fixture ends up needing a quadruple-backslashed class name to insert one row.
 *
 * This class keeps that subprocess model, since it is the only way to get a truthful request, but
 * inverts what crosses the boundary: the generated child script contains no logic and no SQL, only a
 * var_export()ed specification array and a single call back into runChildRequest(). Fixtures are
 * declared as plain PHP arrays and inserted through bound parameters in the child, so there is no
 * nested quoting to get wrong.
 *
 * Usage:
 *
 *   $response = TestRequest::get('/')
 *       ->onSite(['enabled_modules' => ['security']])
 *       ->withHomepage(['title' => 'Mock Homepage'])
 *       ->send();
 *
 *   assert_test(str_contains($response['stdout'], 'Mock Homepage'), 'Homepage renders');
 *
 * onSite() invents a unique domain and points the request's Host header at it unless a domain is
 * given explicitly, so concurrently-running worker slots sharing a database cannot collide on it.
 * Fixture rows are deliberately left in place after the request rather than cleaned up, so a test
 * can assert on what the request wrote; pass an explicit domain when a test needs a fixed host.
 */
final class TestRequest
{
    /**
     * Whether the created user should also be signed in for the request.
     *
     * @var bool
     */
    private bool $authenticateUser = false;

    /**
     * Attributes of the user to create, or null when no user is needed.
     *
     * @var array<string, mixed>|null
     */
    private ?array $user = null;

    /**
     * $_POST payload for the request.
     *
     * @var array<string, mixed>
     */
    private array $body;

    /**
     * Whether to issue a valid CSRF token for this request.
     *
     * @var bool
     */
    private bool $csrf = false;

    /**
     * HTTP method placed into $_SERVER['REQUEST_METHOD'].
     *
     * @var string
     */
    private string $method;

    /**
     * Page rows to create before the request runs, in declaration order.
     *
     * @var array<int, array{attributes: array<string, mixed>, homepage: bool}>
     */
    private array $pages = [];

    /**
     * $_GET payload for the request.
     *
     * @var array<string, mixed>
     */
    private array $query = [];

    /**
     * Explicit $_SERVER overrides, applied last so a test can always win.
     *
     * @var array<string, string>
     */
    private array $server = [];

    /**
     * Attributes of the tenant site to create, or null to run without creating one.
     *
     * @var array<string, mixed>|null
     */
    private ?array $site = null;

    /**
     * Request URI placed into $_SERVER['REQUEST_URI'].
     *
     * @var string
     */
    private string $uri;

    /**
     * @param string $method HTTP method.
     * @param string $uri Request URI, including any query string.
     * @param array<string, mixed> $body $_POST payload.
     */
    private function __construct(string $method, string $uri, array $body = [])
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->body = $body;
    }

    /**
     * Create a user and sign them in for this request, so routes behind AuthMiddleware are reachable.
     *
     * The user is written to the tenant created by onSite() and signed in by seeding
     * $_SESSION['user_id'], which is what AuthMiddleware resolves against.
     *
     * Use withUser() instead when the request should stay anonymous but the account must exist --
     * testing sign-in itself, for example, where an authenticated session makes LoginController
     * redirect to the dashboard before it ever evaluates the submitted credentials.
     *
     * Recognised attributes: username, email, password, role, api_token. Anything omitted is
     * defaulted; role defaults to 'super_admin' so a test opts in to weaker roles deliberately
     * rather than tripping over an authorisation failure it did not mean to exercise.
     *
     * @param array<string, mixed> $attributes Column overrides for the users row.
     * @return self
     */
    public function asUser(array $attributes = []): self
    {
        $this->user = $attributes;
        $this->authenticateUser = true;

        return $this;
    }

    /**
     * Begin a GET request.
     *
     * @param string $uri Request URI.
     * @return self
     */
    public static function get(string $uri): self
    {
        return new self('GET', $uri);
    }

    /**
     * Create a tenant site for the request to resolve against, and target the request at it.
     *
     * Recognised attributes: name, domain, theme, enabled_modules (array or JSON string), timezone,
     * default_language, settings. A unique domain is generated when none is supplied, and the
     * request's Host header follows whichever domain is used.
     *
     * @param array<string, mixed> $attributes Column overrides for the sites row.
     * @return self
     */
    public function onSite(array $attributes = []): self
    {
        $this->site = $attributes;

        return $this;
    }

    /**
     * Begin a POST request.
     *
     * A route behind CsrfMiddleware also needs withCsrf(); without it the request is rejected at the
     * middleware and never reaches the controller, which is itself a branch worth asserting on.
     *
     * @param string $uri Request URI.
     * @param array<string, mixed> $body $_POST payload.
     * @return self
     */
    public static function post(string $uri, array $body = []): self
    {
        return new self('POST', $uri, $body);
    }

    /**
     * Entry point invoked inside the spawned child process. Materialises the declared fixtures,
     * populates the superglobals, boots the application, and dispatches the request.
     *
     * Public only because the generated child script has to call it; it is meaningless in the parent
     * process, where App::bootstrap() has typically already run against a different tenant.
     *
     * @param array $specification Structure produced by specification().
     * @return void
     */
    public static function runChildRequest(array $specification): void
    {
        $siteId = null;

        if ($specification['site'] !== null) {
            $siteId = self::insertSite($specification['site']);
        }

        foreach ($specification['pages'] as $page) {
            $pageId = self::insertPage($page['attributes'], $siteId);

            if ($page['homepage'] && $siteId !== null) {
                DB::query('UPDATE sites SET homepage_id = ? WHERE id = ?', [$pageId, $siteId]);
            }
        }

        if ($specification['user'] !== null) {
            $userId = self::insertUser($specification['user'], $siteId);

            if ($specification['authenticate']) {
                App::ensureSession();
                $_SESSION['user_id'] = $userId;
            }
        }

        $_GET = $specification['query'];
        $_POST = $specification['body'];
        $_SERVER = \array_merge($_SERVER, $specification['server']);

        if ($specification['csrf'] !== null) {
            // Seeded exactly the way Security::csrfToken() would have during a real form render, so
            // CsrfMiddleware performs its genuine hash_equals check rather than being bypassed.
            App::ensureSession();
            $_SESSION['_csrf_token'] = $specification['csrf'];
            $_SESSION['_csrf_token_time'] = \time();
            $_POST['csrf'] = $specification['csrf'];
        }

        // Ordering is load-bearing: bootstrap() resolves the active tenant from HTTP_HOST, so every
        // superglobal has to be in place before it runs.
        App::bootstrap();
        App::handleRequest(APPLICATION_ROOT . '/public');
    }

    /**
     * Spawn the child process, run the request inside it, and return what it produced.
     *
     * $_ENV is handed to the child verbatim so the isolated test database token -- and the coverage
     * instrumentation variables set by CoverageRecorder when running under `bin/test --coverage` --
     * both survive into it. Without that, work done here would be invisible to coverage, which is
     * precisely the blind spot that made ApiController.php read as 3% covered when it was fully
     * exercised.
     *
     * @return array{stdout: string, stderr: string, exit_code: int}
     * @throws \RuntimeException If the child process cannot be spawned.
     */
    public function send(): array
    {
        $script = '<?php require_once ' . \var_export(APPLICATION_ROOT . '/src/Support/TestBootstrap.php', true) . ';'
            . ' \Zero\Support\TestRequest::runChildRequest(' . \var_export($this->specification(), true) . ');';

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes = [];
        $process = \proc_open('php', $descriptors, $pipes, APPLICATION_ROOT, $_ENV);

        if (!\is_resource($process)) {
            throw new \RuntimeException('TestRequest: could not spawn a PHP subprocess for the request.');
        }

        \fwrite($pipes[0], $script);
        \fclose($pipes[0]);

        $stdout = \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);

        $stderr = \stream_get_contents($pipes[2]);
        \fclose($pipes[2]);

        return [
            'stdout' => $stdout === false ? '' : $stdout,
            'stderr' => $stderr === false ? '' : $stderr,
            'exit_code' => \proc_close($process),
        ];
    }

    /**
     * Issue a valid CSRF token for this request, seeding it into the session and the POST body the
     * way a rendered form would, so a state-changing route behind CsrfMiddleware is reachable.
     *
     * @return self
     */
    public function withCsrf(): self
    {
        $this->csrf = true;

        return $this;
    }

    /**
     * Create a page for this request's tenant and mark it as the site's homepage.
     *
     * @param array<string, mixed> $attributes Column overrides for the pages row.
     * @return self
     */
    public function withHomepage(array $attributes = []): self
    {
        $this->pages[] = ['attributes' => $attributes, 'homepage' => true];

        return $this;
    }

    /**
     * Create a page for this request's tenant.
     *
     * Recognised attributes: title, slug, content, summary, type, controller, view, status,
     * precedence. Status defaults to 'published', since a draft is invisible to the router and a
     * test asking for a page almost always means a reachable one.
     *
     * @param array<string, mixed> $attributes Column overrides for the pages row.
     * @return self
     */
    public function withPage(array $attributes = []): self
    {
        $this->pages[] = ['attributes' => $attributes, 'homepage' => false];

        return $this;
    }

    /**
     * Merge entries into the request's $_GET payload.
     *
     * @param array<string, mixed> $query
     * @return self
     */
    public function withQuery(array $query): self
    {
        $this->query = \array_merge($this->query, $query);

        return $this;
    }

    /**
     * Create a user for this request's tenant without signing them in, leaving the request anonymous.
     *
     * This is what a sign-in test wants: the account has to exist for credentials to be checked
     * against, but an authenticated session would short-circuit the controller first.
     *
     * @param array<string, mixed> $attributes Column overrides for the users row.
     * @return self
     */
    public function withUser(array $attributes = []): self
    {
        $this->user = $attributes;
        $this->authenticateUser = false;

        return $this;
    }

    /**
     * Merge entries into the request's $_SERVER, for headers such as HTTP_AUTHORIZATION or
     * HTTP_X_API_KEY. Applied after the helper's own values, so a test can override anything.
     *
     * @param array<string, string> $server
     * @return self
     */
    public function withServer(array $server): self
    {
        $this->server = \array_merge($this->server, $server);

        return $this;
    }

    /**
     * Insert a pages row, returning its generated identifier.
     *
     * @param array<string, mixed> $attributes Column overrides.
     * @param string|null $siteId Owning tenant, or null for an unowned page.
     * @return string
     */
    private static function insertPage(array $attributes, ?string $siteId): string
    {
        $id = Security::uuidv7();
        $now = \gmdate('Y-m-d H:i:s');

        $title = $attributes['title'] ?? 'Test Page';
        $slug = $attributes['slug'] ?? 'test-page-' . \substr($id, -12);

        DB::query(
            'INSERT INTO pages (id, site_id, title, slug, content, summary, type, controller, view, status, precedence, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $siteId,
                $title,
                $slug,
                $attributes['content'] ?? '[]',
                $attributes['summary'] ?? null,
                $attributes['type'] ?? 'post',
                $attributes['controller'] ?? null,
                $attributes['view'] ?? null,
                $attributes['status'] ?? 'published',
                $attributes['precedence'] ?? 0,
                $now,
                $now,
            ]
        );

        return $id;
    }

    /**
     * Insert a sites row, returning its generated identifier.
     *
     * @param array<string, mixed> $attributes Column overrides, already carrying a resolved domain.
     * @return string
     */
    private static function insertSite(array $attributes): string
    {
        $id = Security::uuidv7();
        $now = \gmdate('Y-m-d H:i:s');

        $modules = $attributes['enabled_modules'] ?? [];
        if (\is_array($modules)) {
            $modules = \json_encode($modules);
        }

        DB::query(
            'INSERT INTO sites (id, name, domain, theme, enabled_modules, timezone, default_language, settings, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $attributes['name'] ?? 'Test Site',
                $attributes['domain'],
                $attributes['theme'] ?? 'default',
                $modules,
                $attributes['timezone'] ?? 'UTC',
                $attributes['default_language'] ?? 'en',
                $attributes['settings'] ?? null,
                $now,
                $now,
            ]
        );

        return $id;
    }

    /**
     * Insert a users row, returning its generated identifier.
     *
     * @param array<string, mixed> $attributes Column overrides.
     * @param string|null $siteId Owning tenant, or null for an unowned user.
     * @return string
     */
    private static function insertUser(array $attributes, ?string $siteId): string
    {
        $id = Security::uuidv7();
        $now = \gmdate('Y-m-d H:i:s');
        $suffix = \substr($id, -12);

        $username = $attributes['username'] ?? 'testuser_' . $suffix;
        $email = $attributes['email'] ?? 'testuser_' . $suffix . '@example.test';

        // users.username and users.email are globally unique -- not scoped per tenant -- so a test
        // that needs a known username (any sign-in test does) would collide with the row its own
        // previous run left behind. Clearing conflicts first makes fixtures idempotent and the suite
        // re-runnable. The delete is hard rather than soft because a soft-deleted row still occupies
        // the unique key.
        DB::query('DELETE FROM users WHERE username = ? OR email = ?', [$username, $email]);

        DB::query(
            'INSERT INTO users (id, site_id, username, email, password_hash, role, api_token, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $siteId,
                $username,
                $email,
                \password_hash($attributes['password'] ?? 'Secret123', PASSWORD_BCRYPT),
                $attributes['role'] ?? 'super_admin',
                $attributes['api_token'] ?? null,
                $now,
                $now,
            ]
        );

        return $id;
    }

    /**
     * Freeze the fluent state into the plain data structure handed to the child process, resolving
     * the tenant domain and the Host header the request will be resolved against.
     *
     * @return array{method: string, uri: string, server: array<string, string>, query: array<string, mixed>, body: array<string, mixed>, site: array<string, mixed>|null, pages: array<int, array{attributes: array<string, mixed>, homepage: bool}>, user: array<string, mixed>|null, authenticate: bool, csrf: string|null}
     */
    private function specification(): array
    {
        $site = $this->site;
        $host = 'tests.zero';

        if ($site !== null) {
            // A generated domain keeps parallel worker slots that share a database from colliding on
            // the sites.domain of a fixture they each create.
            $site['domain'] = $site['domain'] ?? 'test-' . \bin2hex(\random_bytes(6)) . '.zero';
            $host = $site['domain'];
        }

        $server = \array_merge(
            [
                'REQUEST_METHOD' => $this->method,
                'REQUEST_URI' => $this->uri,
                'HTTP_HOST' => $host,
                // Controllers audit-log the client IP on auth events; without a value here a test
                // would trip an undefined-index warning inside code that is behaving correctly.
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            $this->server
        );

        return [
            'method' => $this->method,
            'uri' => $this->uri,
            'server' => $server,
            'query' => $this->query,
            'body' => $this->body,
            'site' => $site,
            'pages' => $this->pages,
            'user' => $this->user,
            'authenticate' => $this->authenticateUser,
            'csrf' => $this->csrf ? \bin2hex(\random_bytes(16)) : null,
        ];
    }
}
