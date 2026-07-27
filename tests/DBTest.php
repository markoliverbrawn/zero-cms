<?php
// tests/DBTest.php
// Unit tests for central Database Engine (Zero\Database\DB)

require_once __DIR__ . '/bootstrap.php';

use Zero\Database\DB;

echo "=== DB Component Tests ===\n";

// 1. Test PDO connection
echo "Testing database connection (getPDO)...\n";
$pdo = DB::getPDO();
assert_test($pdo instanceof PDO, "DB::getPDO() returns a valid PDO instance");

// 2. Test query logs tracking
echo "Testing query logging and counts...\n";
$initialCount = DB::getQueryCount();

$stmt = DB::query("SELECT 1 AS val");
$result = $stmt->fetch();

assert_test($result['val'] == 1, "Prepared query executed successfully and returned correct records");
assert_test(DB::getQueryCount() === $initialCount + 1, "Query logger successfully recorded the executed query");

$log = DB::getQueryLog();
$lastLog = end($log);
assert_test(strpos($lastLog['sql'], "SELECT 1") === 0, "Last query logged matches correct SQL command string");
assert_test(isset($lastLog['duration']) && $lastLog['duration'] >= 0, "Query logged records positive microsecond execution duration");
assert_test(DB::getTotalQueryTime() >= 0, "Cumulative DB query timer tracks query execution times");

// 3. Test Schema Column Caching (hasColumn)
echo "Testing schema column exists caching...\n";
// Table 'sites' definitely has column 'domain'
assert_test(DB::hasColumn('sites', 'domain'), "DB::hasColumn correctly identifies that column 'domain' exists in 'sites' table");
// Table 'sites' definitely does not have 'invalid_col'
assert_test(!DB::hasColumn('sites', 'invalid_col_name_xyz'), "DB::hasColumn correctly identifies that non-existent columns do not exist");

// Clear static column cache and test cache lookup through Reflection
try {
    $reflector = new ReflectionClass(DB::class);
    $cacheProp = $reflector->getProperty('columnCache');
    $cacheProp->setAccessible(true);
    $cacheProp->setValue(null, []); // Clear it

    // Check caching mechanism
    DB::hasColumn('sites', 'domain');
    $cacheData = $cacheProp->getValue();
    assert_test(isset($cacheData['sites.domain']) && $cacheData['sites.domain'] === true, "Columns checking correctly populates the static cache lookup array");
} catch (Exception $e) {
    echo "Reflection error on DB column cache: " . $e->getMessage() . "\n";
}

// 4. Test Identity Map Caching (getIdentity/setIdentity)
echo "Testing Identity Mapping Cache...\n";
$mockId = 'mock-record-id-123';
$mockRecord = (object)['id' => $mockId, 'title' => 'Mock Record Title'];

// Initial lookup should return null
assert_test(DB::getIdentity('pages', $mockId) === null, "Identity map initially returns null for uncached record");

// Cache it
DB::setIdentity('pages', $mockId, $mockRecord);
$cached = DB::getIdentity('pages', $mockId);
assert_test($cached === $mockRecord, "getIdentity correctly retrieves the cached record");

// Test negative caching (storing false)
DB::setIdentity('pages', 'non-existent-id', false);
assert_test(DB::getIdentity('pages', 'non-existent-id') === false, "getIdentity correctly supports negative caching (storing false for missing entries)");

echo "DB component tests completed.\n\n";
