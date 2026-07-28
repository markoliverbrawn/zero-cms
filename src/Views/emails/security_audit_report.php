<?php
/**
 * src/Views/emails/security_audit_report.php
 * Beautiful security audit email report template.
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

        // Headers
        if (strpos($trimmed, '# ') === 0) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<h1 style="font-size: 1.6rem; font-weight: 800; color: #ef4444; border-bottom: 2px solid #ef4444; padding-bottom: 8px; margin: 30px 0 15px 0;">' . Str::escape(substr($trimmed, 2)) . '</h1>';
            continue;
        }
        if (strpos($trimmed, '## ') === 0) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<h2 style="font-size: 1.3rem; font-weight: 700; color: #000; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin: 25px 0 12px 0;">' . Str::escape(substr($trimmed, 3)) . '</h2>';
            continue;
        }
        if (strpos($trimmed, '### ') === 0) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            // Extract severity flags if present for colored badges
            $text = substr($trimmed, 4);
            $badgeColor = '#64748b'; // default slate
            if (strpos($text, '[CRITICAL]') !== false) $badgeColor = '#b91c1c'; // dark red
            elseif (strpos($text, '[HIGH]') !== false) $badgeColor = '#ef4444'; // red
            elseif (strpos($text, '[MEDIUM]') !== false) $badgeColor = '#f97316'; // orange
            elseif (strpos($text, '[LOW]') !== false) $badgeColor = '#eab308'; // yellow
            elseif (strpos($text, '[INFO]') !== false) $badgeColor = '#06b6d4'; // cyan

            $html .= '<h3 style="font-size: 1.1rem; font-weight: 700; color: ' . $badgeColor . '; margin: 20px 0 10px 0; border-left: 4px solid ' . $badgeColor . '; padding-left: 10px;">' . Str::escape($text) . '</h3>';
            continue;
        }

        // Bullet lists
        if (strpos($trimmed, '* ') === 0 || strpos($trimmed, '- ') === 0) {
            if (!$inList) {
                $html .= '<ul style="padding-left: 20px; margin: 10px 0; line-height: 1.6; font-size: 0.95rem; color: #334155;">';
                $inList = true;
            }
            $itemText = substr($trimmed, 2);
            // Simple inline bold replacement: **text** -> <strong>text</strong>
            $itemText = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $itemText);
            // Inline code replacement: `code` -> <code style="...">code</code>
            $itemText = preg_replace('/`(.*?)`/', '<code style="background-color: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.9rem; color: #0f172a;">$1</code>', $itemText);
            $html .= '<li style="margin-bottom: 6px;">' . $itemText . '</li>';
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
            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; text-align: center; margin-bottom: 35px;">
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
