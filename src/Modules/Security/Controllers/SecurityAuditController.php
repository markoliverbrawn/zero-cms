<?php
/**
 * File: src/Modules/Security/Controllers/SecurityAuditController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Security\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */



namespace Zero\Modules\Security\Controllers;

use Zero\Core\App;
use Zero\Core\Env;
use Zero\Core\Template;
use Zero\Database\DB;
use Zero\Interfaces\Controller;
use Zero\Modules\Security\Models\SecurityAudit;
use Zero\Modules\Security\Services\CveFetcherService;
use Zero\Modules\Security\Services\ExploitScanner;
use Zero\Services\AiService;
use Zero\Support\Str;

/**
 * Class SecurityAuditController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class SecurityAuditController implements Controller
{
    /**
     * Calculate score processing implementation helper.
     *
     * @param array $telemetry Argument descriptor.
     * @return int Response output.
     */
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

        // Apply a small deduction if static analysis finds certain issues
        $findingsCount = count($telemetry['static_analysis_findings'] ?? []);
        if ($findingsCount > 0) {
            // Cap the deduction at 15 points
            $score -= min($findingsCount * 5, 15);
        }

        return max($score, 0);
    }

    /**
     * Collect telemetry processing implementation helper.
     *
     * @return mixed Response output.
     */
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

        // 9. Fetch recent CVEs from OSV for major framework packages
        try {
            $telemetry['framework_cves'] = [
                'laravel/framework' => CveFetcherService::fetchRecentAdvisories('laravel/framework', 3),
                'symfony/security-core' => CveFetcherService::fetchRecentAdvisories('symfony/security-core', 3),
                'wordpress/core' => CveFetcherService::fetchRecentAdvisories('wordpress/core', 3),
            ];
        } catch (\Exception $e) {
            $telemetry['framework_cves'] = [];
        }

        // 10. Perform static analysis on the local zero-dependency codebase
        try {
            $telemetry['static_analysis_findings'] = ExploitScanner::scanCodebase();
        } catch (\Exception $e) {
            $telemetry['static_analysis_findings'] = [];
        }

        // 11. Inject calculated score to ensure 100% scorecard-to-report synchronization
        $telemetry['calculated_score'] = $this->calculateScore($telemetry);

        // 12. Fetch brief info for active multi-tenant sites for the sites card
        try {
            $sites = DB::query("SELECT id, name, domain, theme FROM sites WHERE deleted_at IS NULL")->fetchAll();
            $telemetry['tenant_sites_details'] = $sites;
        } catch (\Exception $e) {
            $telemetry['tenant_sites_details'] = [];
        }

        return $telemetry;
    }

    /**
     * Retrieves the fallback report attribute value.
     *
     * @param array $telemetry Argument descriptor.
     * @return string Response output.
     */
    private function getFallbackReport(array $telemetry): string
    {
        return Template::renderFile(
            APPLICATION_ROOT . '/src/Modules/Security/Views/fallback_report.php',
            ['telemetry' => $telemetry]
        );
    }

    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
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

    /**
     * Run audit processing implementation helper.
     *
     * @param array $telemetry Argument descriptor.
     * @return string Response output.
     */
    private function runAudit(array $telemetry): string
    {
        $score = $telemetry['calculated_score'] ?? $this->calculateScore($telemetry);

        $prompt = "You are an elite cybersecurity auditor specializing in PHP, multi-tenant architectures, and zero-dependency security. Perform a security analysis of the Zero CMS installation based on the following local telemetry metrics collected in real-time, which now includes recent framework CVE database entries and local static analysis exploit scans:

" . json_encode($telemetry, JSON_PRETTY_PRINT) . "

Generate a highly polished, professional, and structured security report.

MANDATORY SCORE SYNCHRONIZATION RULE:
- You MUST use the exact security score 'calculated_score' provided in the telemetry JSON (which is exactly '{$score}') as the primary 'Platform Security Score' in your Executive Summary. For example, write: '* **Platform Security Score:** **{$score} / 100**'. Do not calculate, estimate, or invent a different score, and do not make any other score deductions. This is a critical mathematical consistency requirement!

SPECIAL ENVIRONMENTAL RULE:
- If the telemetry 'environment' is set to 'dev', you MUST de-escalate the severity classifications and score penalties for 'default_admin_password_in_use' (from High to Low/Info, with minor score deductions) and 'benchmarking_enabled' (from Medium to Info/None, with zero score deduction), as these are common, safe, or acceptable in sandboxed local development environments. Adjust your scoring, severity labels, and recommendations accordingly, making sure to explicitly note in your Executive Summary that sandbox de-escalation scoring is active. However, still note that they must be secured prior to production deployment.
- If the telemetry 'install_file_cli_locked' is set to true, you MUST de-escalate the severity of the 'install_file_exists' warning to 'Info' with exactly zero score deduction, as locking the file to CLI execution (php_sapi_name() !== 'cli') completely neutralizes any remote HTTP browser-based execution exploit vectors. Note that it is safe to keep on disk for terminal tasks but can be deleted if no longer needed.

FRAMEWORK CVE & CODEBASE SCANS ANALYSIS:
- Analyze the `framework_cves` provided for Laravel, Symfony, and WordPress. In a dedicated section of your report, explain how these vulnerabilities manifest in their respective frameworks at a low level.
- Contrast these CVE vulnerability patterns with Zero CMS's zero-dependency implementation. For example, explain how we avoid PHP Object Injection because we don't use `unserialize()`, how we prevent SQL Injection in our custom `DB::query` class by avoiding dynamic variable injection inside queries, how we prevent CSRF timing attacks using `hash_equals()` comparisons in our core Security module, or how our lack of third-party package loaders eliminates dependency-chain supply risk completely.
- Review the `static_analysis_findings` from our local exploit scanner. Provide an expert assessment of each flagged finding: explain if it represents a genuine side-channel risk, or if it is a false-positive de-escalated by our structural safeguards (e.g., helper models or isolated database contexts), and provide exact remediation steps for any true findings.

Include the following sections using beautiful Markdown:
1. Executive Summary: High-level overview of the system state, security score (e.g. 85/100), active environment (clearly noting 'dev' de-escalations if applicable), and quick takeaways.
2. Discovered Vulnerabilities & Warnings: List each warning from the telemetry, classify its severity (High, Medium, Low, or Info), explain the technical exploit vector, and provide precise remediation steps. Include a detailed evaluation of our local static analysis findings.
3. PHP Framework CVE Comparative Audit: Detail the fetched framework vulnerabilities and contrast their exploit mechanics against Zero CMS's bare-metal, low-level implementation. Highlight how our zero-dependency design mitigates or bypasses these risk vectors.
4. Architecture Strengths: Call out the robust zero-dependency architecture, strict tenant isolation traits, Raw TCP SMTP mailers, recursive input sanitizers, and overall lack of vendor supply-chain risks.
5. Strategic Security Roadmap: Concrete, actionable recommendations for future hardening.

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
