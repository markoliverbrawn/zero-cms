<?php
// src/Support/Tests/SubprocessEnvironmentTest.php
// Regression test: test subprocesses must inherit exported variables even under
// variables_order=GPCS (Debian's php-cli default), where $_ENV is empty. The runner and TestRequest
// used to pass $_ENV to proc_open(), so CI's ENVIRONMENT/APP_KEY/DB_* never reached the tests.
// Also checks that bin/test --ci really starts test processes under those CI-like conditions.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\TestRunner;

echo "=== Test Subprocess Environment Tests ===\n";

echo "Testing subprocessEnvironment() includes variables missing from \$_ENV...\n";
\putenv('ZERO_SUBPROCESS_ENV_PROBE=from-getenv');
unset($_ENV['ZERO_SUBPROCESS_ENV_PROBE']);
$env = TestRunner::subprocessEnvironment();
assert_test(($env['ZERO_SUBPROCESS_ENV_PROBE'] ?? null) === 'from-getenv', "A variable only in the process environment is forwarded");

$_ENV['ZERO_SUBPROCESS_ENV_BOOTSTRAP'] = 'from-env-array';
$env = TestRunner::subprocessEnvironment(['ZERO_SUBPROCESS_ENV_PROBE' => 'overridden']);
assert_test(($env['ZERO_SUBPROCESS_ENV_BOOTSTRAP'] ?? null) === 'from-env-array', "A variable a bootstrap set in \$_ENV is forwarded");
assert_test(($env['ZERO_SUBPROCESS_ENV_PROBE'] ?? null) === 'overridden', "\$extra overrides inherited values");
unset($_ENV['ZERO_SUBPROCESS_ENV_BOOTSTRAP']);

echo "Testing a GPCS child still forwards the environment to its own subprocess...\n";
// The child runs with an empty $_ENV, like CI. It starts a grandchild twice -- once with $_ENV
// (the old behaviour) and once with subprocessEnvironment() -- and reports what each one saw.
$childScript = '<?php
define("APPLICATION_ROOT", ' . \var_export(APPLICATION_ROOT, true) . ');
require APPLICATION_ROOT . "/src/Core/Autoloader.php";
\Zero\Core\Autoloader::init();
$grandchild = function (array $env): string {
    $p = \proc_open(["php", "-r", "echo getenv(\"ZERO_SUBPROCESS_ENV_PROBE\") ?: \"<missing>\";"], [1 => ["pipe", "w"]], $pipes, null, $env);
    $out = \stream_get_contents($pipes[1]);
    \proc_close($p);
    return $out;
};
echo \count($_ENV) === 0 ? "empty" : "populated", "|", $grandchild($_ENV), "|", $grandchild(\Zero\Support\TestRunner::subprocessEnvironment());
';
$process = \proc_open(['php', '-d', 'variables_order=GPCS'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, APPLICATION_ROOT);
\fwrite($pipes[0], $childScript);
\fclose($pipes[0]);
$output = (string)\stream_get_contents($pipes[1]);
$errors = (string)\stream_get_contents($pipes[2]);
\proc_close($process);
[$envState, $viaEnvArray, $viaHelper] = \array_pad(\explode('|', $output), 3, '');

assert_test($envState === 'empty', "The GPCS child sees an empty \$_ENV (reproduces CI)" . ($envState !== 'empty' ? ' -- child output: ' . \trim($output . $errors) : ''));
assert_test($viaEnvArray === '<missing>', "Passing \$_ENV from a GPCS process drops exported variables (the old bug)");
assert_test($viaHelper === 'from-getenv', "subprocessEnvironment() forwards exported variables from a GPCS process");

\putenv('ZERO_SUBPROCESS_ENV_PROBE');

echo "Testing bin/test --ci starts test processes under CI-like conditions...\n";
$ciEnvironment = new ReflectionMethod(TestRunner::class, 'ciEnvironment');
$ciEnvironment->setAccessible(true);
$ciDir = \sys_get_temp_dir() . '/zero-test-ci-probe-' . \getmypid();
$ciEnv = $ciEnvironment->invoke(null, $ciDir, []);
$probe = \proc_open(
    ['php', '-r', 'echo ini_get("variables_order"), "|", getenv("ENVIRONMENT"), "|", strlen((string) getenv("APP_KEY"));'],
    [1 => ['pipe', 'w']],
    $probePipes,
    null,
    TestRunner::subprocessEnvironment($ciEnv)
);
$probeOutput = (string)\stream_get_contents($probePipes[1]);
\proc_close($probe);
@\unlink($ciDir . '/zz-zero-test-ci.ini');
@\rmdir($ciDir);
assert_test($probeOutput === 'GPCS|testing|64', "A --ci test process runs with variables_order=GPCS, ENVIRONMENT=testing and an APP_KEY (got: {$probeOutput})");

