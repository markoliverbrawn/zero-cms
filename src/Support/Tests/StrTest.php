<?php
// src/Support/Tests/StrTest.php
// Unit tests for string helper and custom high-contrast syntax highlighter (Zero\Support\Str)

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\Str;

echo "=== Str Component Tests ===\n";

// 1. Test Str::escape()
echo "Testing Str::escape...\n";
assert_test(Str::escape("Hello <World> & 'Friends'") === "Hello &lt;World&gt; &amp; &#039;Friends&#039;", "Escapes HTML tags and quotes correctly");
assert_test(Str::escape(null) === "", "Handles null inputs by returning empty string");
assert_test(Str::escape("Normal string") === "Normal string", "Leaves standard strings unmodified");

// 2. Test Str::slug()
echo "Testing Str::slug...\n";
assert_test(Str::slug("Hello World!") === "hello-world", "Converts string to lowercase and replaces spaces with dashes");
assert_test(Str::slug("A  B   C") === "a-b-c", "Collapses consecutive dashes correctly");
assert_test(Str::slug("Café & Crème") === "cafe-creme", "Transliterates non-ASCII accented characters and drops symbols");
assert_test(Str::slug("") === "n-a", "Defaults empty strings to 'n-a'");

// 3. Test Str::slugPath()
echo "Testing Str::slugPath...\n";
assert_test(Str::slugPath("Blog/Category Name/My-Post") === "blog/category-name/my-post", "Slugifies individual slash-separated URL path segments correctly");
assert_test(Str::slugPath("///") === "n-a", "Defaults paths with only slashes to 'n-a'");

// 4. Test Str::truncate()
echo "Testing Str::truncate...\n";
assert_test(Str::truncate("Hello World", 5) === "Hello...", "Truncates string exceeding limit and appends default suffix");
assert_test(Str::truncate("Hello World", 20) === "Hello World", "Returns string unmodified if within limit");
assert_test(Str::truncate("Hello World", 5, "---") === "Hello---", "Truncates and appends custom suffix");

// 5. Test Str::highlightCode()
echo "Testing Str::highlightCode...\n";
// PHP
$phpCode = 'class Foo { public function bar() { return "hello"; } }';
$highlightedPhp = Str::highlightCode($phpCode, 'php');
assert_test(str_contains($highlightedPhp, 'tok-keyword'), "PHP highlighting injects tok-keyword spans");

// JSON
$jsonCode = '{"name": "Alice", "age": 30}';
$highlightedJson = Str::highlightCode($jsonCode, 'json');
assert_test(str_contains($highlightedJson, 'tok-key'), "JSON highlighting injects tok-key spans");
assert_test(str_contains($highlightedJson, 'tok-num'), "JSON highlighting injects tok-num spans");

// HTML
$htmlCode = '<div class="active">Hello</div>';
$highlightedHtml = Str::highlightCode($htmlCode, 'html');
assert_test(str_contains($highlightedHtml, 'tok-tag'), "HTML highlighting injects tok-tag spans");
assert_test(str_contains($highlightedHtml, 'tok-var'), "HTML highlighting injects tok-var spans");

// JS
$jsCode = 'const x = () => { return 5; };';
$highlightedJs = Str::highlightCode($jsCode, 'javascript');
assert_test(str_contains($highlightedJs, 'tok-keyword'), "JavaScript highlighting injects tok-keyword spans");

// Bash
$bashCode = 'curl -X GET "https://google.com" # command description';
$highlightedBash = Str::highlightCode($bashCode, 'bash');
assert_test(str_contains($highlightedBash, 'tok-comment'), "Bash highlighting injects tok-comment spans");

// 6. Test Str::highlightHtml()
echo "Testing Str::highlightHtml...\n";
$blockHtml = '<pre><code class="language-php">function test() {}</code></pre>';
$highlightedBlock = Str::highlightHtml($blockHtml);
assert_test(str_contains($highlightedBlock, 'block-code-container'), "Wraps syntax highlighted code in container element");
assert_test(str_contains($highlightedBlock, 'tok-keyword'), "Decodes and parses raw code inside block-code elements");
