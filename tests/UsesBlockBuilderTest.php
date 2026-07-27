<?php
// tests/UsesBlockBuilderTest.php
// Unit test to verify the UsesBlockBuilder core trait and overriding behavior.

require_once __DIR__ . '/bootstrap.php';

use Zero\Models\Page;
use Zero\Models\Traits\UsesBlockBuilder;

echo "=== UsesBlockBuilder Trait Component Tests ===\n";

// 1. Verify Page model inherits and resolves default trait behaviors
echo "  Testing core Page model integration...\n";
assert_test(method_exists(Page::class, 'getBlockBuilderField'), "Page model has getBlockBuilderField method from trait");
assert_test(method_exists(Page::class, 'getAllowedBlocks'), "Page model has getAllowedBlocks method from trait");

assert_test(Page::getBlockBuilderField() === 'content', "Page model defaults block builder storage to 'content' field");
assert_test(Page::getAllowedBlocks() === null, "Page model allowed blocks list defaults to null (all blocks enabled)");


// 2. Define a custom model to test override scalability
echo "  Testing custom model overrides and scalability...\n";

class CustomBlockModel
{
    use UsesBlockBuilder;

    public static function getBlockBuilderField(): string
    {
        return 'custom_layout_payload';
    }

    public static function getAllowedBlocks(): ?array
    {
        return ['text', 'gallery', 'accordion'];
    }
}

assert_test(CustomBlockModel::getBlockBuilderField() === 'custom_layout_payload', "Custom model successfully overrides block builder storage field name");
assert_test(CustomBlockModel::getAllowedBlocks() === ['text', 'gallery', 'accordion'], "Custom model successfully filters and whitelists allowed block types");

echo "UsesBlockBuilder trait tests completed successfully.\n\n";
