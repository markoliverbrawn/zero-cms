<?php
/**
 * Zero CMS - Gallery Layout Settings Test
 *
 * Verifies the gallery block's layout / column / spacing settings: the frontend template publishes
 * allowlisted values as CSS custom properties (consumed by assets/css/blocks/gallery.css), falls
 * back safely on bad input, leaves legacy galleries untouched, and the admin block builder renders
 * the settings panel with the saved values selected.
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

echo "=== Gallery Layout Settings Test ===\n";

App::bootstrap();

$resolveMedia = function($idOrPath) {
    return '/storage/uploads/' . $idOrPath;
};

$renderGallery = function(array $block) use ($resolveMedia): string {
    return Template::renderFile(
        APPLICATION_ROOT . '/src/Modules/Admin/Views/blocks/frontend/gallery.php',
        ['block' => $block + ['type' => 'gallery', 'media_ids' => ['a.jpg']], 'resolveMedia' => $resolveMedia]
    );
};

// 1. Legacy gallery with no settings keeps the stylesheet's auto-fit grid.
echo "  Testing gallery with no saved settings...\n";
$htmlLegacy = $renderGallery([]);
assert_test(strpos($htmlLegacy, 'gallery-layout-grid') !== false, "Legacy gallery defaults to the grid layout");
assert_test(strpos($htmlLegacy, 'gallery-fixed-cols') === false, "Legacy gallery keeps the auto-fit columns");
assert_test(strpos($htmlLegacy, '--gallery-') === false, "Legacy gallery publishes no custom properties");

// 2. Masonry with 6 desktop columns.
echo "  Testing masonry gallery with 6 desktop columns...\n";
$htmlMasonry = $renderGallery(['layout' => 'masonry', 'cols_desktop' => '6']);
assert_test(strpos($htmlMasonry, 'gallery-layout-masonry') !== false, "Masonry layout class is applied");
assert_test(strpos($htmlMasonry, 'gallery-fixed-cols') !== false, "Setting a column count switches to fixed columns");
assert_test(strpos($htmlMasonry, '--gallery-cols-desktop: 6') !== false, "Desktop column count is published");
assert_test(strpos($htmlMasonry, '--gallery-cols-mobile: 2') !== false, "Unset mobile columns fall back to 2");
assert_test(strpos($htmlMasonry, '--gallery-cols-wide: 6') !== false, "Unset wide columns follow desktop");

// 3. Bad values fall back to defaults and never reach the style attribute.
echo "  Testing gallery with invalid settings...\n";
$htmlBad = $renderGallery(['layout' => 'x" onload="y', 'cols_desktop' => '99', 'gap' => 'red']);
assert_test(strpos($htmlBad, 'gallery-layout-grid') !== false, "Invalid layout falls back to grid");
assert_test(strpos($htmlBad, '99') === false, "Invalid column count is discarded");
assert_test(strpos($htmlBad, 'red') === false, "Invalid gap is discarded");
assert_test(strpos($htmlBad, 'onload') === false, "Invalid layout value is never echoed");
assert_test(strpos($htmlBad, '--gallery-') === false, "Invalid settings leave the stylesheet defaults in force");

// 4. Admin block builder renders the gallery settings panel with the saved values selected.
echo "  Testing gallery settings panel in the block builder...\n";
$record = (object)['content' => json_encode([[
    'type' => 'gallery',
    'media_ids' => [],
    'layout' => 'masonry',
    'cols_tablet' => '4',
    'cols_desktop' => '6',
    'gap' => '24px',
]])];
$htmlAdmin = Template::renderFile(
    APPLICATION_ROOT . '/src/Modules/Admin/Views/block_builder.php',
    ['record' => $record, 'modelName' => '', 'fieldConfig' => ['label' => 'Content']]
);
assert_test(strpos($htmlAdmin, 'block-cols_desktop-select') !== false, "Gallery settings panel is rendered");
assert_test(strpos($htmlAdmin, '<option value="masonry" selected>') !== false, "Saved layout is selected");
assert_test(strpos($htmlAdmin, '<option value="6" selected>6</option>') !== false, "Saved desktop columns are selected");
assert_test(strpos($htmlAdmin, '<option value="4" selected>4</option>') !== false, "Saved tablet columns are selected");
assert_test(strpos($htmlAdmin, '<option value="24px" selected>') !== false, "Saved spacing is selected");

echo "Gallery layout settings tests completed successfully!\n\n";
