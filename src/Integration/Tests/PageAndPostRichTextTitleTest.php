<?php
// tests/PageAndPostRichTextTitleTest.php
// Integration test to verify that the Page model schema configures the title field as standard text,
// and that edit forms successfully render a standard input box for the title.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Core\Template;
use Zero\Models\Page;

echo "=== Page Text Title Configuration & View Tests ===\n";

// Ensure App is bootstrapped
App::bootstrap();

// 1. Verify that the Page model configures the title field with type "text"
echo "  Verifying Page Model title configuration type...\n";

$pageConfig = Page::getConfig();
assert_test(isset($pageConfig['title']), "Page model has title field configured");
assert_test($pageConfig['title']['type'] === 'text', "Page model title field type is reverted to 'text'");


// 2. Setup mock record and compile model edit form views to check for input box rendering
echo "  Rendering Model Edit view for Page and checking standard input presence...\n";

$mockPage = new Page([
    'id' => '019fa1f1-abcd-72f0-8c3b-9732ab7f9e3a',
    'title' => 'Welcome to our Zero CMS website',
    'slug' => 'welcome',
    'status' => 'published',
    'content' => '[]'
]);

$viewPath = APPLICATION_ROOT . '/src/Modules/Admin/Views/model/edit.php';

$renderedEditForm = Template::renderFile($viewPath, [
    'modelName' => 'pages',
    'record' => $mockPage,
    'config' => $pageConfig,
    'csrf' => 'mock-csrf-token'
]);

// 3. Asset assertions on the compiled form's output
echo "  Asserting edit form has input field for title and no rich text editor for it...\n";

// Verify that the title field is rendered as a standard input element
assert_test(strpos($renderedEditForm, 'name="title"') !== false, "Input field with name='title' is present");
assert_test(strpos($renderedEditForm, 'value="Welcome to our Zero CMS website"') !== false, "Input field has correct populated value");

// Verify that the rich text editor area/toolbar for title is NOT rendered for this text field
// (Because the page does not have other rich_text type fields when usesBlockBuilder is true)
assert_test(strpos($renderedEditForm, 'class="editor"') === false, "Editor container is NOT rendered for standard title field");
assert_test(strpos($renderedEditForm, 'class="toolbar"') === false, "Editor toolbar is NOT rendered for standard title field");

echo "\n✅ Page Reverted Text Title Configuration & View Tests Passed Successfully!\n";
