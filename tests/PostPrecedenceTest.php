<?php
// tests/PostPrecedenceTest.php
// Automated test to verify that the Post model is not orderable and does not include page-specific columns in its config.

require_once __DIR__ . '/bootstrap.php';

use Zero\Modules\Blog\Models\Post;

echo "=== Post Precedence & Config Component Tests ===\n";

// 1. Verify Post is not orderable
$traits = class_uses(Post::class);
$isOrderable = isset($traits[\Zero\Models\Traits\IsOrderable::class]) || (method_exists(Post::class, 'isOrderable') && Post::isOrderable());

assert_test(!$isOrderable, "Post model correctly identifies itself as NOT orderable (isOrderable returns false)");

// 2. Verify config overrides
$config = Post::getConfig();

assert_test(!isset($config['precedence']), "Post model config does NOT contain page-specific 'precedence' field");
assert_test(!isset($config['controller']), "Post model config does NOT contain page-specific 'controller' field");
assert_test(!isset($config['view']), "Post model config does NOT contain page-specific 'view' field");

// 3. Verify that standard editable fields are present
assert_test(isset($config['title']), "Post model config contains 'title' field");
assert_test(isset($config['status']), "Post model config contains 'status' field");
assert_test(isset($config['content']), "Post model config contains 'content' field");

echo "Post precedence and config component tests completed.\n\n";
