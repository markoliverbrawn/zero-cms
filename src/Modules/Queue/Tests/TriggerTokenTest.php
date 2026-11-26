<?php
// src/Modules/Queue/Tests/TriggerTokenTest.php
// Regression test: the queue and scheduler trigger endpoints accept their token only in a header.
// A ?token= query string used to be accepted as a deprecated fallback; tokens in URLs end up in
// request logs, proxy logs and Referer headers, so it is now ignored.

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Support\TestRequest;

echo "=== Queue/Scheduler Trigger Token Tests ===\n";

$endpoints = [
    'queue' => ['/api/v1/queue/process', 'QUEUE_TRIGGER_TOKEN', 'HTTP_X_QUEUE_TOKEN'],
    'scheduler' => ['/api/v1/queue/schedule', 'SCHEDULER_TRIGGER_TOKEN', 'HTTP_X_SCHEDULER_TOKEN'],
];

foreach ($endpoints as $label => [$path, $envName, $header]) {
    $token = 'trigger-test-' . \bin2hex(\random_bytes(8));
    $originalToken = \getenv($envName);
    \putenv("{$envName}={$token}");

    echo "Testing the {$label} trigger...\n";

    $viaQuery = TestRequest::post($path)->onSite()->withQuery(['token' => $token])->send();
    assert_test(\strpos($viaQuery['stdout'], 'Forbidden') !== false, "The {$label} trigger refuses a correct token sent as ?token=");

    $wrongHeader = TestRequest::post($path)->onSite()->withServer([$header => 'wrong-' . $token])->send();
    assert_test(\strpos($wrongHeader['stdout'], 'Forbidden') !== false, "The {$label} trigger refuses a wrong token in the header");

    $viaHeader = TestRequest::post($path)->onSite()->withServer([$header => $token])->send();
    assert_test(
        $viaHeader['exit_code'] === 0 && \strpos($viaHeader['stdout'], 'Forbidden') === false,
        "The {$label} trigger accepts the correct token in the header"
    );

    \putenv($originalToken === false ? $envName : "{$envName}={$originalToken}");
}

echo "Trigger token tests completed.\n";
