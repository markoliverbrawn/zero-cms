<?php
// src/Modules/Events/Tests/EventsTest.php
// Unit and integration tests for the decoupled Events module

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Database\MigrationManager;
use Zero\Models\Site;
use Zero\Modules\Events\Models\Event;

// Ensure database migrations are run on the test database connection
ob_start();
MigrationManager::up();
ob_end_clean();

echo "=== Events Module Integration Tests ===\n";

// 1. Verify Module Auto-Discovery
echo "  Testing Events module auto-discovery...\n";
$eventsModule = null;
foreach (App::getModules() as $m) {
    if ($m->getId() === 'events') {
        $eventsModule = $m;
        break;
    }
}

assert_test($eventsModule !== null, "Events module is discovered by the core engine");
assert_test($eventsModule->getAccentColor() === '#f97316', "Events module returns representative orange accent color");


// 2. Verify Schema Migration
echo "  Testing Events table creation...\n";
$tableExists = false;
try {
    DB::query("SELECT 1 FROM events LIMIT 1");
    $tableExists = true;
} catch (Exception $e) {
    $tableExists = false;
}
assert_test($tableExists === true, "Events database table was successfully created by the migration");


// 3. Test Active Record Operations & Multi-Tenant Isolation
echo "  Testing Event model Active Record lifecycle...\n";
$siteId1 = 'site-events-test-1111';
$siteId2 = 'site-events-test-2222';

// Clean old test records
DB::query("DELETE FROM events WHERE site_id IN (?, ?)", [$siteId1, $siteId2]);
DB::query("DELETE FROM sites WHERE id IN (?, ?)", [$siteId1, $siteId2]);

// Insert temp test sites
DB::query("INSERT INTO sites (id, name, domain, theme, enabled_modules) VALUES (?, 'Test Site 1', 'test1.zero', 'default', '[\"events\"]')", [$siteId1]);
DB::query("INSERT INTO sites (id, name, domain, theme, enabled_modules) VALUES (?, 'Test Site 2', 'test2.zero', 'default', '[\"events\"]')", [$siteId2]);

// Cache current site context to restore later
$oldSite = App::getCurrentSite();

// Switch to Site 1 Context explicitly
$site1 = Site::find($siteId1);
App::setCurrentSite($site1);

$event1 = new Event([
    'title' => 'Zero Cyber Summit',
    'slug' => 'zero-cyber-summit',
    'description' => 'A security roundtable discussing zero-trust operations.',
    'event_date' => '2026-10-15 09:00:00',
    'location' => 'Decentralized Hub 1',
    'status' => 'published'
]);
$eventId1 = $event1->save();
assert_test(!empty($eventId1), "Event successfully saved and received an ID");

// Verify we can find it by ID
$found = Event::find($eventId1);
assert_test($found !== null, "Saved event found via Event::find()");
assert_test($found->title === 'Zero Cyber Summit', "Event title matches saved title");

// Verify we can find it by slug (with multi-tenant scoping)
$foundBySlug = Event::findBySlug('zero-cyber-summit');
assert_test($foundBySlug !== null, "Saved event found via Event::findBySlug()");
assert_test($foundBySlug->id === $eventId1, "Event found by slug has correct ID");

// Test multi-tenant isolation: switch to Site 2 Context explicitly
$site2 = Site::find($siteId2);
App::setCurrentSite($site2);

$foundOnSite2 = Event::findBySlug('zero-cyber-summit');
assert_test($foundOnSite2 === null, "Event from site 1 is completely invisible and isolated from site 2");

// Create event on site 2 with same slug
$event2 = new Event([
    'title' => 'Zero Cyber Summit Site 2',
    'slug' => 'zero-cyber-summit',
    'description' => 'A distinct discussion on site 2.',
    'event_date' => '2026-10-15 11:00:00',
    'location' => 'Decentralized Hub 2',
    'status' => 'published'
]);
$eventId2 = $event2->save();

$foundOnSite2New = Event::findBySlug('zero-cyber-summit');
assert_test($foundOnSite2New !== null, "Successfully retrieved site 2's own event with the same slug");
assert_test($foundOnSite2New->id === $eventId2, "Site 2 event ID is distinct");

// Restore original site context
App::setCurrentSite($oldSite);

// Cleanup test DB entries
DB::query("DELETE FROM events WHERE site_id IN (?, ?)", [$siteId1, $siteId2]);
DB::query("DELETE FROM sites WHERE id IN (?, ?)", [$siteId1, $siteId2]);


// 4. Test Localized Language Dictionaries
echo "  Testing Events localized Lang dictionaries...\n";
$translationsEn = require APPLICATION_ROOT . '/src/Modules/Events/Lang/en.php';
assert_test(isset($translationsEn['events_title']), "English Lang file contains 'events_title' key");
assert_test($translationsEn['events_title'] === 'Events', "English translation is correct");


echo "\n✅ Events Module Integration Tests Passed Successfully!\n";
