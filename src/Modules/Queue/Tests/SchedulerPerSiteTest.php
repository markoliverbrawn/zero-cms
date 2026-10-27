<?php
// src/Modules/Queue/Tests/SchedulerPerSiteTest.php
// Verifies per-site schedules: a task registered with a resolver callable is evaluated against
// each site's own settings, 'off' (and any unknown expression) never dispatches, and the Security
// module's audit schedule and report recipient come from that site's settings.

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Job as JobInterface;
use Zero\Models\Site;
use Zero\Modules\Queue\Support\Scheduler;
use Zero\Modules\Security\Jobs\SecurityAuditJob;

/**
 * Never executed -- the test only checks what the scheduler dispatches.
 */
class PerSiteScheduleProbeJob implements JobInterface
{
    public function execute(array $payload): void
    {
    }
}

echo "=== Per-Site Scheduler Tests ===\n";

// Pin the env default before bootstrap, which is when the Security module reads it.
putenv('SECURITY_AUDIT_SCHEDULE=daily');
App::bootstrap();

$makeSite = function (string $label, array $securitySettings): Site {
    $site = new Site([
        'name' => "Schedule {$label}",
        'domain' => 'schedule-' . $label . '-' . bin2hex(random_bytes(4)) . '.zero',
        'theme' => 'default',
        'enabled_modules' => json_encode(['queue']),
        'settings' => json_encode(['security' => $securitySettings]),
    ]);
    $site->save();
    return $site;
};

$dailySite = $makeSite('daily', ['audit_schedule' => 'daily', 'report_email' => 'audits@example.com']);
$offSite = $makeSite('off', ['audit_schedule' => 'off', 'report_email' => 'not-an-email']);
$typoSite = $makeSite('typo', ['audit_schedule' => 'Daily']);
$unsetSite = $makeSite('unset', []);
assert_test($unsetSite->getModuleSetting('security', 'audit_schedule') === 'daily', "An unsaved schedule defaults to SECURITY_AUDIT_SCHEDULE");

$dispatchedFor = function (string $siteId): int {
    $stmt = DB::getPDO()->prepare("SELECT COUNT(*) FROM queue_jobs WHERE job_class = ? AND site_id = ?");
    $stmt->execute([PerSiteScheduleProbeJob::class, $siteId]);
    return (int)$stmt->fetchColumn();
};

// 1. A resolver callable is evaluated per site.
echo "  Testing a per-site schedule resolver...\n";
Scheduler::register(
    PerSiteScheduleProbeJob::class,
    [],
    static fn(Site $site): string => (string)$site->getModuleSetting('security', 'audit_schedule', 'daily')
);
Scheduler::run();

assert_test($dispatchedFor($dailySite->id) === 1, "A site scheduled 'daily' gets the task dispatched");
assert_test($dispatchedFor($offSite->id) === 0, "A site set to 'off' never gets the task, even on its first run");
assert_test($dispatchedFor($typoSite->id) === 0, "An unknown schedule ('Daily') is treated as off rather than running once");
assert_test($dispatchedFor($unsetSite->id) === 1, "A site with no saved setting falls back to the default schedule");

$stmt = DB::getPDO()->prepare("SELECT COUNT(*) FROM queue_scheduled_tasks WHERE task_key = ? AND site_id = ?");
$stmt->execute([PerSiteScheduleProbeJob::class, $offSite->id]);
assert_test((int)$stmt->fetchColumn() === 0, "A switched-off site gets no scheduler tracking row");

// 2. Running again inside the interval does not re-dispatch.
Scheduler::run();
assert_test($dispatchedFor($dailySite->id) === 1, "A daily task is not dispatched twice within the day");

// 3. The Security module registers its audit with the per-site schedule.
echo "  Testing the security audit settings...\n";
$schema = App::getModuleSettingsSchema('security');
assert_test(isset($schema['audit_schedule'], $schema['report_email']), "Security registers audit_schedule and report_email settings");
assert_test(array_key_exists(Scheduler::OFF, $schema['audit_schedule']['options']), "The schedule setting offers 'Off'");
assert_test(!array_key_exists('hourly', $schema['audit_schedule']['options']), "The schedule setting no longer offers 'Hourly'");

// 4. Report recipient: per-site address, else ADMIN_EMAIL.
putenv('ADMIN_EMAIL=fallback@example.com');
assert_test(SecurityAuditJob::recipient($dailySite) === 'audits@example.com', "A site's own report_email receives its report");
assert_test(SecurityAuditJob::recipient($unsetSite) === 'fallback@example.com', "A site without report_email falls back to ADMIN_EMAIL");
assert_test(SecurityAuditJob::recipient($offSite) === 'fallback@example.com', "An invalid report_email falls back to ADMIN_EMAIL");
putenv('ADMIN_EMAIL');
putenv('SECURITY_AUDIT_SCHEDULE');

// Cleanup
foreach ([$dailySite, $offSite, $typoSite, $unsetSite] as $site) {
    DB::getPDO()->prepare("DELETE FROM queue_jobs WHERE site_id = ?")->execute([$site->id]);
    DB::getPDO()->prepare("DELETE FROM queue_scheduled_tasks WHERE site_id = ?")->execute([$site->id]);
    DB::getPDO()->prepare("DELETE FROM sites WHERE id = ?")->execute([$site->id]);
}

echo "Per-site scheduler tests completed successfully!\n\n";
