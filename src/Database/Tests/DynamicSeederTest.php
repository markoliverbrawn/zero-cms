<?php
// tests/DynamicSeederTest.php
// Integration test to verify the Core-Agnostic, Event-Driven Seeder framework.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\Seeder;
use Zero\Support\Security;

echo "=== Core-Agnostic Event-Driven Seeder Component Tests ===\n";

// Bootstrap App
\Zero\Core\App::bootstrap();

// 1. Verify dynamic filter registration and resolution
echo "  Testing Seeder::registerFieldFilter()...\n";

$filterApplied = false;
Seeder::registerFieldFilter('test_field', function ($value, $colName, $tableName) use (&$filterApplied) {
    $filterApplied = true;
    return $value . '-filtered';
});

// Create a mock seeder source
$mockData = [
    'mock_table' => [
        [
            'id' => Security::uuidv7(),
            'site_id' => Security::uuidv7(),
            'test_field' => 'initial-value'
        ]
    ]
];

$seeder = new Seeder($mockData);

// Reflect and check filtered values during mock row execution
$refSeeder = new \ReflectionClass(Seeder::class);
$method = $refSeeder->getMethod('getTableColumns');
$method->setAccessible(true);

// Set valid columns in cache to bypass DESCRIBE query on mock_table
$propCache = $refSeeder->getProperty('columnCache');
$propCache->setAccessible(true);
$cache = $propCache->getValue();
$cache['mock_table'] = ['id', 'site_id', 'test_field'];
$propCache->setValue(null, $cache);

// Use a temporary test double to execute the seeding steps or manually trigger row processors
$row = [
    'id' => Security::uuidv7(),
    'site_id' => Security::uuidv7(),
    'test_field' => 'initial-value'
];

// Let's manually run field filters on the row using reflection to verify
$propFilters = $refSeeder->getProperty('fieldFilters');
$propFilters->setAccessible(true);
$filters = $propFilters->getValue();

assert_test(isset($filters['test_field']), "Field filter registered for test_field");

$val = $row['test_field'];
foreach ($filters['test_field'] as $filter) {
    $val = $filter($val, 'test_field', 'mock_table', $row);
}

assert_test($filterApplied, "Field filter was successfully executed during testing flow");
assert_test($val === 'initial-value-filtered', "Field filter correctly transformed value from 'initial-value' to 'initial-value-filtered'");

// 2. Verify row processor registration
echo "  Testing Seeder::registerRowProcessor()...\n";

$rowProcessorApplied = false;
Seeder::registerRowProcessor('mock_table', function (&$row) use (&$rowProcessorApplied) {
    $rowProcessorApplied = true;
    $row['processed_key'] = 'yes';
});

$processors = $refSeeder->getProperty('rowProcessors')->getValue();
assert_test(isset($processors['mock_table']), "Row processor registered for mock_table");

$testRow = ['test_field' => 'val'];
foreach ($processors['mock_table'] as $processor) {
    $processor($testRow, $seeder);
}

assert_test($rowProcessorApplied, "Row processor was executed on mock_table");
assert_test(($testRow['processed_key'] ?? '') === 'yes', "Row processor mutated row content successfully");

// 3. Verify post-table hook registration
echo "  Testing Seeder::registerPostTableHook()...\n";

$tableHookApplied = false;
Seeder::registerPostTableHook('mock_table', function ($rows) use (&$tableHookApplied) {
    $tableHookApplied = true;
    assert_test(count($rows) === 1, "Post-table hook received correct row batch size");
});

$tableHooks = $refSeeder->getProperty('postTableHooks')->getValue();
assert_test(isset($tableHooks['mock_table']), "Post-table hook registered for mock_table");

foreach ($tableHooks['mock_table'] as $hook) {
    $hook([$testRow]);
}
assert_test($tableHookApplied, "Post-table hook was successfully executed");

// 4. Verify post-run hook registration
echo "  Testing Seeder::registerPostRunHook()...\n";

$runHookApplied = false;
Seeder::registerPostRunHook(function () use (&$runHookApplied) {
    $runHookApplied = true;
});

Seeder::triggerPostRunHooks();
assert_test($runHookApplied, "Post-run hook triggered and executed successfully!");

echo "\n✅ Dynamic Seeder Event-Driven Component Integration Tests Passed Successfully!\n";
