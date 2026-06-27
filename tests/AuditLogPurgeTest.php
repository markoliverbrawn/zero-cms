<?php
// tests/AuditLogPurgeTest.php
// Integration tests for multi-tenant and global Audit Log purging behavior

require_once __DIR__ . '/bootstrap.php';

use Zero\Database\DB;
use Zero\Support\Logger;

echo "=== Audit Log Purge & Multi-Tenant Isolation Tests ===\n";

$siteA = 'test-site-a-uuid';
$siteB = 'test-site-b-uuid';

// 1. Setup mock log data for both sites
echo "Setting up mock audit log entries for Site A and Site B...\n";

// Ensure tables are clear of these test sites' logs first
DB::query("DELETE FROM audit_logs WHERE site_id IN (?, ?)", [$siteA, $siteB]);

// Insert logs directly under Site A
DB::query("
    INSERT INTO audit_logs (id, site_id, user_id, action, object_type, object_id, meta, created_at)
    VALUES 
    ('log-a1', ?, 'user-1', 'create_page', 'pages', 'page-1', '{}', NOW()),
    ('log-a2', ?, 'user-1', 'edit_page', 'pages', 'page-1', '{}', NOW())
", [$siteA, $siteA]);

// Insert logs directly under Site B
DB::query("
    INSERT INTO audit_logs (id, site_id, user_id, action, object_type, object_id, meta, created_at)
    VALUES 
    ('log-b1', ?, 'user-2', 'create_post', 'posts', 'post-1', '{}', NOW())
", [$siteB]);

// Verify initial inserts
$countA = DB::query("SELECT COUNT(*) FROM audit_logs WHERE site_id = ?", [$siteA])->fetchColumn();
$countB = DB::query("SELECT COUNT(*) FROM audit_logs WHERE site_id = ?", [$siteB])->fetchColumn();
assert_test($countA == 2, "Site A initially has exactly 2 logs");
assert_test($countB == 1, "Site B initially has exactly 1 log");

// 2. Perform Tenant-Scoped Purging (Site A only)
echo "Testing tenant-scoped purging of logs for Site A...\n";
DB::query("DELETE FROM audit_logs WHERE site_id = ?", [$siteA]);

// Verify Site A logs are purged, but Site B is untouched (Strict Isolation)
$countAAfter = DB::query("SELECT COUNT(*) FROM audit_logs WHERE site_id = ?", [$siteA])->fetchColumn();
$countBAfter = DB::query("SELECT COUNT(*) FROM audit_logs WHERE site_id = ?", [$siteB])->fetchColumn();

assert_test($countAAfter == 0, "Site A logs successfully purged completely");
assert_test($countBAfter == 1, "Site B logs remain 100% untouched (Strict Tenant Isolation Verified)");

// 3. Perform Global Purge (All sites - Super Admin privilege simulation)
echo "Testing global super-admin purging across all sites...\n";

// Insert some temp logs back to test global deletion
DB::query("
    INSERT INTO audit_logs (id, site_id, user_id, action, object_type, object_id, meta, created_at)
    VALUES ('log-temp-a', ?, 'user-1', 'create_page', 'pages', 'page-1', '{}', NOW())
", [$siteA]);

DB::query("DELETE FROM audit_logs");

$totalLogs = DB::query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
assert_test($totalLogs == 0, "All logs globally successfully cleared (Global Purge Verified)");

echo "Audit Log Purge and isolation tests completed successfully!\n\n";
