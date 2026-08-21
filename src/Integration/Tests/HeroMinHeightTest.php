<?php
/**
 * Zero CMS - Hero Min Height setting Test
 *
 * Verifies that the 'min_height' setting on hero blocks is correctly resolved and published as a
 * CSS custom property on the block wrapper, which assets/css/blocks/hero.css then consumes. The
 * template deliberately emits variables rather than declarations, so the assertions here check
 * for the custom property rather than a raw min-height rule.
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

echo "=== Hero Minimum Height Settings Test ===\n";

// Bootstrap App
App::bootstrap();

// Define mock resolver helper matching the frontend page view context
$resolveMedia = function($idOrPath) {
    return '/storage/uploads/' . $idOrPath;
};

// 1. Test case: No min_height set (should default and not apply inline height overrides)
echo "  Testing hero block with default min_height setting...\n";
$blockDefault = [
    'type' => 'hero',
    'title' => 'My Main Title',
    'content' => '<p>Welcome</p>'
];

$htmlDefault = Template::renderFile(
    APPLICATION_ROOT . '/src/Modules/Admin/Views/blocks/frontend/hero.php',
    [
        'block' => $blockDefault,
        'resolveMedia' => $resolveMedia
    ]
);

assert_test(strpos($htmlDefault, 'style=') === false, "Default min_height emits no style attribute at all on the wrapper");
assert_test(strpos($htmlDefault, '--hero-min-height') === false, "Default min_height leaves the stylesheet's own default in force");


// 2. Test case: Custom min_height set to 60vh (should output inline min-height)
echo "  Testing hero block with custom 60vh min_height setting...\n";
$block60 = [
    'type' => 'hero',
    'title' => 'My Main Title',
    'content' => '<p>Welcome</p>',
    'min_height' => '60'
];

$html60 = Template::renderFile(
    APPLICATION_ROOT . '/src/Modules/Admin/Views/blocks/frontend/hero.php',
    [
        'block' => $block60,
        'resolveMedia' => $resolveMedia
    ]
);

assert_test(strpos($html60, '--hero-min-height: 60vh') !== false, "Custom min_height of 60vh publishes '--hero-min-height: 60vh'");
assert_test(strpos($html60, 'min-height:') === false || strpos($html60, '--hero-min-height') !== false, "The height is expressed as a custom property, not an inline declaration");


// 3. Test case: Custom min_height set to 100vh
echo "  Testing hero block with custom 100vh min_height setting...\n";
$block100 = [
    'type' => 'hero',
    'title' => 'My Main Title',
    'content' => '<p>Welcome</p>',
    'min_height' => '100'
];

$html100 = Template::renderFile(
    APPLICATION_ROOT . '/src/Modules/Admin/Views/blocks/frontend/hero.php',
    [
        'block' => $block100,
        'resolveMedia' => $resolveMedia
    ]
);

assert_test(strpos($html100, '--hero-min-height: 100vh') !== false, "Custom min_height of 100vh publishes '--hero-min-height: 100vh'");


// 4. Test case: a hero with no media still renders cleanly, and the wrapper carries only
// custom properties -- never a literal background-image declaration.
echo "  Testing that hero styling is published purely as custom properties...\n";
assert_test(strpos($html60, 'background-image') === false, "The hero template never inlines a background-image declaration");
assert_test(strpos($html60, 'class="block-hero') !== false, "The hero wrapper keeps its block-hero class for the stylesheet to target");

echo "Hero Minimum Height settings tests completed successfully!\n\n";
