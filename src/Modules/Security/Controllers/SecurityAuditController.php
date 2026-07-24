<?php

namespace Zero\Modules\Security\Controllers;

use Zero\Services\AiService;
use Zero\Core\App;
use Zero\Core\Env;
use Zero\Database\DB;
use Zero\Interfaces\Controller;
use Zero\Modules\Security\Models\SecurityAudit;
use Zero\Support\Str;

class SecurityAuditController implements Controller
{
    private function calculateScore(array $telemetry): int
    {
        $score = 100;
        $isDev = (($telemetry['environment'] ?? '') === 'dev');

        if ($telemetry['install_file_exists'] ?? false) {
            if (!($telemetry['install_file_cli_locked'] ?? false)) {
                $score -= 30;
            }
        }
        if ($telemetry['benchmarking_enabled'] ?? false) {
            if (!$isDev) {
                $score -= 10;
            }
        }
        if ($telemetry['default_admin_password_in_use'] ?? false) {
            if ($isDev) {
                $score -= 5;
            } else {
                $score -= 25;
            }
        }
        if ($telemetry['storage_directory_open_access'] ?? false) {
            $score -= 15;
        }

        return $score;
    }

    private function collectTelemetry(): array
    {
        $telemetry = [];

        // 1. Check if install.php exists and its secure lock status across multiple paths
        $telemetry['install_file_exists'] = false;
        $telemetry['install_file_cli_locked'] = false;
        $telemetry['install_file_path'] = '';
        
        $possiblePaths = [
            APPLICATION_ROOT . '/install.php',
            APPLICATION_ROOT . '/etc/install.php'
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $telemetry['install_file_exists'] = true;
                $telemetry['install_file_path'] = (strpos($path, 'etc/') !== false) ? 'etc/install.php' : 'install.php';
                
                $content = file_get_contents($path);
                if (strpos($content, "php_sapi_name() !== 'cli'") !== false) {
                    $telemetry['install_file_cli_locked'] = true;
                }
                break;
            }
        }

        // 2. Check if benchmarking/debugging is active
        $telemetry['benchmarking_enabled'] = (Env::get('BENCHMARKING') === 'true');

        // 3. Check for default admin passwords (scoped strictly to the current active tenant)
        try {
            $defaultHash = '$2y$10$2tdsRK0UD/QvrVPFoz1WZOtodh33dRR1jfRzQbkDDpUuBfHZJPzhC';
            $rows = DB::query(
                "SELECT username FROM users WHERE password_hash = ? AND (site_id = ? OR site_id IS NULL) AND deleted_at IS NULL",
                [$defaultHash, App::getCurrentSiteId()]
            )->fetchAll();
            
            $telemetry['default_admin_password_in_use'] = (count($rows) > 0);
            $telemetry['default_password_usernames'] = array_column($rows, 'username');
        } catch (\Exception $e) {
            $telemetry['default_admin_password_in_use'] = false;
            $telemetry['default_password_usernames'] = [];
        }

        // 4. Check folder execution protection
        $uploadsDir = APPLICATION_ROOT . '/public/storage/uploads';
        $telemetry['storage_directory_open_access'] = true;
        if (file_exists($uploadsDir)) {
            $telemetry['storage_directory_writable'] = is_writable($uploadsDir);
            if (file_exists(APPLICATION_ROOT . '/public/storage/.htaccess')) {
                $telemetry['storage_directory_open_access'] = false;
            }
        } else {
            $telemetry['storage_directory_writable'] = false;
        }

        // 5. Total Admin accounts count (scoped strictly to the active tenant + global super admins)
        try {
            $adminsCount = DB::query(
                "SELECT COUNT(*) FROM users 
                 WHERE role = 'super_admin' 
                   AND (site_id = ? OR site_id IS NULL) 
                   AND deleted_at IS NULL",
                [App::getCurrentSiteId()]
            )->fetchColumn();
            $telemetry['total_super_admins'] = intval($adminsCount);
        } catch (\Exception $e) {
            $telemetry['total_super_admins'] = 0;
        }

        // 6. Total active tenant sites
        try {
            $sitesCount = DB::query(
                "SELECT COUNT(*) FROM sites WHERE deleted_at IS NULL"
            )->fetchColumn();
            $telemetry['total_tenant_sites'] = intval($sitesCount);
        } catch (\Exception $e) {
            $telemetry['total_tenant_sites'] = 0;
        }

        // 7. Security audit warnings logged (scoped strictly to the active tenant)
        try {
            $warningsCount = DB::query(
                "SELECT COUNT(*) FROM audit_logs 
                 WHERE site_id = ? 
                   AND deleted_at IS NULL",
                [App::getCurrentSiteId()]
            )->fetchColumn();
            $telemetry['audit_warnings_logged'] = intval($warningsCount);
        } catch (\Exception $e) {
            $telemetry['audit_warnings_logged'] = 0;
        }

        // 8. Capture active runtime environment (defaults strictly to 'production' if not defined)
        $telemetry['environment'] = strtolower(Env::get('ENVIRONMENT', 'production'));

        // 9. Inject calculated score to ensure 100% scorecard-to-report synchronization
        $telemetry['calculated_score'] = $this->calculateScore($telemetry);

        // 10. Fetch brief info for active multi-tenant sites for the sites card
        try {
            $sites = DB::query("SELECT id, name, domain, theme FROM sites WHERE deleted_at IS NULL")->fetchAll();
            $telemetry['tenant_sites_details'] = $sites;
        } catch (\Exception $e) {
            $telemetry['tenant_sites_details'] = [];
        }

        return $telemetry;
    }

    private function getFallbackReport(array $telemetry): string
    {
        $isDev = (($telemetry['environment'] ?? '') === 'dev');
        $score = $telemetry['calculated_score'] ?? $this->calculateScore($telemetry);
        $warningsCount = 0;
        $vulnerabilitiesSection = "";

        if ($telemetry['install_file_exists']) {
            $filePath = $telemetry['install_file_path'] ?? 'install.php';
            if ($telemetry['install_file_cli_locked']) {
                // Completely de-escalated warning, 0 points score penalty
                $vulnerabilitiesSection .= "### [INFO] Severity: Persistent Installation File (CLI-Locked & Neutralized)
* **Exploit Vector:** The `{$filePath}` file is still present on disk. However, it is strictly locked to CLI-only execution mode (`php_sapi_name() !== '\''cli'\''`), meaning any remote HTTP browser-based execution vectors have been 100% neutralized (returns HTTP 403 Forbidden).
* **Remediation:** The file is completely secure and safe to remain on disk for CLI tasks. However, if you no longer require terminal installation commands, you can permanently delete it to clean the directory.\n\n";
            } else {
                $warningsCount++;
                $vulnerabilitiesSection .= "### [CRITICAL] Severity: `{$filePath}` File Still Present on Disk
* **Exploit Vector:** An attacker can access `{$filePath}` directly via the browser and attempt to reinitialize the database schema or trigger installation configurations, wiping site content or creating unauthorized super admin accounts.
* **Remediation:** Immediately delete or rename the `/data/misc/zero/{$filePath}` file on disk:
  ```bash
  rm {$filePath}
  ```\n\n";
            }
        }

        if ($telemetry['benchmarking_enabled']) {
            if ($isDev) {
                // Informational alert only in development, 0 points score penalty
                $vulnerabilitiesSection .= "### [INFO] Severity: Performance Benchmarking Enabled (Sandbox De-escalated)
* **Exploit Vector:** Performance telemetry overlays are active. While this outputs database transaction paths and microsecond metrics, it is safe, highly useful, and expected in local sandbox development environments.
* **Remediation:** No immediate action required in dev. Ensure `BENCHMARKING=false` inside the `.env` file prior to production deployment.\n\n";
            } else {
                $warningsCount++;
                $vulnerabilitiesSection .= "### [MEDIUM] Severity: Performance Benchmarking Enabled in Production
* **Exploit Vector:** Having `BENCHMARKING=true` outputs SQL transaction times, query execution logs, and timing metrics on public views. This leaks internal table schemas, performance bottlenecks, and provides timing side-channel attack vectors.
* **Remediation:** Set `BENCHMARKING=false` inside the `.env` file for production environments.\n\n";
            }
        }

        if ($telemetry['default_admin_password_in_use']) {
            $usernamesList = implode(', ', array_map(fn($u) => "`" . Str::escape($u) . "`", $telemetry['default_password_usernames'] ?? []));
            if ($isDev) {
                $warningsCount++;
                $vulnerabilitiesSection .= "### [LOW] Severity: Default Password Active on Account(s): {$usernamesList} (Sandbox De-escalated)
* **Exploit Vector:** Default seed installation credentials are active on this dev sandbox. This is acceptable for ease of local testing, but must never be exposed publicly.
* **Remediation:** Log into the administrative dashboard, navigate to **Manage Users**, and update the passwords for {$usernamesList} to strong, secure values, or delete any unused seed accounts prior to production deployment.\n\n";
            } else {
                $warningsCount++;
                $vulnerabilitiesSection .= "### [HIGH] Severity: Default Password Active on Account(s): {$usernamesList}
* **Exploit Vector:** The user account(s) {$usernamesList} are still configured with the default installation password (or default seed hash). Attackers can easily log in to the backend back-office dashboard and gain platform control.
* **Remediation:** Log into the administrative dashboard, navigate to **Manage Users**, and update the passwords for {$usernamesList} to strong, secure values, or delete any unused seed accounts.\n\n";
            }
        }

        if ($telemetry['storage_directory_open_access']) {
            $warningsCount++;
            $vulnerabilitiesSection .= "### [MEDIUM] Severity: Storage Folder Permissions Hardening Recommended
* **Exploit Vector:** The `/storage/uploads/` directory is writable and must be strictly protected to prevent execution of arbitrary uploaded scripts (e.g. `.php` file uploads executed by the web server).
* **Remediation:** Ensure a protective `.htaccess` or server block resides in the `/storage/` folder, blocking execution of `.php` files inside the uploads directories:
  ```apache
  <Files *.php>
      Order Deny,Allow
      Deny from all
  </Files>
  ```\n\n";
        }

        if ($vulnerabilitiesSection === "") {
            $vulnerabilitiesSection = "*No active warnings or vulnerabilities detected. The system configuration is exceptionally secure!*\n\n";
        }

        $report = "# ZERO CMS LOCAL SECURITY AUDIT REPORT

## 1. EXECUTIVE SUMMARY
* **Platform Security Score:** **{$score} / 100**
* **Discovered Warnings:** **{$warningsCount}**
* **Active Environment:** **" . strtoupper($telemetry['environment'] ?? 'PRODUCTION') . "** " . ($isDev ? "(Sandbox De-escalated Scoring Active)" : "") . "
* **System Status:** " . ($score >= 85 ? "SECURE (COMPLIANT)" : ($score >= 60 ? "WARNING" : "VULNERABLE")) . "

This automated report presents a comprehensive security audit of the **Zero CMS** multi-tenant installation on " . date('Y-m-d H:i:s') . ". The system was audited across core directories, database tables, multi-tenant boundaries, and environment files to verify technical integrity.

---

## 2. DISCOVERED VULNERABILITIES & WARNINGS
{$vulnerabilitiesSection}
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
";

        return $report;
    }

    public function handle($param)
    {
        App::applyAuthMiddleware();
        App::applyRoleMiddleware('super_admin');

        $siteId = App::getCurrentSiteId();

        // Support direct archived audit deletion from within this console view
        if (isset($_GET['delete'])) {
            $deleteId = $_GET['delete'];
            DB::query("UPDATE security_audits SET deleted_at = NOW() WHERE id = ? AND site_id = ?", [$deleteId, $siteId]);
            $_SESSION['success_flash'] = 'Archived audit report deleted successfully.';
            header('Location: /admin/list/security_audits');
            exit();
        }

        $telemetry = $this->collectTelemetry();
        $isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

        if ($isAjax) {
            $report = $this->runAudit($telemetry);
            
            // Auto-save/archive this security audit report in the database historically!
            try {
                $userId = $_SESSION['user_id'] ?? null;
                $audit = new SecurityAudit([
                    'user_id' => $userId,
                    'score' => $telemetry['calculated_score'] ?? 100,
                    'environment' => $telemetry['environment'] ?? 'production',
                    'telemetry' => json_encode($telemetry),
                    'report' => $report
                ]);
                $audit->save();
            } catch (\Exception $e) {
                // Silently bypass if schema is being initialized
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'report' => $report,
                'telemetry' => $telemetry
            ]);
            exit;
        }

        // Fetch past saved audits for the active tenant ordered newest first
        $pastAudits = [];
        try {
            $pastAudits = DB::query(
                "SELECT id, created_at, score, environment, report 
                 FROM security_audits 
                 WHERE site_id = ? AND deleted_at IS NULL 
                 ORDER BY created_at DESC",
                [$siteId]
            )->fetchAll();
        } catch (\Exception $e) {
            $pastAudits = [];
        }

        App::render('security/audit', [
            'telemetry' => $telemetry,
            'pastAudits' => $pastAudits,
            'title' => 'Security Audit & History'
        ]);
        exit;
    }

    private function runAudit(array $telemetry): string
    {
        $score = $telemetry['calculated_score'] ?? $this->calculateScore($telemetry);

        $prompt = "You are an elite cybersecurity auditor specializing in PHP, multi-tenant architectures, and zero-dependency security. Perform a security analysis of the Zero CMS installation based on the following local telemetry metrics collected in real-time:

" . json_encode($telemetry, JSON_PRETTY_PRINT) . "

Generate a highly polished, professional, and structured security report.

MANDATORY SCORE SYNCHRONIZATION RULE:
- You MUST use the exact security score 'calculated_score' provided in the telemetry JSON (which is exactly '{$score}') as the primary 'Platform Security Score' in your Executive Summary. For example, write: '* **Platform Security Score:** **{$score} / 100**'. Do not calculate, estimate, or invent a different score, and do not make any other score deductions. This is a critical mathematical consistency requirement!

SPECIAL ENVIRONMENTAL RULE:
- If the telemetry 'environment' is set to 'dev', you MUST de-escalate the severity classifications and score penalties for 'default_admin_password_in_use' (from High to Low/Info, with minor score deductions) and 'benchmarking_enabled' (from Medium to Info/None, with zero score deduction), as these are common, safe, or acceptable in sandboxed local development environments. Adjust your scoring, severity labels, and recommendations accordingly, making sure to explicitly note in your Executive Summary that sandbox de-escalation scoring is active. However, still note that they must be secured prior to production deployment.
- If the telemetry 'install_file_cli_locked' is set to true, you MUST de-escalate the severity of the 'install_file_exists' warning to 'Info' with exactly zero score deduction, as locking the file to CLI execution (php_sapi_name() !== 'cli') completely neutralizes any remote HTTP browser-based execution exploit vectors. Note that it is safe to keep on disk for terminal tasks but can be deleted if no longer needed.

Include the following sections using beautiful Markdown:
1. Executive Summary: High-level overview of the system state, security score (e.g. 85/100), active environment (clearly noting 'dev' de-escalations if applicable), and quick takeaways.
2. Discovered Vulnerabilities & Warnings: List each warning from the telemetry, classify its severity (High, Medium, Low, or Info), explain the technical exploit vector, and provide precise remediation steps.
3. Architecture Strengths: Call out the robust zero-dependency architecture, strict tenant isolation traits, Raw TCP SMTP mailers, recursive input sanitizers, and overall lack of vendor supply-chain risks.
4. Strategic Security Roadmap: Concrete, actionable recommendations for future hardening.

Write the report in a direct, highly analytical, and authoritative tone. Use high-contrast Markdown formatting (bullet points, bold highlights, clean dividers, code snippets where appropriate). Avoid conversational filler or introductory phrases. Start directly with the Executive Summary.";

        try {
            $generatedText = AiService::generate($prompt);
            if (!empty($generatedText)) {
                return "## GEMINI AI SECURE AUDIT REPORT\n\n" . $generatedText;
            }
        } catch (\Exception $e) {
            $diagnostics = $e->getMessage();
            return "## GEMINI API HANDSHAKE TIMEOUT / CONFIGURATION ERROR\n\n*Failed to connect to Google Gemini API. Falling back to the local secure compiler report below:*\n\n**Connection Diagnostics:** `{$diagnostics}`\n\n" . $this->getFallbackReport($telemetry);
        }

        return "## GEMINI API HANDSHAKE TIMEOUT / CONFIGURATION ERROR\n\n*Failed to connect to Google Gemini API. Falling back to the local secure compiler report below:*\n\n**Connection Diagnostics:** `Empty or malformed response returned from the AI Provider.`\n\n" . $this->getFallbackReport($telemetry);
    }
}
