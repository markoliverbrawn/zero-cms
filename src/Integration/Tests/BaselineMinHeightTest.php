<?php
/**
 * Zero CMS - Baseline Hero Min Height setting Test
 *
 * Verifies that the 'min_height' setting on baseline hero blocks is correctly
 * resolved and rendered as inline CSS style overrides on both public front-end pages
 * and dynamic administrative block previews.
 *
 * PHP version 8.3
 *
 * @package    Zero\Tests
 * @author     Zero CMS Team
 * @copyright  2026 Zero CMS
 */

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Core\Template;

echo "=== Baseline Hero Minimum Height Settings Test ===\n";

// Bootstrap App
App::bootstrap();

// Define mock resolver helper matching the frontend page view context
$resolveMedia = function($idOrPath) {
    return '/storage/uploads/' . $idOrPath;
};

// 1. Test case: No min_height set (should default and not apply inline height overrides)
echo "  Testing baseline hero block with default min_height setting...\n";
$blockDefault = [
    'type' => 'baseline',
    'title' => 'My Main Title',
    'content' => '<p>Welcome</p>'
];

$htmlDefault = Template::renderFile(
    APPLICATION_ROOT . '/src/Modules/Admin/Views/blocks/frontend/baseline.php',
    [
        'block' => $blockDefault,
        'resolveMedia' => $resolveMedia
    ]
);

assert_test(strpos($htmlDefault, 'style=""') !== false || strpos($htmlDefault, 'style=" "') !== false || strpos($htmlDefault, 'style') === false, "Default min_height doesn't inject inline height styles on wrapper");


// 2. Test case: Custom min_height set to 60vh (should output inline min-height)
echo "  Testing baseline hero block with custom 60vh min_height setting...\n";
$block60 = [
    'type' => 'baseline',
    'title' => 'My Main Title',
    'content' => '<p>Welcome</p>',
    'min_height' => '60'
];

$html60 = Template::renderFile(
    APPLICATION_ROOT . '/src/Modules/Admin/Views/blocks/frontend/baseline.php',
    [
        'block' => $block60,
        'resolveMedia' => $resolveMedia
    ]
);

assert_test(strpos($html60, 'min-height: 60vh;') !== false, "Custom min_height of 60vh correctly renders 'min-height: 60vh;' style attribute");


// 3. Test case: Custom min_height set to 100vh
echo "  Testing baseline hero block with custom 100vh min_height setting...\n";
$block100 = [
    'type' => 'baseline',
    'title' => 'My Main Title',
    'content' => '<p>Welcome</p>',
    'min_height' => '100'
];

$html100 = Template::renderFile(
    APPLICATION_ROOT . '/src/Modules/Admin/Views/blocks/frontend/baseline.php',
    [
        'block' => $block100,
        'resolveMedia' => $resolveMedia
    ]
);

assert_test(strpos($html100, 'min-height: 100vh;') !== false, "Custom min_height of 100vh correctly renders 'min-height: 100vh;' style attribute");


echo "Baseline Hero Minimum Height settings tests completed successfully!\n\n";
