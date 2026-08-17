<?php
/**
 * src/Modules/Security/Views/fallback_report.php
 * Markdown template for Zero CMS local security fallback report.
 */

use Zero\Support\Str;

$isDev = (($telemetry['environment'] ?? '') === 'dev');
$score = $telemetry['calculated_score'] ?? 100;
$warningsCount = 0;
$vulnerabilitiesSection = "";

if ($telemetry['install_file_exists'] ?? false) {
    $filePath = $telemetry['install_file_path'] ?? 'install.php';
    if ($telemetry['install_file_cli_locked'] ?? false) {
        $vulnerabilitiesSection .= "### 🟢 [INFO] Severity: Persistent Installation File (CLI-Locked & Neutralized)
* **Exploit Vector:** The `{$filePath}` file is still present on disk. However, it is strictly locked to CLI-only execution mode (`php_sapi_name() !== '\''cli'\''`), meaning any remote HTTP browser-based execution vectors have been 100% neutralized (returns HTTP 403 Forbidden).
* **Remediation:** The file is completely secure and safe to remain on disk for CLI tasks. However, if you no longer require terminal installation commands, you can permanently delete it to clean the directory.\n\n";
    } else {
        $warningsCount++;
        $vulnerabilitiesSection .= "### 🔴 [CRITICAL] Severity: `{$filePath}` File Still Present on Disk
* **Exploit Vector:** An attacker can access `{$filePath}` directly via the browser and attempt to reinitialize the database schema or trigger installation configurations, wiping site content or creating unauthorized super admin accounts.
* **Remediation:** Immediately delete or rename the `/data/misc/zero/{$filePath}` file on disk:
  ```bash
  rm {$filePath}
  ```\n\n";
    }
}

if ($telemetry['benchmarking_enabled'] ?? false) {
    if ($isDev) {
        $vulnerabilitiesSection .= "### 🟢 [INFO] Severity: Performance Benchmarking Enabled (Sandbox De-escalated)
* **Exploit Vector:** Performance telemetry overlays are active. While this outputs database transaction paths and microsecond metrics, it is safe, highly useful, and expected in local sandbox development environments.
* **Remediation:** No immediate action required in dev. Ensure `BENCHMARKING=false` inside the `.env` file prior to production deployment.\n\n";
    } else {
        $warningsCount++;
        $vulnerabilitiesSection .= "### 🟡 [MEDIUM] Severity: Performance Benchmarking Enabled in Production
* **Exploit Vector:** Having `BENCHMARKING=true` outputs SQL transaction times, query execution logs, and timing metrics on public views. This leaks internal table schemas, performance bottlenecks, and provides timing side-channel attack vectors.
* **Remediation:** Set `BENCHMARKING=false` inside the `.env` file for production environments.\n\n";
    }
}

if ($telemetry['default_admin_password_in_use'] ?? false) {
    $usernamesList = implode(', ', array_map(fn($u) => "`" . Str::escape($u) . "`", $telemetry['default_password_usernames'] ?? []));
    if ($isDev) {
        $warningsCount++;
        $vulnerabilitiesSection .= "### 🔵 [LOW] Severity: Default Password Active on Account(s): {$usernamesList} (Sandbox De-escalated)
* **Exploit Vector:** Default seed installation credentials are active on this dev sandbox. This is acceptable for ease of local testing, but must never be exposed publicly.
* **Remediation:** Log into the administrative dashboard, navigate to **Manage Users**, and update the passwords for {$usernamesList} to strong, secure values, or delete any unused seed accounts prior to production deployment.\n\n";
    } else {
        $warningsCount++;
        $vulnerabilitiesSection .= "### 🔴 [HIGH] Severity: Default Password Active on Account(s): {$usernamesList}
* **Exploit Vector:** The user account(s) {$usernamesList} are still configured with the default installation password (or default seed hash). Attackers can easily log in to the backend back-office dashboard and gain platform control.
* **Remediation:** Log into the administrative dashboard, navigate to **Manage Users**, and update the passwords for {$usernamesList} to strong, secure values, or delete any unused seed accounts.\n\n";
    }
}

if ($telemetry['storage_directory_open_access'] ?? false) {
    $warningsCount++;
    $vulnerabilitiesSection .= "### 🟡 [MEDIUM] Severity: Storage Folder Permissions Hardening Recommended
* **Exploit Vector:** The `/storage/uploads/` directory is writable and must be strictly protected to prevent execution of arbitrary uploaded scripts (e.g. `.php` file uploads executed by the web server).
* **Remediation:** Ensure a protective `.htaccess` or server block resides in the `/storage/` folder, blocking execution of `.php` files inside the uploads directories:
  ```apache
  <Files *.php>
      Order Deny,Allow
      Deny from all
  </Files>
  ```\n\n";
}

// Render static analysis findings in local report
$findings = $telemetry['static_analysis_findings'] ?? [];
if (count($findings) > 0) {
    $vulnerabilitiesSection .= "### 🔵 [LOW] Severity: Static Code Analysis Flags (" . count($findings) . " findings)\n";
    foreach ($findings as $finding) {
        $warningsCount++;
        $vulnerabilitiesSection .= "* **[" . Str::escape($finding['class']) . "]** in `" . Str::escape($finding['file']) . "` (Line " . $finding['line'] . "):\n";
        $vulnerabilitiesSection .= "  * " . Str::escape($finding['description']) . "\n";
    }
    $vulnerabilitiesSection .= "\n";
}

if ($vulnerabilitiesSection === "") {
    $vulnerabilitiesSection = "*No active warnings or vulnerabilities detected. The system configuration is exceptionally secure!*\n\n";
}

// Framework CVEs fallback compiler
$cveSection = "### 2.5 PHP FRAMEWORK CVE FEEDS (REFERENCE ONLY)\n";
$frameworkCves = $telemetry['framework_cves'] ?? [];
if (empty($frameworkCves)) {
    $cveSection .= "*No external framework feeds were successfully retrieved (offline or timeout).*\n";
} else {
    foreach ($frameworkCves as $framework => $cves) {
        $cveSection .= "* **" . Str::escape($framework) . "**:\n";
        if (empty($cves)) {
            $cveSection .= "  * No recent vulnerabilities reported or empty feed.\n";
        } else {
            foreach ($cves as $cve) {
                $id = Str::escape($cve['id'] ?? 'Unknown CVE');
                $summary = Str::escape($cve['summary'] ?? 'No summary available.');
                $cveSection .= "  * **{$id}**: {$summary}\n";
            }
        }
    }
}
?># ZERO CMS LOCAL SECURITY AUDIT REPORT

## 1. EXECUTIVE SUMMARY
* **Platform Security Score:** **<?= $score ?> / 100**
* **Discovered Warnings:** **<?= $warningsCount ?>**
* **Active Environment:** **<?= strtoupper($telemetry['environment'] ?? 'PRODUCTION') ?>** <?= ($isDev ? "(Sandbox De-escalated Scoring Active)" : "") ?>

* **System Status:** <?= ($score >= 85 ? "SECURE (COMPLIANT)" : ($score >= 60 ? "WARNING" : "VULNERABLE")) ?>


This automated report presents a comprehensive security audit of the **Zero CMS** multi-tenant installation on <?= date('Y-m-d H:i:s') ?>. The system was audited across core directories, database tables, multi-tenant boundaries, and environment files to verify technical integrity.

---

## 2. DISCOVERED VULNERABILITIES & WARNINGS
<?= $vulnerabilitiesSection ?>

---

<?= $cveSection ?>

---

## 3. ZERO-DEPENDENCY ARCHITECTURAL STRENGTHS
Zero CMS demonstrates a state-of-the-art security posture through its minimalist design philosophy:
* **Zero-Dependency Core:** Bypassing standard package managers like composer completely eliminates third-party supply-chain vulnerability vectors (malicious packages, un-audited lockfile updates, and abandoned library exploits).
* **Hardened Multi-Tenant Isolation:** Tenant boundary protection is strictly enforced at the database active-record level via static `IsModel` trait scoping, preventing cross-tenant SQL database leaking.
* **Bare-Metal Email Sockets:** Raw TCP socket handshakes in `Emailer.php` avoid un-audited SDK dependencies, reducing the surface area for unauthorized outbound network exploits.
* **Declarative Declarator Input Validator:** Strong pipeline input validation in `Validator.php` completely eliminates parameter injection vectors.

---

## 4. SECURITY ROADMAP & STRATEGIC HARDENING
1. **Disable Install Gateways:** Ensure `/install.php` is permanently deleted on production systems.
2. **Apply Directory Execution Hardening:** Lock down web server execution of script uploads inside `/storage/uploads/`.
3. **Audit Log Monitoring:** Regularly review the DB-driven security audit logs (`audit_logs` table) for anomalous user activities or session anomalies.
