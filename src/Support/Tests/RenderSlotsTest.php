<?php
// src/Support/Tests/RenderSlotsTest.php
// Verifies RenderSlots caps concurrent image renders per instance: a full pool makes acquire()
// wait, a freed slot is picked up, a timed-out wait falls back to rendering anyway, and a render
// that throws inside MediaVariantController::generate() still frees both its slot and its
// variant lock.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Core\Storage\Storage;
use Zero\Http\Controllers\MediaVariantController;
use Zero\Support\Assets;
use Zero\Support\ImageProcessor;
use Zero\Models\Site;
use Zero\Support\RenderSlots;
use Zero\Support\Security;
use Zero\Support\VariantCache;

echo "=== Render Slots Tests ===\n";

// A private slot directory, so parallel suites rendering real variants never share this pool.
$slotDir = sys_get_temp_dir() . '/zero-render-slots-test-' . bin2hex(random_bytes(6));
RenderSlots::useDirectory($slotDir);
putenv('IMAGE_RENDER_SLOTS=2');

// 1. With two slots, a third acquire waits for the full timeout and then gives up.
echo "  Testing the slot limit...\n";
$first = RenderSlots::acquire(0);
$second = RenderSlots::acquire(0);
assert_test(is_resource($first) && is_resource($second), "Two slots are available when IMAGE_RENDER_SLOTS=2");

$started = microtime(true);
$third = RenderSlots::acquire(0.3);
$waited = microtime(true) - $started;
assert_test($third === null, "A third acquire gets no slot while both are held");
assert_test($waited >= 0.3, "The third acquire waited for its timeout before giving up");

// 2. Releasing a slot lets the waiting caller in.
RenderSlots::release($first);
$third = RenderSlots::acquire(0);
assert_test(is_resource($third), "A released slot is picked up by the next acquire");

// 3. Fallback: with no slot available the render still completes.
echo "  Testing the timed-out fallback still renders...\n";
$none = RenderSlots::acquire(0);
assert_test($none === null, "A zero-second wait on a full pool returns null immediately");
RenderSlots::release($none); // a null handle is a safe no-op
if (Assets::isSupported()) {
    $canvas = imagecreatetruecolor(40, 20);
    ob_start();
    imagepng($canvas);
    $png = ob_get_clean();
    imagedestroy($canvas);
    $variant = ImageProcessor::render($png, 20, 10);
    assert_test($variant['bytes'] !== '' && $variant['width'] === 20, "The render completes without a slot");
}
RenderSlots::release($second);
RenderSlots::release($third);

// 4. A render that throws inside the controller frees its slot and its variant lock.
echo "  Testing a failed render releases its slot and variant lock...\n";
App::bootstrap();
$siteId = Security::uuidv7();
App::setCurrentSite(new Site(['id' => $siteId, 'name' => 'Render slots', 'domain' => 'render-slots.zero']));

/**
 * Stand-in for the controller's terminating responses, which would otherwise exit the test.
 */
class RenderSlotsTestStop extends \Exception
{
}

/**
 * Exposes generate() and turns its exit-based responses into a catchable signal.
 */
class RenderSlotsTestController extends MediaVariantController
{
    public string $outcome = '';

    public function runGenerate(string $relativePath, string $sourcePath): void
    {
        $this->generate($relativePath, $sourcePath, 20, 10, Assets::FIT_COVER, 80, 50, 50);
    }

    protected function notFound(): void
    {
        $this->outcome = 'not-found';
        throw new RenderSlotsTestStop();
    }

    protected function redirectToOriginal(string $sourcePath): void
    {
        $this->outcome = 'redirect';
        throw new RenderSlotsTestStop();
    }

    protected function respond(string $bytes): void
    {
        $this->outcome = 'respond';
        throw new RenderSlotsTestStop();
    }
}

$uploadsDir = APPLICATION_ROOT . '/public/storage/uploads/' . $siteId;
mkdir($uploadsDir, 0775, true);
$corruptPath = confine_test_path($uploadsDir . '/corrupt.jpg', $uploadsDir);
file_put_contents($corruptPath, 'this is not an image');
$sourcePath = '/storage/uploads/' . $siteId . '/corrupt.jpg';
assert_critical(Storage::read($sourcePath) === 'this is not an image', "The corrupt source is readable, so the failure comes from the render itself");
$relativePath = Assets::CACHE_DIRECTORY . '/render-slots-test/' . bin2hex(random_bytes(6)) . '/20x10-cover-q80-test.webp';

$controller = new RenderSlotsTestController();
try {
    $controller->runGenerate($relativePath, $sourcePath);
} catch (RenderSlotsTestStop $stop) {
    // expected: the controller "responded"
}
assert_test($controller->outcome === 'redirect', "A failed render degrades to the original image");
assert_test(!is_file(VariantCache::absolutePath($relativePath) . '.lock'), "The variant lock was released");

$a = RenderSlots::acquire(0);
$b = RenderSlots::acquire(0);
assert_test(is_resource($a) && is_resource($b), "Both render slots are free again after the failed render");
RenderSlots::release($a);
RenderSlots::release($b);

$lock = VariantCache::lock($relativePath);
assert_test(is_resource($lock), "The next request can take the variant lock and render");
VariantCache::release($lock, $relativePath);

// Cleanup
unlink($corruptPath);
rmdir($uploadsDir);
$variantDir = dirname(VariantCache::absolutePath($relativePath));
if (is_dir($variantDir)) {
    rmdir($variantDir);
    rmdir(dirname($variantDir));
}
array_map('unlink', glob($slotDir . '/*') ?: []);
rmdir($slotDir);
RenderSlots::useDirectory(null);
putenv('IMAGE_RENDER_SLOTS');

echo "Render slots tests completed successfully!\n\n";
