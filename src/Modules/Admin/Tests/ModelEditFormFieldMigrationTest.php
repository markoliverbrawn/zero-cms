<?php
// tests/ModelEditFormFieldMigrationTest.php
// Integration tests proving the model/edit.php + ModelController migration onto the FormField
// component system preserved every legacy special case (gallery picker, image picker, block
// builder skip, pages.parent_path circular guard) while fixing the number/email input-type bug.

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Core\Template;
use Zero\Models\Page;
use Zero\Modules\Shop\Models\Category;
use Zero\Modules\Shop\Models\Order;
use Zero\Modules\Shop\Models\Product;

echo "=== Model Edit Form / FormField Migration Tests ===\n";

App::bootstrap();

$viewPath = APPLICATION_ROOT . '/src/Modules/Admin/Views/model/edit.php';

// 1. getConfig() type-value migrations landed correctly
echo "Verifying getConfig() type-value changes...\n";
$productConfig = Product::getConfig();
assert_test($productConfig['media_ids']['type'] === 'gallery_picker', "Product.media_ids is now type 'gallery_picker'");
assert_test($productConfig['description']['type'] === 'rich_text_editor', "Product.description is now type 'rich_text_editor'");

$categoryConfig = Category::getConfig();
assert_test($categoryConfig['description']['type'] === 'rich_text_editor', "Category.description is now type 'rich_text_editor'");

$pageConfig = Page::getConfig();
assert_test($pageConfig['content']['type'] === 'textarea', "Page.content stays type 'textarea' (block_builder is resolved per-record in the view, not statically)");

// 2. Number/email fields now render their correct HTML5 input type (the bug the migration fixes)
echo "Testing number/email input-type fix...\n";
$mockProduct = new Product([
    'id' => 'prod-1',
    'title' => 'Test Product',
    'slug' => 'test-product',
    'price' => '19.99',
    'compare_at_price' => '24.99',
    'media_ids' => '',
    'description' => 'A description',
]);
$productHtml = Template::renderFile($viewPath, [
    'modelName' => 'products',
    'record' => $mockProduct,
    'config' => $productConfig,
    'csrf' => 'mock-csrf-token',
]);
assert_test(\strpos($productHtml, 'name="price"') !== false, "price field is rendered");
assert_test(\preg_match('/name="price"[^>]*type="number"|type="number"[^>]*name="price"/', $productHtml) === 1, "price field now renders type=\"number\" (was a bare <input> before migration)");

$orderConfig = Order::getConfig();
$mockOrder = new Order(['id' => 'ord-1', 'customer_email' => 'test@example.com', 'customer_name' => 'Jane', 'total_price' => '42.00']);
$orderHtml = Template::renderFile($viewPath, [
    'modelName' => 'orders',
    'record' => $mockOrder,
    'config' => $orderConfig,
    'csrf' => 'mock-csrf-token',
]);
assert_test(\strpos($orderHtml, 'type="email"') !== false, "customer_email field now renders type=\"email\"");

// 3. Gallery picker (media_ids) markup is preserved for model_edit.js compatibility
echo "Testing GalleryPickerField markup preservation...\n";
$mockProductWithGallery = new Product([
    'id' => 'prod-2',
    'title' => 'Gallery Product',
    'slug' => 'gallery-product',
    'price' => '10',
    'media_ids' => '',
]);
$galleryHtml = Template::renderFile($viewPath, [
    'modelName' => 'products',
    'record' => $mockProductWithGallery,
    'config' => $productConfig,
    'csrf' => 'mock-csrf-token',
]);
assert_test(\strpos($galleryHtml, 'id="product-gallery-picker-btn"') !== false, "Gallery picker button id is preserved for model_edit.js");
assert_test(\strpos($galleryHtml, 'id="product-media-ids-input"') !== false, "Gallery picker hidden input id is preserved for model_edit.js");

// 4. Image picker markup preserved
echo "Testing ImagePickerField markup preservation...\n";
assert_test(\strpos($productHtml, 'image-picker-container') !== false, "Image picker container class is preserved for model_edit.js");
assert_test(\strpos($productHtml, 'class="image-picker-input"') !== false, "Image picker input class is preserved for model_edit.js");

// 5. Block builder: disabled case renders a hidden passthrough, not a rich-text editor
echo "Testing block builder skip + hidden passthrough...\n";
$shopPage = new Page([
    'id' => 'page-1',
    'title' => 'Shop Landing',
    'slug' => 'shop-landing',
    'status' => 'published',
    'content' => 'raw non-block content',
    'controller' => 'Zero\\Modules\\Shop\\Controllers\\CatalogController',
]);
assert_test($shopPage->usesBlockBuilder() === false, "Sanity check: this mock page does not use the block builder");
$shopPageHtml = Template::renderFile($viewPath, [
    'modelName' => 'pages',
    'record' => $shopPage,
    'config' => $pageConfig,
    'csrf' => 'mock-csrf-token',
]);
assert_test(\strpos($shopPageHtml, 'name="content" value="raw non-block content"') !== false, "content renders as a hidden passthrough input carrying the existing value when block builder is disabled");
assert_test(\strpos($shopPageHtml, 'block-title-rich-editor') === false, "No rich-text editor is rendered for content when block builder is disabled");

// 6. pages.parent_path circular-reference guard
echo "Testing pages.parent_path circular-reference guard...\n";
$configWithParentPath = $pageConfig;
$configWithParentPath['parent_path'] = [
    'type' => 'select',
    'label' => 'Parent Page',
    'editable' => true,
    'options' => ['' => 'None', 'about' => 'About', 'about/team' => 'About / Team', 'contact' => 'Contact'],
];
$aboutPage = new Page(['id' => 'page-2', 'title' => 'About', 'slug' => 'about', 'status' => 'published', 'content' => '[]']);
$aboutPageHtml = Template::renderFile($viewPath, [
    'modelName' => 'pages',
    'record' => $aboutPage,
    'config' => $configWithParentPath,
    'csrf' => 'mock-csrf-token',
]);
\preg_match('/<select\s+name="parent_path".*?<\/select>/s', $aboutPageHtml, $parentPathMatch);
$parentPathSelectHtml = $parentPathMatch[0] ?? '';
assert_test($parentPathSelectHtml !== '', "The parent_path <select> is found in the rendered form");
assert_test(\strpos($parentPathSelectHtml, 'value="contact"') !== false, "An unrelated page still appears as a parent_path option");
assert_test(\strpos($parentPathSelectHtml, 'value="about"') === false, "A page's own slug is excluded from its own parent_path options");
assert_test(\strpos($parentPathSelectHtml, 'value="about/team"') === false, "A page's descendant slug is excluded from its own parent_path options");

// 7. ModelController-style POST value casting (same FormField::castSubmittedValue() call ModelController::handle() now makes)
echo "Testing ModelController-style POST value casting...\n";
$config = ['status' => ['type' => 'select', 'options' => ['draft' => 'Draft', 'published' => 'Published'], 'editable' => true, 'default' => 'draft']];
$field = App::makeFormField('select', 'status', $config['status']);
assert_test($field->castSubmittedValue(['status' => 'published']) === 'published', "ModelController-style select casting accepts a valid option");
assert_test($field->castSubmittedValue(['status' => 'tampered']) === 'draft', "ModelController-style select casting falls back to default for a tampered value");

$multiConfig = ['options' => ['u1' => 'User One', 'u2' => 'User Two'], 'multiple' => true, 'editable' => true];
$multiField = App::makeFormField('select', 'comment_notifiers', $multiConfig);
$multiCast = $multiField->castSubmittedValue(['comment_notifiers' => ['u1', 'u2']]);
assert_test(\json_encode($multiCast) === \json_encode(['u1', 'u2']), "Multi-select casting round-trips a fully-valid submission identically to the legacy raw json_encode()");

echo "Model Edit Form / FormField Migration tests completed.\n\n";
