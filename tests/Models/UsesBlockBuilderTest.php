<?php
// tests/UsesBlockBuilderTest.php
// Unit test to verify the UsesBlockBuilder and SupportsBlocks core traits and overriding behavior.

require_once __DIR__ . '/bootstrap.php';

use Zero\Models\Page;
use Zero\Models\Traits\UsesBlockBuilder;
use Zero\Models\Traits\SupportsBlocks;

echo "=== UsesBlockBuilder Trait Component Tests ===\n";

// 1. Verify Page model inherits and resolves default trait behaviors
echo "  Testing core Page model integration...\n";
assert_test(method_exists(Page::class, 'getBlockBuilderField'), "Page model has getBlockBuilderField method from trait");
assert_test(method_exists(Page::class, 'getAllowedBlocks'), "Page model has getAllowedBlocks method from trait");
assert_test(method_exists(Page::class, 'getSupportedBlocks'), "Page model has getSupportedBlocks method from SupportsBlocks trait");
assert_test(method_exists(Page::class, 'isBlockBuilderEnabled'), "Page model has isBlockBuilderEnabled method from SupportsBlocks trait");

assert_test(Page::getBlockBuilderField() === 'content', "Page model defaults block builder storage to 'content' field");
assert_test(Page::getAllowedBlocks() === null, "Page model allowed blocks list defaults to null (all blocks enabled)");
assert_test(Page::getSupportedBlocks() === true, "Page model getSupportedBlocks defaults to true (all blocks enabled)");


// 2. Define a custom model to test override scalability and SupportsBlocks integration
echo "  Testing custom model overrides and scalability...\n";

class CustomBlockModel
{
    use UsesBlockBuilder;

    public static function getBlockBuilderField(): string
    {
        return 'custom_layout_payload';
    }

    public static function getSupportedBlocks()
    {
        return ['text', 'gallery', 'accordion'];
    }
}

assert_test(CustomBlockModel::getBlockBuilderField() === 'custom_layout_payload', "Custom model successfully overrides block builder storage field name");
assert_test(CustomBlockModel::getSupportedBlocks() === ['text', 'gallery', 'accordion'], "Custom model successfully overrides getSupportedBlocks");
assert_test(CustomBlockModel::getAllowedBlocks() === ['text', 'gallery', 'accordion'], "getAllowedBlocks dynamically maps to custom getSupportedBlocks array list");


// 3. Test dynamic Controller capability check
echo "  Testing dynamic controller supportsBlocks capability verification...\n";

$normalPage = new Page(['title' => 'Standard page', 'controller' => '']);
assert_test($normalPage->usesBlockBuilder() === true, "Standard page with empty controller uses block builder by default");

$blogPage = new Page(['title' => 'Blog landing page', 'controller' => 'Zero\\Modules\\Blog\\Controllers\\BlogController']);
assert_test($blogPage->usesBlockBuilder() === true, "Blog page using BlogController with SupportsBlocks trait returns true for block builder compatibility");

$shopPage = new Page(['title' => 'Shop landing page', 'controller' => 'Zero\\Modules\\Shop\\Controllers\\CatalogController']);
assert_test($shopPage->usesBlockBuilder() === false, "Shop page using CatalogController without supportsBlocks returns false for block builder compatibility");


echo "UsesBlockBuilder and SupportsBlocks trait tests completed successfully.\n\n";
