<?php
// src/Support/Tests/SecurityScanTest.php
// Rules behind bin/security-scan. Each rule is checked against the vulnerable shape of a real bug
// fixed in core (it must be reported) and against the fixed shape (it must not be), plus ignore
// comments, baselines and the cross-file route rule.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\SecurityScan\Finding;
use Zero\Support\SecurityScan\Scanner;
use Zero\Support\SecurityScan\SourceFile;

echo "=== Security Scan Rule Tests ===\n";

/**
 * Scan in-memory sources ([path => code]) and return "rule@path:line" strings.
 */
$scan = function (array $sources, array $baseline = []): array {
    $files = [];
    foreach ($sources as $path => $code) {
        $files[$path] = new SourceFile($path, $code);
    }
    return \array_map(
        static fn (Finding $f): string => "{$f->rule}@{$f->file}:{$f->line}",
        (new Scanner())->scanSources($files, $baseline)
    );
};

$has = fn (array $results, string $rule): bool => (bool) \array_filter($results, fn ($r) => \str_starts_with($r, $rule . '@'));

// ---------------------------------------------------------------- dynamic-php-path (High 3)
echo "Testing dynamic-php-path...\n";
$vulnerable = <<<'PHP'
<?php
foreach ($decodedBlocks as $block):
    $type = $block['type'] ?? '';
    $blockPath = $themeBlocksDir . '/blocks/' . $type . '.php';
    include $blockPath;
endforeach;
PHP;
$fixed = <<<'PHP'
<?php
foreach ($decodedBlocks as $block):
    $type = $block['type'] ?? '';
    if (!BlockHelper::isSafeBlockType($type)) { continue; }
    $blockPath = $themeBlocksDir . '/blocks/' . $type . '.php';
    include $blockPath;
endforeach;
PHP;
assert_test($has($scan(['src/Views/themes/x/post.php' => $vulnerable]), 'dynamic-php-path'), "An unvalidated block type in a .php path is reported");
assert_test(!$has($scan(['src/Views/themes/x/post.php' => $fixed]), 'dynamic-php-path'), "A validated block type is not reported");
assert_test(!$has($scan(['src/A.php' => "<?php \$f = \$dir . '/layout.php'; if (\$x === 'Seeder.php') {}"]), 'dynamic-php-path'), "Base-directory variables and standalone literals are not reported");
assert_test(!$has($scan(['src/A.php' => "<?php foreach (self::BLOCK_TYPES as \$type => \$c) { \$p = __DIR__ . '/' . \$type . '.php'; }"]), 'dynamic-php-path'), "A variable iterated from a class constant is trusted");

// ---------------------------------------------------------------- dynamic-class (#17 Page.controller)
echo "Testing dynamic-class...\n";
$vulnerable = <<<'PHP'
<?php
$controllerClass = $pageRecord->controller;
if (\class_exists($controllerClass)) {
    $controller = new $controllerClass();
}
PHP;
$fixed = <<<'PHP'
<?php
$controllerClass = $pageRecord->controller;
if (Page::isDispatchableController($controllerClass)) {
    $controller = new $controllerClass();
}
PHP;
assert_test($has($scan(['src/Http/Router.php' => $vulnerable]), 'dynamic-class'), "Instantiating a stored class name after only class_exists() is reported");
assert_test(!$has($scan(['src/Http/Router.php' => $fixed]), 'dynamic-class'), "A class name validated against the expected interface is not reported");
assert_test(!$has($scan(['src/A.php' => "<?php \$model = App::getModelClass(\$name); \$r = \$model::find(1);"]), 'dynamic-class'), "A class from a registry getter is trusted");

// ---------------------------------------------------------------- host-header-link (High 1)
echo "Testing host-header-link...\n";
$vulnerable = <<<'PHP'
<?php
$host = Security::resolveTrustedHost();
$link = $scheme . '://' . $host . '/admin/reset?token=' . $token;
Emailer::send($user['email'], $subject, $body);
PHP;
$fixed = <<<'PHP'
<?php
$link = $site->absoluteUrl('/admin/reset?token=' . $token);
Emailer::send($user['email'], $subject, $body);
PHP;
assert_test($has($scan(['src/C.php' => $vulnerable]), 'host-header-link'), "A host-derived link in a mail-sending file is reported");
assert_test(!$has($scan(['src/C.php' => $fixed]), 'host-header-link'), "A link built from the stored site domain is not reported");
assert_test(!$has($scan(['src/C.php' => "<?php \$h = \$_SERVER['HTTP_HOST'];"]), 'host-header-link'), "Reading the host in a file that sends no mail is not reported");

// ---------------------------------------------------------------- unscoped-lookup (#18)
echo "Testing unscoped-lookup...\n";
assert_test($has($scan(['src/Modules/Admin/Controllers/ModelController.php' => "<?php \$record = \$model::find(\$id);"]), 'unscoped-lookup'), "find(\$id) in a controller is reported");
assert_test(!$has($scan(['src/Modules/Admin/Controllers/ModelController.php' => "<?php \$record = \$model::findInCurrentSite(\$id);"]), 'unscoped-lookup'), "findInCurrentSite(\$id) is not reported");
assert_test(!$has($scan(['src/Models/Thing.php' => "<?php \$record = static::find(\$id);"]), 'unscoped-lookup'), "find() outside controllers is not reported");

// ---------------------------------------------------------------- unescaped-output (High 4)
echo "Testing unescaped-output...\n";
$vulnerable = <<<'PHP'
<div data-duration="<?php echo $duration; ?>">
  <div class="quote"><?php echo $item['content'] ?? ''; ?></div>
  <img src="<?php echo Assets::url($mediaUrl, width: 640); ?>">
</div>
PHP;
$fixed = <<<'PHP'
<div data-duration="<?php echo (int) $duration; ?>">
  <div class="quote"><?php echo Security::sanitizeHtml((string) ($item['content'] ?? '')); ?></div>
  <img src="<?php echo Str::escape(Assets::url($mediaUrl, width: 640)); ?>">
  <p class="<?php echo $active ? 'on' : 'off'; ?>"><?= $count + 1 ?> <?php echo App::makeFormField('text', 'x', [])->render(); ?></p>
</div>
PHP;
$results = $scan(['src/Modules/Admin/Views/blocks/frontend/testimonials.php' => $vulnerable]);
assert_test(\count(\array_filter($results, fn ($r) => \str_starts_with($r, 'unescaped-output@'))) === 3, "Raw variable, raw array value and an unescaped Assets::url() are each reported");
assert_test(!$has($scan(['src/Modules/Admin/Views/blocks/frontend/testimonials.php' => $fixed]), 'unescaped-output'), "Escaped, sanitised, cast, literal-ternary, arithmetic and builder output is not reported");
assert_test(!$has($scan(['src/Support/NotAView.php' => "<?php echo \$x;"]), 'unescaped-output'), "Non-view files are not checked for output escaping");

// ---------------------------------------------------------------- sql-interpolation
echo "Testing sql-interpolation...\n";
assert_test($has($scan(['src/A.php' => "<?php DB::query(\"SELECT * FROM t ORDER BY {\$sort}\");"]), 'sql-interpolation'), "An interpolated variable in SQL text is reported");
assert_test(!$has($scan(['src/A.php' => "<?php if (!Security::isKnownSqlColumn('t', \$sort)) { return; } DB::query(\"SELECT * FROM t ORDER BY {\$sort}\");"]), 'sql-interpolation'), "A column checked by isKnownSqlColumn() is not reported");
assert_test(!$has($scan(['src/A.php' => "<?php DB::query(\"SELECT * FROM \" . static::\$tableName . \" WHERE id = ?\", [\$id]);"]), 'sql-interpolation'), "Bound values and static table names are not reported");

// ---------------------------------------------------------------- timing-compare / object-injection
echo "Testing timing-compare and object-injection...\n";
assert_test($has($scan(['src/A.php' => "<?php if (\$requestToken !== \$envToken) {}"]), 'timing-compare'), "Comparing two tokens with !== is reported");
assert_test(!$has($scan(['src/A.php' => "<?php if (!\\hash_equals(\$envToken, \$requestToken)) {} if (\$token === '') {}"]), 'timing-compare'), "hash_equals() and comparison with a literal are not reported");
assert_test($has($scan(['src/A.php' => "<?php \$x = unserialize(\$raw);"]), 'object-injection'), "unserialize() without allowed_classes is reported");
assert_test(!$has($scan(['src/A.php' => "<?php \$x = unserialize(\$raw, ['allowed_classes' => false]);"]), 'object-injection'), "unserialize() with allowed_classes is not reported");

// ---------------------------------------------------------------- unauthenticated-route (High 2)
echo "Testing unauthenticated-route...\n";
$module = <<<'PHP'
<?php
namespace Zero\Modules\Admin;
class Module {
    public function getRoutes(): array {
        return [
            '#^/api/v1/admin/files/?$#' => Controllers\Api\FilesApiController::class,
            '#^/admin/dashboard$#' => Controllers\DashboardController::class,
        ];
    }
}
PHP;
$sessionOnlyBase = <<<'PHP'
<?php
namespace Zero\Modules\Admin\Controllers\Api;
abstract class AdminApiControllerBase {
    protected function authenticate(): array { if (empty($_SESSION['user_id'])) { exit; } return []; }
}
PHP;
$checkingBase = <<<'PHP'
<?php
namespace Zero\Modules\Admin\Controllers\Api;
abstract class AdminApiControllerBase {
    protected function authenticate(): array { if (!Permissions::roleHas($role, 'backoffice.access')) { exit; } return []; }
}
PHP;
$files = "<?php\nnamespace Zero\\Modules\\Admin\\Controllers\\Api;\nclass FilesApiController extends AdminApiControllerBase { public function handle(\$m) { \$this->authenticate(); } }";
$dashboard = "<?php\nnamespace Zero\\Modules\\Admin\\Controllers;\nclass DashboardController { public function handle(\$m) { App::applyAuthMiddleware(); } }";
$sources = [
    'src/Modules/Admin/Module.php' => $module,
    'src/Modules/Admin/Controllers/Api/FilesApiController.php' => $files,
    'src/Modules/Admin/Controllers/DashboardController.php' => $dashboard,
];
$vulnerable = $scan($sources + ['src/Modules/Admin/Controllers/Api/AdminApiControllerBase.php' => $sessionOnlyBase]);
assert_test(\in_array('unauthenticated-route@src/Modules/Admin/Module.php:6', $vulnerable, true), "An admin API whose base class only checks for a session is reported at its route");
assert_test(!\in_array('unauthenticated-route@src/Modules/Admin/Module.php:7', $vulnerable, true), "A route whose controller applies the auth middleware is not reported");
assert_test(!$has($scan($sources + ['src/Modules/Admin/Controllers/Api/AdminApiControllerBase.php' => $checkingBase]), 'unauthenticated-route'), "An access check inherited from the base class satisfies the rule");

// ---------------------------------------------------------------- ignores and baselines
echo "Testing ignore comments and baselines...\n";
$code = "<?php\n// security-scan-ignore: timing-compare -- both values are public build ids\nif (\$tokenA === \$tokenB) {}";
assert_test(!$has($scan(['src/A.php' => $code]), 'timing-compare'), "An ignore comment with a reason silences the next line");
$code = "<?php\n// security-scan-ignore: timing-compare\nif (\$tokenA === \$tokenB) {}";
$results = $scan(['src/A.php' => $code]);
assert_test($has($results, 'timing-compare') && $has($results, 'invalid-ignore'), "An ignore comment without a reason silences nothing and is itself reported");
$code = "<?php\n// security-scan-ignore: sql-interpolation -- wrong rule\nif (\$tokenA === \$tokenB) {}";
assert_test($has($scan(['src/A.php' => $code]), 'timing-compare'), "An ignore comment only silences the rules it names");

$files = ['src/A.php' => new SourceFile('src/A.php', "<?php\nif (\$tokenA === \$tokenB) {}")];
$fingerprints = \array_map(static fn (Finding $f): string => $f->fingerprint(), (new Scanner())->scanSources($files));
assert_test((new Scanner())->scanSources($files, $fingerprints) === [], "A baselined finding is suppressed");
$shifted = ['src/A.php' => new SourceFile('src/A.php', "<?php\n\n\nif (\$tokenA === \$tokenB) {}")];
assert_test((new Scanner())->scanSources($shifted, $fingerprints) === [], "Baseline fingerprints survive the line moving");

// ---------------------------------------------------------------- CLI
echo "Testing the CLI exit codes...\n";
$dir = \sys_get_temp_dir() . '/zero-scan-' . \bin2hex(\random_bytes(4));
\mkdir($dir . '/src', 0777, true);
\file_put_contents($dir . '/src/A.php', "<?php if (\$tokenA === \$tokenB) {}");
$bin = \escapeshellarg(APPLICATION_ROOT . '/bin/security-scan');
\exec('php ' . $bin . ' --root=' . \escapeshellarg($dir) . ' src 2>&1', $out, $dirtyExit);
\exec('php ' . $bin . ' --root=' . \escapeshellarg($dir) . ' --write-baseline=' . \escapeshellarg($dir . '/baseline.json') . ' src 2>&1', $out, $writeExit);
\exec('php ' . $bin . ' --root=' . \escapeshellarg($dir) . ' --baseline=' . \escapeshellarg($dir . '/baseline.json') . ' src 2>&1', $out, $cleanExit);
assert_test($dirtyExit === 1, "The CLI exits 1 when there are findings");
assert_test($writeExit === 0 && \is_file($dir . '/baseline.json'), "The CLI writes a baseline");
assert_test($cleanExit === 0, "The CLI exits 0 when every finding is baselined");
\array_map('unlink', [$dir . '/src/A.php', $dir . '/baseline.json']);
\rmdir($dir . '/src');
\rmdir($dir);

echo "Security scan rule tests completed successfully!\n";
