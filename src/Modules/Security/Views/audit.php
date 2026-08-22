<?php
// src/Modules/Security/Views/audit.php

use Zero\Core\App;
use Zero\Models\User;
use Zero\Support\AssetVersion;
use Zero\Support\I18n;
use Zero\Support\Str;

?>
<div class="security-audit-container">
    <div class="model-edit-header" style="border-bottom: 2px solid var(--border-color, #cbd5e1); margin-bottom: 25px; padding-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="icon-svg" style="color: var(--accent-color, #0056b3); width: 24px; height: 24px; display: inline-block; vertical-align: middle;"><?php echo App::svg('shield'); ?></span>
            <h2 style="display: inline-block; vertical-align: middle; margin: 0;">Security Audits</h2>
        </div>
        <div id="audit-trigger-box">
            <button type="button" class="btn" id="btn-trigger-audit" style="cursor: pointer; padding: 10px 20px; font-weight: 700; border: none; border-radius: var(--border-radius); background-color: var(--accent-color, #2563eb); color: white;">+ New Audit Report</button>
        </div>
    </div>

    <!-- Live System Telemetry Metrics Overview Grid -->
    <div class="audit-metrics-grid">
        <div class="audit-metric-card interactive-card" id="card-score">
            <div class="metric-title">Platform Integrity Score</div>
            <?php
            $initialScore = 100;
            $isDev = (($telemetry['environment'] ?? '') === 'dev');

            if ($telemetry['install_file_exists'] ?? false) {
                if (!($telemetry['install_file_cli_locked'] ?? false)) {
                    $initialScore -= 30;
                }
            }
            if ($telemetry['benchmarking_enabled'] ?? false) {
                if (!$isDev) {
                    $initialScore -= 10;
                }
            }
            if ($telemetry['default_admin_password_in_use'] ?? false) {
                if ($isDev) {
                    $initialScore -= 5;
                } else {
                    $initialScore -= 25;
                }
            }
            if ($telemetry['storage_directory_open_access'] ?? false) {
                $initialScore -= 15;
            }
            
            $scoreClass = 'score-safe';
            if ($initialScore < 60) {
                $scoreClass = 'score-danger';
            } elseif ($initialScore < 85) {
                $scoreClass = 'score-warn';
            }
            ?>
            <div class="metric-value <?php echo $scoreClass; ?>" id="telemetry-score"><?php echo $initialScore; ?>/100</div>
        </div>
        <div class="audit-metric-card interactive-card" id="card-admins">
            <div class="metric-title">Administrators</div>
            <div class="metric-value"><?php echo intval($telemetry['total_super_admins'] ?? 0); ?></div>
        </div>
        <div class="audit-metric-card interactive-card" id="card-sites">
            <div class="metric-title">Active Sites</div>
            <div class="metric-value"><?php echo intval($telemetry['total_tenant_sites'] ?? 0); ?></div>
        </div>
        <div class="audit-metric-card interactive-card" id="card-alerts">
            <div class="metric-title">Logged Security Alerts</div>
            <div class="metric-value <?php echo ($telemetry['audit_warnings_logged'] ?? 0) > 0 ? 'score-warn' : ''; ?>">
                <?php echo intval($telemetry['audit_warnings_logged'] ?? 0); ?>
            </div>
        </div>
    </div>

    <!-- Quick Attention Items Diagnostic Drawer (Toggled by clicking Platform Integrity Score card) -->
    <div class="audit-report-container" id="quick-attention-box" style="display: none; margin-bottom: 2.5rem; font-family: monospace;">
        <h3 style="margin-top: 0; color: var(--accent-color, #38bdf8); font-size: 1.4rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; margin-bottom: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 8px;">
            <span class="icon-svg" style="color: #f59e0b; width: 22px; height: 22px; display: inline-block;"><?php echo App::svg('shield'); ?></span>
            Platform Hardening Checks
        </h3>
        <ul style="padding-left: 1.5rem; margin: 0; line-height: 1.6; font-size: 1rem;">
            <?php 
            $warnings = [];
            $isDev = (($telemetry['environment'] ?? '') === 'dev');
            
            if (($telemetry['install_file_exists'] ?? false) && !($telemetry['install_file_cli_locked'] ?? false)) {
                $warnings[] = "<strong style='color: #ef4444;'>[CRITICAL] install.php File in Web Root:</strong> The installation script is present in the web root and is not CLI-locked, posing a critical security risk. Delete the file or lock it strictly to CLI-only execution.";
            }
            if (($telemetry['benchmarking_enabled'] ?? false) && !$isDev) {
                $warnings[] = "<strong style='color: #f59e0b;'>[MEDIUM] Performance Benchmarking Active:</strong> Telemetries are outputting SQL details publicly on production. Set <code>BENCHMARKING=false</code> in <code>.env</code>.";
            }
            if ($telemetry['default_admin_password_in_use'] ?? false) {
                $usernames = implode(', ', array_map(fn($u) => "<code>" . Str::escape($u) . "</code>", $telemetry['default_password_usernames'] ?? []));
                $warnings[] = "<strong style='color: #ef4444;'>[HIGH] Default Credentials Active:</strong> Account(s) {$usernames} still carry default credentials. Update them in <em>Manage Users</em>.";
            }
            if ($telemetry['storage_directory_open_access'] ?? false) {
                $warnings[] = "<strong style='color: #f59e0b;'>[MEDIUM] Storage Upload Folder Unprotected:</strong> Direct PHP execution is allowed. Hardening via <code>storage/.htaccess</code> is recommended.";
            }
            
            if (empty($warnings)) {
                echo "<li style='color: #10b981; list-style-type: none; margin-left: -1.5rem;'>All platform health indicators are compliant! No items need attention.</li>";
            } else {
                foreach ($warnings as $warn) {
                    echo "<li style='margin-bottom: 0.75rem;'>{$warn}</li>";
                }
            }
            ?>
        </ul>
    </div>

    <!-- Real-time Pulsing Loader Component -->
    <div class="audit-loader" id="audit-progress-loader">
        <div class="loader-spinner"></div>
        <p>Gemini is performing a deep architectural threat-modeling scan of the Zero CMS core...</p>
    </div>

    <!-- Rendered Markdown Security Report Card -->
    <div class="audit-report-container" id="audit-report-viewer"></div>

    <!-- Archived Audits History Section -->
    <div class="model-edit-header" style="border-bottom: 2px solid var(--border-color, #cbd5e1); margin-top: 3rem; margin-bottom: 20px; padding-bottom: 10px; display: flex; align-items: center; gap: 8px;">
        <span class="icon-svg" style="color: var(--accent-color, #2563eb); width: 20px; height: 24px; display: inline-block; vertical-align: middle;"><?php echo App::svg('clipboard'); ?></span>
        <h3 style="margin: 0; font-size: 1.3rem; font-weight: 800; display: inline-block; vertical-align: middle;">Archived Audits History</h3>
    </div>

    <div class="listrecords" style="margin-top: 0;">
        <?php if (empty($pastAudits)): ?>
            <p class="text-muted" style="text-align: center; padding: 2.5rem 0; margin: 0;"><?php echo I18n::t('no_records_found'); ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 40%;">Execution Time</th>
                        <th style="width: 20%; text-align: center;">Security Score</th>
                        <th style="width: 20%; text-align: center;">Environment</th>
                        <th style="width: 20%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pastAudits as $audit): ?>
                        <tr>
                            <td data-label="Execution Time" style="font-weight: 600;">
                                <?php echo Str::escape(I18n::localizeDateTime($audit['created_at'], 'M d, Y H:i:s')); ?>
                            </td>
                            <td data-label="Security Score" style="text-align: center; font-weight: 800;">
                                <?php
                                $score = intval($audit['score']);
                                $color = '#10b981';
                                if ($score < 60) $color = '#ef4444';
                                elseif ($score < 85) $color = '#f59e0b';
                                ?>
                                <span style="color: <?php echo $color; ?>;"><?php echo $score; ?>/100</span>
                            </td>
                            <td data-label="Environment" style="text-align: center; text-transform: uppercase; font-family: monospace; font-size: 0.9rem;">
                                <?php echo Str::escape($audit['environment']); ?>
                            </td>
                            <td>
                                <!-- Hidden textarea storing raw markdown for browser-side instant loading on click! -->
                                <textarea id="markdown-archive-<?php echo $audit['id']; ?>" style="display: none;"><?php echo Str::escape($audit['report']); ?></textarea>
                                
                                <button type="button" class="btn-view-report" data-id="<?php echo $audit['id']; ?>" style="margin-right: 12px; cursor: pointer; background: none; border: none; color: var(--accent-color); font-weight: 500; padding: 0;">View Report</button>
                                <a href="/admin/list/security_audits?delete=<?php echo $audit['id']; ?>" onclick="return confirm('Are you sure you want to permanently delete this archived audit report?');" style="color: #ef4444; font-weight: 500; text-decoration: none;">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Load External Zero-Dependency Interactive Audit Scripts -->
<script src="<?php echo Str::escape(AssetVersion::url('/assets/js/admin/security_audit.js')); ?>"></script>
