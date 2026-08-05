<?php
// tests/PageAndPostRichTextTitleTest.php
// Integration test to verify that Page and Blog Post model schemas configure title fields as rich_text,
// and that edit forms successfully render the cut-down rich-text toolbar and editor.

require_once __DIR__ . '/bootstrap.php';

use Zero\Core\App;
use Zero\Core\Template;
use Zero\Models\Page;
use Zero\Modules\Blog\Models\Post;

echo "=== Page & Post Rich Text Title Configuration & View Tests ===\n";

// Ensure App is bootstrapped
App::bootstrap();

// 1. Verify that both Page and Post models configure the title field with type "rich_text"
echo "  Verifying Page & Post Model title configuration types...\n";

$pageConfig = Page::getConfig();
assert_test(isset($pageConfig['title']), "Page model has title field configured");
assert_test($pageConfig['title']['type'] === 'rich_text', "Page model title field type is 'rich_text'");

$postConfig = Post::getConfig();
assert_test(isset($postConfig['title']), "Post model has title field configured");
assert_test($postConfig['title']['type'] === 'rich_text', "Post model title field type is 'rich_text'");


// 2. Setup mock record and compile model edit form views to check for editor rendering
echo "  Rendering Model Edit view for Page and checking cut-down editor presence...\n";

$mockPage = new Page([
    'id' => '019fa1f1-abcd-72f0-8c3b-9732ab7f9e3a',
    'title' => '<strong>Welcome</strong> to our <em>Zero CMS</em> website',
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
echo "  Asserting edit form editor and toolbar HTML signatures...\n";

// Verify that the title field is rendered inside a rich-text editor wrapper
assert_test(strpos($renderedEditForm, 'class="editor"') !== false, "Editor container is rendered");
assert_test(strpos($renderedEditForm, 'class="toolbar"') !== false, "Editor toolbar is rendered");

// Verify that the editor toolbar contains specifically the cut-down buttons
assert_test(strpos($renderedEditForm, 'data-cmd="bold"') !== false, "Bold formatting button is present");
assert_test(strpos($renderedEditForm, 'data-cmd="italic"') !== false, "Italic formatting button is present");
assert_test(strpos($renderedEditForm, 'data-cmd="insertSmall"') !== false, "Small formatting button is present");

// Verify that formatting commands NOT belonging to the cut-down toolbar (like list/link/table insertion) are omitted
// Wait! Let's check: are other editors (like content) on the page?
// Yes, the Page model might have other content editors if usesBlockBuilder is false,
// but our mockPage has content = '[]' and usesBlockBuilder = true, so the block builder is rendered instead of a general description textarea.
// Therefore, the only rich text editor rendered on the page is the Title field!
// Let's verify that table insertion or link commands are NOT rendered in this specific form's markup
assert_test(strpos($renderedEditForm, 'data-cmd="insertTable"') === false, "Table insertion is omitted from the cut-down title toolbar");
assert_test(strpos($renderedEditForm, 'data-cmd="createLink"') === false, "Link insertion is omitted from the cut-down title toolbar");

// Verify that the editor content container contains the mock title HTML
assert_test(strpos($renderedEditForm, '<strong>Welcome</strong> to our <em>Zero CMS</em> website') !== false, "Rich text title content is successfully populated inside the contenteditable area");

// Verify that the hidden sync input is correctly bound and populated
assert_test(strpos($renderedEditForm, 'type="hidden"') !== false, "Hidden input exists for value submission");
assert_test(strpos($renderedEditForm, 'class="content-input"') !== false, "Submission input has correct content-input class");
assert_test(strpos($renderedEditForm, 'name="title"') !== false, "Submission input is bound to the title field name");

echo "\n✅ Page & Post Rich Text Title Configuration & View Tests Passed Successfully!\n";
