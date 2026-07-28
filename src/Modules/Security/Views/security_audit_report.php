<?php
/**
 * src/Modules/Security/Views/security_audit_report.php
 * Beautiful, modular security audit email report template housed directly within the Security module.
 */

use Zero\Support\Str;

// Highly robust, zero-dependency simple Markdown-to-HTML parser for email clients
$parseMarkdownToHtml = function (string $markdown): string {
    $lines = explode("\n", $markdown);
    $html = '';
    $inList = false;
    $inCode = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Code block toggle
        if (strpos($trimmed, '```') === 0) {
            if ($inCode) {
                $html .= '</code></pre></div>';
                $inCode = false;
            } else {
                $html .= '<div style="background-color: #0b0f19; border: 1px solid #1e293b; padding: 15px; border-radius: 4px; margin: 15px 0; font-family: monospace; font-size: 0.9rem; color: #38bdf8; overflow-x: auto; word-break: break-all;"><pre style="margin: 0; white-space: pre-wrap;"><code>';
                $inCode = true;
            }
            continue;
        }

        if ($inCode) {
            $html .= Str::escape($line) . "\n";
            continue;
        }

        // State-of-the-art unified Markdown header parser (H1 to H6)
        if (preg_match('/^(#{1,6})\s*(.*?)$/', $trimmed, $headerMatches)) {
            $hashes = $headerMatches[1];
            $text = trim($headerMatches[2]);
            $level = strlen($hashes);

            // Clean up bold highlights or asterisks inside headers (e.g. '#### **Laravel**' -> 'Laravel')
            $text = preg_replace('/^\s*\*\*\s*(.*?)\s*\*\*\s*$/', '$1', $text);
            $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);

            if ($inList) { $html .= '</ul>'; $inList = false; }

            if ($level === 1) {
                $html .= '<h1 style="font-size: 1.6rem; font-weight: 800; color: #ef4444; border-bottom: 2px solid #ef4444; padding-bottom: 8px; margin: 30px 0 15px 0;">' . Str::escape($text) . '</h1>';
            } elseif ($level === 2) {
                $html .= '<h2 style="font-size: 1.3rem; font-weight: 700; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin: 25px 0 12px 0;">' . Str::escape($text) . '</h2>';
            } elseif ($level === 3) {
                $badgeColor = '#64748b'; // default slate
                $bgColor = '#f8fafc';
                $borderColor = '#cbd5e1';

                if (strpos($text, '[CRITICAL]') !== false || strpos($text, '🔴') !== false) {
                    $badgeColor = '#b91c1c';
                    $bgColor = '#fef2f2';
                    $borderColor = '#fca5a5';
                } elseif (strpos($text, '[HIGH]') !== false) {
                    $badgeColor = '#dc2626';
                    $bgColor = '#fff5f5';
                    $borderColor = '#feb2b2';
                } elseif (strpos($text, '[MEDIUM]') !== false || strpos($text, '🟡') !== false) {
                    $badgeColor = '#ea580c';
                    $bgColor = '#fff7ed';
                    $borderColor = '#ffedd5';
                } elseif (strpos($text, '[LOW]') !== false || strpos($text, '🔵') !== false) {
                    $badgeColor = '#d97706';
                    $bgColor = '#fefcbf';
                    $borderColor = '#fef08a';
                } elseif (strpos($text, '[INFO]') !== false || strpos($text, '🟢') !== false) {
                    $badgeColor = '#0891b2';
                    $bgColor = '#ecfeff';
                    $borderColor = '#cffafe';
                }

                $html .= '<div style="background-color: ' . $bgColor . '; border-left: 5px solid ' . $badgeColor . '; border-top: 1px solid ' . $borderColor . '; border-right: 1px solid ' . $borderColor . '; border-bottom: 1px solid ' . $borderColor . '; padding: 15px; border-radius: 6px; margin: 20px 0 15px 0;">';
                $html .= '<h3 style="font-size: 1.05rem; font-weight: 700; color: ' . $badgeColor . '; margin: 0;">' . Str::escape($text) . '</h3>';
                $html .= '</div>';
            } elseif ($level === 4) {
                $html .= '<h4 style="font-size: 1rem; font-weight: 700; color: #334155; margin: 18px 0 8px 0;">' . Str::escape($text) . '</h4>';
            } else {
                $html .= '<h5 style="font-size: 0.95rem; font-weight: 700; color: #475569; margin: 15px 0 6px 0;">' . Str::escape($text) . '</h5>';
            }
            continue;
        }

        // Bullet lists
        if (strpos($trimmed, '* ') === 0 || strpos($trimmed, '- ') === 0) {
            if (!$inList) {
                $html .= '<ul style="padding-left: 0; margin: 10px 0; line-height: 1.6; font-size: 0.95rem; color: #334155; list-style-type: none;">';
                $inList = true;
            }
            $itemText = substr($trimmed, 2);
            // Simple inline bold replacement: **text** -> <strong>text</strong>
            $itemText = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $itemText);
            // Inline code replacement: `code` -> <code style="...">code</code>
            $itemText = preg_replace('/`(.*?)`/', '<code style="background-color: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.9rem; color: #0f172a;">$1</code>', $itemText);

            // Dynamically check for Exploit Vector or Remediation to style them as high-visibility pop-out cards!
            if (str_starts_with($itemText, '<strong>Remediation:</strong>')) {
                $html .= '<li style="margin-bottom: 12px; background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 12px; border-radius: 0 4px 4px 0; color: #166534; font-size: 0.9rem; line-height: 1.5;">' . $itemText . '</li>';
            } elseif (str_starts_with($itemText, '<strong>Exploit Vector:</strong>')) {
                $html .= '<li style="margin-bottom: 12px; background-color: #f8fafc; border-left: 4px solid #64748b; padding: 12px; border-radius: 0 4px 4px 0; color: #334155; font-size: 0.9rem; line-height: 1.5;">' . $itemText . '</li>';
            } else {
                $html .= '<li style="margin-bottom: 8px; padding-left: 15px; text-indent: -15px;">&bull; ' . $itemText . '</li>';
            }
            continue;
        }

        if ($inList && $trimmed === '') {
            $html .= '</ul>';
            $inList = false;
            continue;
        }

        // Dividers
        if ($trimmed === '---') {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<hr style="border: 0; border-top: 1px dashed #cbd5e1; margin: 25px 0;">';
            continue;
        }

        // Normal paragraph
        if ($trimmed !== '') {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $paraText = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $trimmed);
            $paraText = preg_replace('/`(.*?)`/', '<code style="background-color: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.9rem; color: #0f172a;">$1</code>', $paraText);
            $html .= '<p style="font-size: 0.95rem; line-height: 1.6; color: #334155; margin: 12px 0;">' . $paraText . '</p>';
        }
    }

    if ($inList) { $html .= '</ul>'; }
    if ($inCode) { $html .= '</code></pre></div>'; }

    return $html;
};

// Calculate status text & colors based on score
$scoreColor = '#10b981'; // Green (Compliant)
$statusText = 'SECURE & COMPLIANT';
if ($score < 60) {
    $scoreColor = '#ef4444'; // Red (Vulnerable)
    $statusText = 'CRITICAL VULNERABILITIES DETECTED';
} elseif ($score < 85) {
    $scoreColor = '#f97316'; // Orange (Warning)
    $statusText = 'WARNING INDICATORS DETECTED';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <div style="max-width: 650px; margin: 40px auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
        
        <!-- Header Brand Section -->
        <div style="background-color: #0f172a; padding: 30px; text-align: center; border-bottom: 4px solid <?= $scoreColor ?>;">
            <div style="font-size: 1.8rem; font-weight: 800; letter-spacing: 0.05em; color: #ffffff; margin-bottom: 5px;">ZERO CMS</div>
            <div style="font-size: 0.9rem; color: #94a3b8; font-family: monospace;">AUTOMATED SECURITY TELEMETRY SYSTEM</div>
        </div>

        <!-- Main Body Wrapper -->
        <div style="padding: 40px 30px;">
            
            <!-- Dashboard Score Card Widget -->
            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; text-align: center; margin-bottom: 30px;">
                <div style="font-size: 0.85rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 10px;">Security Health Index</div>
                <div style="font-size: 3.5rem; font-weight: 900; color: <?= $scoreColor ?>; line-height: 1; margin-bottom: 5px;"><?= $score ?><span style="font-size: 1.5rem; color: #94a3b8; font-weight: 500;">/100</span></div>
                <div style="display: inline-block; padding: 6px 16px; background-color: <?= $scoreColor ?>; color: #ffffff; border-radius: 50px; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 5px;">
                    <?= $statusText ?>
                </div>
                
                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0; text-align: left; font-size: 0.9rem; color: #475569; font-family: monospace;">
                    <strong>Host Target:</strong> <?= Str::escape($siteDomain) ?> (<?= Str::escape($siteName) ?>)<br>
                    <strong>Time of Audit:</strong> <?= date('Y-m-d H:i:s') ?> UTC<br>
                    <strong>Execution Scope:</strong> Automated Cron Scheduler Job
                </div>
            </div>

            <!-- High Visibility Action Warning Alert if Score is under 100 -->
            <?php if ($score < 100): ?>
                <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 6px; margin: 25px 0; border-top: 1px solid #fee2e2; border-right: 1px solid #fee2e2; border-bottom: 1px solid #fee2e2;">
                    <strong style="color: #991b1b; font-size: 0.95rem; display: block; margin-bottom: 5px;">⚠️ ATTENTION: Outstanding Concerns Identified</strong>
                    <p style="color: #7f1d1d; font-size: 0.85rem; margin: 0; line-height: 1.4;">Outstanding security vulnerabilities or non-compliant configurations have been registered. Please check the highlighted <strong>Remediation</strong> instructions below to restore a clean 100/100 compliance index.</p>
                </div>
            <?php endif; ?>

            <!-- Compiled Telemetry Content -->
            <div style="font-family: inherit;">
                <?= $parseMarkdownToHtml($report) ?>
            </div>

            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 40px 0 30px 0;">

            <!-- Footer Meta Info -->
            <div style="text-align: center; font-size: 0.8rem; color: #94a3b8; line-height: 1.6;">
                This email was auto-generated by the Zero CMS Security Module's background scheduler.<br>
                To change the audit frequency or adjust notifications, update the <code>SECURITY_AUDIT_SCHEDULE</code> and <code>ADMIN_EMAIL</code> parameters inside your system environment configuration.
            </div>

        </div>
    </div>
</body>
</html>
